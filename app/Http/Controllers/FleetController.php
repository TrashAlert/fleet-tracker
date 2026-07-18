<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\GpsTelemetry;
use App\Models\Shipment;
use App\Models\ShipmentTicket;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\DeliveryConfirmedNotification;
use App\Notifications\ShipmentCreatedNotification;
use App\Notifications\ShipmentTicketApprovedNotification;
use App\Services\ActivityLogger;
use App\Services\OsrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;

class FleetController extends Controller
{
    /**
     * Fleet manager main dashboard.
     */
    public function dashboard()
    {
        $user = auth()->user();
        $query = Vehicle::with(['latestPosition', 'activeShipment', 'activeShipments'])
            ->where('is_active', true);

        // Drivers only see their own vehicle
        if ($user->isDriver() && $user->vehicle_id) {
            $query->where('id', $user->vehicle_id);
        }

        $vehicles = $query->get();

        $alertQuery = Alert::where('is_read', false)->latest('triggered_at');

        // Drivers only see alerts for their own vehicle
        if ($user->isDriver() && $user->vehicle_id) {
            $alertQuery->where('vehicle_id', $user->vehicle_id);
        }

        $unreadAlerts = $alertQuery->take(50)->get();

        // Drivers get a dedicated scoped view
        if ($user->isDriver()) {
            return view('fleet.driver-dashboard', compact('vehicles', 'unreadAlerts'));
        }

        return view('fleet.dashboard', compact('vehicles', 'unreadAlerts'));
    }

    /**
     * Live positions for all active vehicles — polled by Leaflet.js every 5s.
     */
    public function livePositions(): JsonResponse
    {
        $user = auth()->user();
        $query = Vehicle::with('latestPosition')->where('is_active', true);

        if ($user->isDriver() && $user->vehicle_id) {
            $query->where('id', $user->vehicle_id);
        }

        $positions = $query->get()
            ->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'plate' => $v->plate_number,
                'driver' => $v->driver?->name ?? $v->getRawOriginal('driver_name'),
                'is_offline' => $v->isOffline(),
                'latitude' => $v->latestPosition?->latitude,
                'longitude' => $v->latestPosition?->longitude,
                'speed_kmh' => $v->latestPosition?->speed_kmh,
                'heading' => $v->latestPosition?->heading,
                'satellites' => $v->latestPosition?->satellites,
                'hdop' => $v->latestPosition?->hdop,
                'recorded_at' => $v->latestPosition?->recorded_at?->toIso8601String(),
            ]);

        return response()->json($positions);
    }

    /**
     * Historical route playback for a single vehicle.
     */
    public function tripHistory(Request $request, Vehicle $vehicle): JsonResponse
    {
        $request->validate(['date' => 'required|date']);

        $user = auth()->user();

        // Drivers can only view their own vehicle history
        if ($user->isDriver() && $user->vehicle_id !== $vehicle->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $points = GpsTelemetry::where('vehicle_id', $vehicle->id)
            ->whereDate('recorded_at', $request->date)
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude', 'speed_kmh', 'recorded_at']);

        return response()->json($points);
    }

    /**
     * Road route + ETA from a vehicle's live position to its CURRENT delivery
     * destination (the started, in_transit shipment). Fetched on demand when a
     * dashboard user clicks a vehicle — deliberately not part of the live poll,
     * so OSRM load stays at one cached call per click instead of N per 5s.
     *
     * OSRM is a soft dependency: when it's down, `eta_minutes`/`geometry` are
     * null and the UI falls back to a straight dashed line to the destination.
     */
    public function vehicleRoute(Vehicle $vehicle): JsonResponse
    {
        $user = auth()->user();

        // Drivers can only view their own vehicle (same scoping as tripHistory)
        if ($user->isDriver() && $user->vehicle_id !== $vehicle->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $vehicle->load('latestPosition');
        $pos = $vehicle->latestPosition;
        $shipment = $vehicle->shipments()->where('status', 'in_transit')->latest()->first();

        if (! $pos || ! $shipment) {
            return response()->json([
                'available' => false,
                'reason' => ! $pos ? 'No GPS position for this vehicle yet.' : 'No delivery in progress for this vehicle.',
            ]);
        }

        // Cache briefly: a click-storm or the 20s refresh while selected reuses
        // the same computation. Keyed on shipment too, so a new delivery never
        // serves the previous route.
        $route = Cache::remember(
            "vehicle-route:{$vehicle->id}:{$shipment->id}",
            15,
            fn () => app(OsrmService::class)->route(
                (float) $pos->latitude, (float) $pos->longitude,
                (float) $shipment->destination_lat, (float) $shipment->destination_lng
            )
        );

        return response()->json([
            'available' => true,
            'tracking_code' => $shipment->tracking_code,
            'client_name' => $shipment->client_name,
            'destination' => [
                'lat' => (float) $shipment->destination_lat,
                'lng' => (float) $shipment->destination_lng,
                'address' => $shipment->destination_address,
            ],
            'expected_at' => $shipment->expected_delivery_at?->format('d M Y, H:i'),
            'eta_minutes' => $route['eta_minutes'] ?? null,
            'distance_km' => $route['distance_km'] ?? null,
            'geometry' => $route['geometry'] ?? null,
        ]);
    }

    /**
     * Mark an alert as read.
     */
    public function markAlertRead(Alert $alert): JsonResponse
    {
        $user = auth()->user();

        // Drivers can only acknowledge their own vehicle's alerts — mirrors
        // the scoping unreadAlerts applies when listing them
        if ($user->isDriver() && $user->vehicle_id !== $alert->vehicle_id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $alert->update(['is_read' => true]);

        ActivityLogger::logEvent(
            'alert_marked_read',
            "Alert #{$alert->id} ({$alert->type}) marked as read",
            'Alert', $alert->id, "#{$alert->id} {$alert->type}"
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Vehicle management listing.
     */
    public function vehicles()
    {
        $vehicles = Vehicle::with('driver')->withCount('telemetry')->paginate(20);

        // All driver users — to populate the assign driver dropdown
        $availableDrivers = User::where('role', 'driver')
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'vehicle_id']);

        return view('fleet.vehicles', compact('vehicles', 'availableDrivers'));
    }

    /**
     * Shipments listing — filters, status counts, role scoping.
     */
    public function shipments(Request $request)
    {
        $user = auth()->user();
        $query = Shipment::with('vehicle')->latest();

        if ($user->isDriver() && $user->vehicle_id) {
            $query->where('vehicle_id', $user->vehicle_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $shipments = $query->paginate(20)->withQueryString();

        $countQuery = Shipment::query();
        if ($user->isDriver() && $user->vehicle_id) {
            $countQuery->where('vehicle_id', $user->vehicle_id);
        }
        $statusCounts = $countQuery->selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status')->toArray();

        // Vehicles with their current workload — least busy first
        $vehicles = Vehicle::where('is_active', true)
            ->withCount(['shipments as active_shipments_count' => function ($q) {
                $q->whereIn('status', ['pending', 'in_transit', 'delayed']);
            }])
            ->orderBy('active_shipments_count')
            ->orderBy('plate_number')
            ->get(['id', 'plate_number', 'name']);

        $maxActive = config('fleet.max_active_shipments', 10);

        return view('fleet.shipments', compact('shipments', 'statusCounts', 'vehicles', 'maxActive'));
    }

    /**
     * Single shipment detail JSON for drawer panel.
     */
    public function shipmentDetail(Shipment $shipment): JsonResponse
    {
        $user = auth()->user();

        // Drivers can only view shipments assigned to their own vehicle —
        // the detail payload carries client PII (same scoping as tripHistory)
        if ($user->isDriver() && $user->vehicle_id !== $shipment->vehicle_id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $shipment->load(['vehicle.latestPosition', 'alerts' => fn ($q) => $q->latest()->take(5)]);

        return response()->json([
            'id' => $shipment->id,
            'tracking_code' => $shipment->tracking_code,
            'status' => $shipment->status,
            'client_name' => $shipment->client_name,
            'client_email' => $shipment->client_email,
            'client_phone' => $shipment->client_phone,
            'origin_address' => $shipment->origin_address,
            'destination_address' => $shipment->destination_address,
            'delivery_notes' => $shipment->delivery_notes,
            'destination_lat' => $shipment->destination_lat,
            'destination_lng' => $shipment->destination_lng,
            'expected_delivery_at' => $shipment->expected_delivery_at?->format('Y-m-d H:i'),
            'delivery_tier_label' => $shipment->delivery_tier
                ? (config("fleet.delivery_tiers.{$shipment->delivery_tier}.label") ?? ucfirst($shipment->delivery_tier))
                : null,
            'actual_delivery_at' => $shipment->actual_delivery_at?->format('Y-m-d H:i'),
            'delivery_photo' => $shipment->delivery_photo_url,
            'created_at' => $shipment->created_at->format('Y-m-d H:i'),
            'vehicle' => $shipment->vehicle ? [
                'id' => $shipment->vehicle->id,
                'name' => $shipment->vehicle->name,
                'plate' => $shipment->vehicle->plate_number,
                'driver' => $shipment->vehicle->driver?->name ?? $shipment->vehicle->getRawOriginal('driver_name'),
                'is_offline' => $shipment->vehicle->isOffline(),
                'latitude' => $shipment->vehicle->latestPosition?->latitude,
                'longitude' => $shipment->vehicle->latestPosition?->longitude,
                'speed_kmh' => $shipment->vehicle->latestPosition?->speed_kmh,
                'recorded_at' => $shipment->vehicle->latestPosition?->recorded_at?->diffForHumans(),
            ] : null,
            'alerts' => $shipment->alerts->map(fn ($a) => [
                'type' => $a->type,
                'message' => $a->message,
                'triggered_at' => $a->triggered_at->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Driver starts a delivery — acknowledges the shipment, pending → in_transit.
     * `delayed` (= late but never started) is startable too, so a shipment that
     * goes late before the driver taps Start can never deadlock.
     * One at a time: rejected if another shipment is already in transit.
     */
    public function startDelivery(Request $request, Shipment $shipment): JsonResponse
    {
        $user = auth()->user();

        // Only the driver of this vehicle can start it
        if ($user->isDriver() && $user->vehicle_id !== $shipment->vehicle_id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        if (! in_array($shipment->status, ['pending', 'delayed'])) {
            return response()->json(['error' => 'This shipment has already been started.'], 422);
        }

        // One at a time — block if another shipment is already in progress.
        // Only in_transit counts: delayed means "late, not yet started".
        $inProgress = Shipment::where('vehicle_id', $shipment->vehicle_id)
            ->where('status', 'in_transit')
            ->where('id', '!=', $shipment->id)
            ->first();

        if ($inProgress) {
            return response()->json([
                'error' => "Finish your current delivery ({$inProgress->tracking_code}) before starting a new one.",
            ], 422);
        }

        $shipment->update(['status' => 'in_transit']);

        ActivityLogger::logEvent(
            'shipment_started',
            "Driver {$user->name} acknowledged and started shipment {$shipment->tracking_code}",
            'Shipment', $shipment->id, $shipment->tracking_code,
            ['started_by' => $user->name, 'vehicle_id' => $shipment->vehicle_id],
            ['causer_type' => 'web', 'causer_label' => $user->name]
        );

        return response()->json(['ok' => true, 'tracking_code' => $shipment->tracking_code]);
    }

    /**
     * Driver confirms delivery — validates they are still within radius.
     */
    public function confirmDelivery(Request $request, Shipment $shipment): JsonResponse
    {
        $user = auth()->user();

        // Only the driver of this vehicle can confirm
        if ($user->isDriver() && $user->vehicle_id !== $shipment->vehicle_id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        // A package photo is required to confirm delivery.
        $photo = $request->file('photo');
        if (! $photo || ! $photo->isValid()) {
            return response()->json(['error' => 'A package photo is required to confirm delivery.'], 422);
        }
        if (! str_starts_with((string) $photo->getMimeType(), 'image/')) {
            return response()->json(['error' => 'The uploaded file must be an image.'], 422);
        }
        if ($photo->getSize() > 8 * 1024 * 1024) {
            return response()->json(['error' => 'The photo is too large (max 8 MB).'], 422);
        }

        // Shipment must be started first (delayed = late but never started)
        if ($shipment->status !== 'in_transit') {
            return response()->json(['error' => 'Start this delivery before confirming it.'], 422);
        }

        // Must have entered the radius first
        if (! $shipment->near_destination_at) {
            return response()->json(['error' => 'Vehicle has not reached the destination radius yet.'], 422);
        }

        // Validate driver is still within radius
        $shipment->load('vehicle.latestPosition');
        if (! $shipment->isCurrentlyNearDestination(200)) {
            return response()->json([
                'error' => 'You are no longer within the delivery zone. Move closer to the destination to confirm.',
            ], 422);
        }

        // GD decodes the full-resolution photo into a raw bitmap before it can be
        // scaled down — a high-megapixel phone photo can exceed PHP's 128M default.
        ini_set('memory_limit', '512M');

        // Downscale + re-encode the proof photo before storing (Intervention Image v4).
        // Auto-orientation is on by default, so phone photos stay upright.
        $manager = ImageManager::usingDriver(Driver::class);
        $image = $manager->decode($photo->getRealPath())->scaleDown(width: 1280, height: 1280);
        $encoded = $image->encodeUsingFormat(Format::JPEG, quality: 75);
        $photoPath = 'delivery-proofs/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($photoPath, (string) $encoded);

        $shipment->update([
            'status' => 'delivered',
            'actual_delivery_at' => now(),
            'left_radius_at' => null,
            'delivery_flag_sent' => false,
            'delivery_photo_path' => $photoPath,
        ]);

        ActivityLogger::logEvent(
            'shipment_delivered',
            "Shipment {$shipment->tracking_code} confirmed as delivered by driver {$user->name}",
            'Shipment', $shipment->id, $shipment->tracking_code,
            ['confirmed_by' => $user->name, 'vehicle_id' => $shipment->vehicle_id],
            ['causer_type' => 'web', 'causer_label' => $user->name]
        );

        $shipment->notify(new DeliveryConfirmedNotification($shipment));

        return response()->json([
            'ok' => true,
            'tracking_code' => $shipment->tracking_code,
            'actual_delivery_at' => $shipment->actual_delivery_at->format('d M Y, H:i'),
        ]);
    }

    /**
     * Delivery status for driver dashboard polling — checks if near destination.
     */
    public function deliveryStatus(): JsonResponse
    {
        $user = auth()->user();

        if (! $user->isDriver() || ! $user->vehicle_id) {
            return response()->json(['shipments' => []]);
        }

        $vehicle = Vehicle::with(['latestPosition', 'activeShipments'])->find($user->vehicle_id);

        if (! $vehicle || ! $vehicle->latestPosition || $vehicle->activeShipments->isEmpty()) {
            return response()->json(['shipments' => []]);
        }

        $pos = $vehicle->latestPosition;
        $active = $vehicle->activeShipments->values();

        // One OSRM Table request: road distance + drive time from the truck to every destination.
        $destinations = $active->map(fn ($s) => [(float) $s->destination_lat, (float) $s->destination_lng])->all();
        $routes = app(OsrmService::class)->table(
            (float) $pos->latitude, (float) $pos->longitude, $destinations
        );

        $shipments = $active->map(function ($s, $i) use ($pos, $routes) {
            $distance = $pos->distanceTo($s->destination_lat, $s->destination_lng); // straight-line — used by the 200m confirm radius
            $route = $routes[$i] ?? null;

            return [
                'shipment_id' => $s->id,
                'tracking_code' => $s->tracking_code,
                'client_name' => $s->client_name,
                'destination_address' => $s->destination_address,
                'delivery_notes' => $s->delivery_notes,
                'expected_at' => $s->expected_delivery_at?->format('d M Y, H:i'),
                'status' => $s->status,
                'distance_metres' => round($distance),
                'route_distance_metres' => ($route && $route['distance_m'] !== null) ? (int) round($route['distance_m']) : null,
                'route_eta_minutes' => ($route && $route['duration_s'] !== null) ? (int) round($route['duration_s'] / 60) : null,
                // Only the delivery actually in progress can be "near" — a
                // pending/delayed stop that happens to share the area must not
                // trigger the confirm-photo banner (it can't be confirmed anyway).
                'near_destination' => $s->status === 'in_transit' && $distance <= 200,
                'near_destination_at' => $s->near_destination_at?->toIso8601String(),
                'left_radius_at' => $s->left_radius_at?->toIso8601String(),
                'delivery_flag_sent' => $s->delivery_flag_sent,
            ];
        });

        // Preferred order: the optimized road tour (OSRM Trip / TSP). Fallbacks
        // keep the old behavior — nearest first by drive-time, then straight-line.
        $tourOrder = $this->optimizedTourOrder($pos, $active);

        if ($tourOrder !== null) {
            $shipments = collect($tourOrder)->map(fn ($i) => $shipments[$i]);
        } elseif ($routes !== null) {
            $shipments = $shipments->sortBy(fn ($s) => $s['route_eta_minutes'] ?? PHP_INT_MAX);
        } else {
            $shipments = $shipments->sortBy('distance_metres');
        }

        $shipments = $shipments->values()->map(function (array $s, int $i) {
            $s['stop_order'] = $i + 1;

            return $s;
        });

        return response()->json([
            'shipments' => $shipments,
            'route_optimized' => $tourOrder !== null,
        ]);
    }

    /**
     * Optimized visit order for a driver's active stops, as 0-based indexes
     * into the collection. The started (in_transit) delivery is always stop 1 —
     * the driver is committed to it — and the REMAINING stops are solved as a
     * road tour starting from ITS destination (or from the truck when nothing
     * is started). Returns null when there's nothing to optimize (fewer than
     * two free stops) or OSRM is unavailable — callers fall back to sorting.
     */
    private function optimizedTourOrder(GpsTelemetry $pos, $active): ?array
    {
        $currentIdx = $active->search(fn ($s) => $s->status === 'in_transit');
        $rest = array_values(array_filter(
            $active->keys()->all(),
            fn ($i) => $i !== $currentIdx
        ));

        if (count($rest) < 2) {
            return null;
        }

        [$startLat, $startLng] = $currentIdx !== false
            ? [(float) $active[$currentIdx]->destination_lat, (float) $active[$currentIdx]->destination_lng]
            : [(float) $pos->latitude, (float) $pos->longitude];

        $stops = array_map(
            fn ($i) => [(float) $active[$i]->destination_lat, (float) $active[$i]->destination_lng],
            $rest
        );

        $order = app(OsrmService::class)->trip($startLat, $startLng, $stops);
        if ($order === null) {
            return null;
        }

        $ordered = array_map(fn ($j) => $rest[$j], $order);
        if ($currentIdx !== false) {
            array_unshift($ordered, $currentIdx);
        }

        return $ordered;
    }

    /**
     * Manual status override (admin/manager only).
     */
    public function updateShipmentStatus(Request $request, Shipment $shipment): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending,in_transit,delayed,delivered,cancelled',
        ]);

        $old = $shipment->status;
        $shipment->update($data);

        ActivityLogger::logEvent(
            'shipment_status_overridden',
            "Shipment {$shipment->tracking_code} status manually changed: {$old} → {$data['status']}",
            'Shipment', $shipment->id, $shipment->tracking_code,
            ['old_status' => $old, 'new_status' => $data['status']]
        );

        return response()->json(['ok' => true, 'status' => $shipment->status]);
    }

    /**
     * Create a new shipment and auto-generate tracking code.
     */
    public function storeShipment(Request $request): JsonResponse
    {
        // Force JSON error responses so the JS fetch can always parse them
        $request->headers->set('Accept', 'application/json');

        $tiers = config('fleet.delivery_tiers', []);

        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'nullable|string|max:20',
            'origin_address' => 'required|string|max:500',
            'destination_address' => 'required|string|max:500',
            'delivery_notes' => 'nullable|string|max:1000',
            'destination_lat' => 'required|numeric|between:-90,90',
            'destination_lng' => 'required|numeric|between:-180,180',
            // Either a service tier (date computed from config/fleet.php) or an
            // explicit custom date — the admin form offers both.
            'delivery_tier' => 'nullable|required_without:expected_delivery_at|in:'.implode(',', array_keys($tiers)),
            'expected_delivery_at' => 'nullable|required_without:delivery_tier|date',
            // Present when creating from an approved forwarding request
            'ticket_id' => 'nullable|exists:shipment_tickets,id',
        ]);

        // A tier computes the expected date; an explicit date means no tier.
        if (! empty($data['delivery_tier']) && empty($data['expected_delivery_at'])) {
            $data['expected_delivery_at'] = now()->addDays((int) $tiers[$data['delivery_tier']]['days']);
        } elseif (! empty($data['expected_delivery_at'])) {
            $data['delivery_tier'] = null;
        }

        // Enforce the per-vehicle active shipment cap
        $maxActive = config('fleet.max_active_shipments', 10);
        $activeCount = Shipment::where('vehicle_id', $data['vehicle_id'])
            ->whereIn('status', ['pending', 'in_transit', 'delayed'])
            ->count();

        if ($activeCount >= $maxActive) {
            return response()->json([
                'message' => "This vehicle already has {$activeCount} active deliveries (max {$maxActive}). Assign to another vehicle or wait for deliveries to complete.",
            ], 422);
        }

        // Cast coords to float so MySQL doesn't reject string values
        $data['destination_lat'] = (float) $data['destination_lat'];
        $data['destination_lng'] = (float) $data['destination_lng'];

        $shipment = Shipment::create($data);

        ActivityLogger::logEvent(
            'shipment_created',
            "Shipment {$shipment->tracking_code} created for client {$shipment->client_name} — vehicle ID {$shipment->vehicle_id}",
            'Shipment', $shipment->id, $shipment->tracking_code,
            ['client_email' => $shipment->client_email, 'expected_at' => $shipment->expected_delivery_at]
        );

        // Created from a customer shipment request? Approve it — the customer
        // gets ONE email: ShipmentTicketApprovedNotification (it carries the new
        // tracking code + link), instead of the generic created email. Only a
        // still-pending request counts; a stale/reviewed ticket_id degrades to a
        // normal creation with the normal created email.
        $ticket = ! empty($data['ticket_id']) ? ShipmentTicket::find($data['ticket_id']) : null;
        $approving = $ticket && $ticket->status === 'pending';

        if ($approving) {
            $ticket->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'created_shipment_id' => $shipment->id,
            ]);

            $ticket->notify(
                new ShipmentTicketApprovedNotification($ticket, $shipment)
            );

            ActivityLogger::logEvent(
                'ticket_approved',
                "Shipment request {$ticket->request_code} approved — shipment {$shipment->tracking_code} created",
                'ShipmentTicket', $ticket->id, $ticket->request_code,
                ['new_shipment_id' => $shipment->id, 'new_tracking_code' => $shipment->tracking_code]
            );
        } else {
            $shipment->notify(new ShipmentCreatedNotification($shipment));
        }

        return response()->json([
            'tracking_code' => $shipment->tracking_code,
            'id' => $shipment->id,
        ], 201);
    }

    /**
     * Update vehicle details.
     */
    public function storeVehicle(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'plate_number' => 'required|string|max:20|unique:vehicles,plate_number',
            'mqtt_client_id' => 'required|string|max:100|unique:vehicles,mqtt_client_id',
            'driver_user_id' => 'nullable|exists:users,id',
        ]);

        $driverUserId = $data['driver_user_id'] ?? null;
        unset($data['driver_user_id']);

        $vehicle = Vehicle::create(array_merge($data, ['is_active' => true]));

        // Link selected driver to this vehicle
        if ($driverUserId) {
            User::where('vehicle_id', $vehicle->id)->update(['vehicle_id' => null]);
            User::where('id', $driverUserId)->update(['vehicle_id' => $vehicle->id]);
        }

        ActivityLogger::logEvent(
            'vehicle_created',
            "Vehicle [{$vehicle->name}] registered with plate {$vehicle->plate_number}",
            'Vehicle', $vehicle->id, $vehicle->plate_number,
            ['mqtt_client_id' => $vehicle->mqtt_client_id, 'driver_user_id' => $driverUserId]
        );

        return redirect()->route('fleet.vehicles')
            ->with('success', "Vehicle {$vehicle->name} registered successfully.");
    }

    public function updateVehicle(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'plate_number' => 'required|string|max:20|unique:vehicles,plate_number,'.$vehicle->id,
            'mqtt_client_id' => 'required|string|max:100|unique:vehicles,mqtt_client_id,'.$vehicle->id,
            'driver_user_id' => 'nullable|exists:users,id',
        ]);

        $driverUserId = $data['driver_user_id'] ?? null;
        unset($data['driver_user_id']);

        $vehicle->update($data);

        // Relink driver — unlink old, link new
        User::where('vehicle_id', $vehicle->id)->update(['vehicle_id' => null]);
        if ($driverUserId) {
            User::where('id', $driverUserId)->update(['vehicle_id' => $vehicle->id]);
        }

        ActivityLogger::logEvent(
            'vehicle_updated',
            "Vehicle [{$vehicle->plate_number}] details updated",
            'Vehicle', $vehicle->id, $vehicle->plate_number,
            ['changed_fields' => array_keys($data), 'driver_user_id' => $driverUserId]
        );

        return response()->json(['ok' => true, 'vehicle' => $vehicle->fresh()]);
    }

    /**
     * Toggle vehicle active/inactive status.
     */
    public function toggleVehicle(Request $request, Vehicle $vehicle): JsonResponse
    {
        $vehicle->update(['is_active' => $request->boolean('is_active')]);

        $status = $vehicle->is_active ? 'activated' : 'deactivated';
        ActivityLogger::logEvent(
            'vehicle_toggled',
            "Vehicle [{$vehicle->plate_number}] was {$status}",
            'Vehicle', $vehicle->id, $vehicle->plate_number,
            ['is_active' => $vehicle->is_active]
        );

        return response()->json(['ok' => true, 'is_active' => $vehicle->is_active]);
    }

    /**
     * Permanently delete a vehicle and its telemetry.
     */
    public function destroyVehicle(Vehicle $vehicle): JsonResponse
    {
        $label = $vehicle->plate_number;
        $id = $vehicle->id;

        $vehicle->delete();

        ActivityLogger::logEvent(
            'vehicle_deleted',
            "Vehicle [{$label}] permanently deleted",
            'Vehicle', $id, $label
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Return unread alerts as JSON (polled by dashboard every 5s).
     */
    public function unreadAlerts(): JsonResponse
    {
        $user = auth()->user();
        $query = Alert::where('is_read', false)->latest('triggered_at');

        if ($user->isDriver() && $user->vehicle_id) {
            $query->where('vehicle_id', $user->vehicle_id);
        }

        $alerts = $query->take(50)->get()->map(fn ($a) => [
            'id' => $a->id,
            'type' => $a->type,
            'message' => $a->message,
            'triggered_at' => $a->triggered_at->diffForHumans(),
        ]);

        return response()->json($alerts);
    }
}
