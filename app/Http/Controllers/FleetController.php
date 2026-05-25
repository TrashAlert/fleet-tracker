<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\GpsTelemetry;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    /**
     * Fleet manager main dashboard.
     */
    public function dashboard()
    {
        $user  = auth()->user();
        $query = Vehicle::with(['latestPosition', 'activeShipment'])
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

        return view('fleet.dashboard', compact('vehicles', 'unreadAlerts'));
    }

    /**
     * Live positions for all active vehicles — polled by Leaflet.js every 5s.
     */
    public function livePositions(): JsonResponse
    {
        $user  = auth()->user();
        $query = Vehicle::with('latestPosition')->where('is_active', true);

        if ($user->isDriver() && $user->vehicle_id) {
            $query->where('id', $user->vehicle_id);
        }

        $positions = $query->get()
            ->map(fn(Vehicle $v) => [
                'id'           => $v->id,
                'name'         => $v->name,
                'plate'        => $v->plate_number,
                'driver'       => $v->driver_name,
                'is_offline'   => $v->isOffline(),
                'latitude'     => $v->latestPosition?->latitude,
                'longitude'    => $v->latestPosition?->longitude,
                'speed_kmh'    => $v->latestPosition?->speed_kmh,
                'heading'      => $v->latestPosition?->heading,
                'recorded_at'  => $v->latestPosition?->recorded_at?->toIso8601String(),
            ]);

        return response()->json($positions);
    }

    /**
     * Historical route playback for a single vehicle.
     */
    public function tripHistory(Request $request, Vehicle $vehicle): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $points = GpsTelemetry::where('vehicle_id', $vehicle->id)
            ->whereDate('recorded_at', $request->date)
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude', 'speed_kmh', 'recorded_at']);

        return response()->json($points);
    }

    /**
     * Mark an alert as read.
     */
    public function markAlertRead(Alert $alert): JsonResponse
    {
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
        $vehicles = Vehicle::withCount('telemetry')->paginate(20);
        return view('fleet.vehicles', compact('vehicles'));
    }

    /**
     * Shipments listing — filters, status counts, role scoping.
     */
    public function shipments(Request $request)
    {
        $user  = auth()->user();
        $query = Shipment::with('vehicle')->latest();

        if ($user->isDriver() && $user->vehicle_id) {
            $query->where('vehicle_id', $user->vehicle_id);
        }
        if ($request->filled('status'))     { $query->where('status', $request->status); }
        if ($request->filled('vehicle_id')) { $query->where('vehicle_id', $request->vehicle_id); }
        if ($request->filled('date_from'))  { $query->whereDate('created_at', '>=', $request->date_from); }
        if ($request->filled('date_to'))    { $query->whereDate('created_at', '<=', $request->date_to); }

        $shipments = $query->paginate(20)->withQueryString();

        $countQuery = Shipment::query();
        if ($user->isDriver() && $user->vehicle_id) {
            $countQuery->where('vehicle_id', $user->vehicle_id);
        }
        $statusCounts = $countQuery->selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status')->toArray();

        $vehicles = Vehicle::where('is_active', true)->get(['id', 'plate_number', 'name']);

        return view('fleet.shipments', compact('shipments', 'statusCounts', 'vehicles'));
    }

    /**
     * Single shipment detail JSON for drawer panel.
     */
    public function shipmentDetail(Shipment $shipment): JsonResponse
    {
        $shipment->load(['vehicle.latestPosition', 'alerts' => fn($q) => $q->latest()->take(5)]);

        return response()->json([
            'id'                   => $shipment->id,
            'tracking_code'        => $shipment->tracking_code,
            'status'               => $shipment->status,
            'client_name'          => $shipment->client_name,
            'client_email'         => $shipment->client_email,
            'client_phone'         => $shipment->client_phone,
            'origin_address'       => $shipment->origin_address,
            'destination_address'  => $shipment->destination_address,
            'destination_lat'      => $shipment->destination_lat,
            'destination_lng'      => $shipment->destination_lng,
            'expected_delivery_at' => $shipment->expected_delivery_at?->format('Y-m-d H:i'),
            'actual_delivery_at'   => $shipment->actual_delivery_at?->format('Y-m-d H:i'),
            'created_at'           => $shipment->created_at->format('Y-m-d H:i'),
            'vehicle' => $shipment->vehicle ? [
                'id'          => $shipment->vehicle->id,
                'name'        => $shipment->vehicle->name,
                'plate'       => $shipment->vehicle->plate_number,
                'driver'      => $shipment->vehicle->driver_name,
                'is_offline'  => $shipment->vehicle->isOffline(),
                'latitude'    => $shipment->vehicle->latestPosition?->latitude,
                'longitude'   => $shipment->vehicle->latestPosition?->longitude,
                'speed_kmh'   => $shipment->vehicle->latestPosition?->speed_kmh,
                'recorded_at' => $shipment->vehicle->latestPosition?->recorded_at?->diffForHumans(),
            ] : null,
            'alerts' => $shipment->alerts->map(fn($a) => [
                'type'         => $a->type,
                'message'      => $a->message,
                'triggered_at' => $a->triggered_at->diffForHumans(),
            ]),
        ]);
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

        $data = $request->validate([
            'vehicle_id'           => 'required|exists:vehicles,id',
            'client_name'          => 'required|string|max:255',
            'client_email'         => 'required|email|max:255',
            'client_phone'         => 'nullable|string|max:20',
            'origin_address'       => 'required|string|max:500',
            'destination_address'  => 'required|string|max:500',
            'destination_lat'      => 'required|numeric|between:-90,90',
            'destination_lng'      => 'required|numeric|between:-180,180',
            'expected_delivery_at' => 'required|date',
        ]);

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

        return response()->json([
            'tracking_code' => $shipment->tracking_code,
            'id'            => $shipment->id,
        ], 201);
    }
    /**
     * Update vehicle details.
     */
    public function updateVehicle(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'plate_number'   => 'required|string|max:20|unique:vehicles,plate_number,' . $vehicle->id,
            'mqtt_client_id' => 'required|string|max:100|unique:vehicles,mqtt_client_id,' . $vehicle->id,
            'driver_name'    => 'nullable|string|max:255',
            'driver_phone'   => 'nullable|string|max:20',
        ]);

        $vehicle->update($data);

        ActivityLogger::logEvent(
            'vehicle_updated',
            "Vehicle [{$vehicle->plate_number}] details updated",
            'Vehicle', $vehicle->id, $vehicle->plate_number,
            ['changed_fields' => array_keys($data)]
        );

        return response()->json(['ok' => true, 'vehicle' => $vehicle]);
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
        $id    = $vehicle->id;

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
        $alerts = Alert::where('is_read', false)
            ->latest('triggered_at')
            ->take(20)
            ->get()
            ->map(fn($a) => [
                'id'           => $a->id,
                'type'         => $a->type,
                'message'      => $a->message,
                'triggered_at' => $a->triggered_at->diffForHumans(),
            ]);

        return response()->json($alerts);
    }
}
