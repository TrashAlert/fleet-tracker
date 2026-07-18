<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Shipment;
use App\Services\ManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ClientTrackingController extends Controller
{
    /**
     * Public tracking portal — no auth required.
     */
    public function index(Request $request)
    {
        $code = strtoupper(trim($request->query('code', '')));
        $shipment = null;

        if ($code) {
            $shipment = Shipment::with([
                'vehicle.latestPosition',
                'vehicle.driver',
            ])->where('tracking_code', $code)->first();
        }

        $timeline = $shipment ? $this->timeline($shipment) : [];

        return view('client.track', compact('code', 'shipment', 'timeline'));
    }

    /**
     * JSON status endpoint — polled every 10s by the tracking page.
     */
    public function status(string $trackingCode): JsonResponse
    {
        $shipment = Shipment::with(['vehicle.latestPosition', 'vehicle.driver'])
            ->where('tracking_code', strtoupper($trackingCode))
            ->firstOrFail();

        // The truck's live location is shared only while the shipment is actually
        // moving (in_transit). Before the driver starts the trip — pending, or
        // delayed (= late but never started) — it stays hidden, and once the run
        // is complete (delivered / cancelled) it's hidden again. Suppressed here
        // at the source so the coordinates never reach the browser at all — not
        // merely hidden in the UI.
        $locationHidden = $shipment->status !== 'in_transit';

        $pos = $locationHidden ? null : $shipment->vehicle?->latestPosition;

        // Road-distance ETA from the truck's live position to the destination.
        // Only while moving (when $pos is available). Computed server-side because
        // OSRM lives on the host's localhost and isn't reachable from the client.
        $eta = null;
        if ($pos && $shipment->destination_lat && $shipment->destination_lng) {
            $eta = $this->routeEta(
                (float) $pos->latitude,
                (float) $pos->longitude,
                (float) $shipment->destination_lat,
                (float) $shipment->destination_lng
            );
        }

        // Queue position — how many stops the driver visits before this one,
        // derived from the SAME manifest order the driver dashboard shows
        // (ManifestService). Null when it can't be derived (OSRM down, no GPS
        // fix yet, terminal status) — the UI hides the row. Privacy-safe: a
        // count reveals no vehicle location.
        $stopsAhead = null;
        if ($shipment->status === 'in_transit') {
            $stopsAhead = 0; // the delivery in progress IS the current stop
        } elseif (in_array($shipment->status, ['pending', 'delayed']) && $shipment->vehicle) {
            $active = $shipment->vehicle->activeShipments()->get()->values();
            $idx = $active->search(fn ($s) => $s->id === $shipment->id);
            $tour = $idx === false
                ? null
                : app(ManifestService::class)->tour($shipment->vehicle->latestPosition, $active);
            if ($tour !== null) {
                $posInTour = array_search($idx, $tour['order'], true);
                $stopsAhead = $posInTour === false ? null : $posInTour;
            }
        }

        return response()->json([
            'status' => $shipment->status,
            'location_hidden' => $locationHidden,
            'stops_ahead' => $stopsAhead,
            'timeline' => $this->timeline($shipment),
            'delivered_at' => $shipment->actual_delivery_at?->toIso8601String(),
            'eta' => $eta,
            'vehicle' => $pos ? [
                'latitude' => $pos->latitude,
                'longitude' => $pos->longitude,
                'speed_kmh' => $pos->speed_kmh,
                'recorded_at' => $pos->recorded_at?->toIso8601String(),
                'driver_name' => $shipment->vehicle?->driver_name,
                'driver_phone' => $shipment->vehicle?->driver_phone,
            ] : null,
        ]);
    }

    /**
     * Client-safe delivery event timeline, oldest first.
     *
     * Built from the curated activity_log actions the pipeline writes for a
     * shipment — internal events (left-zone flags, MQTT noise, audit diffs)
     * are simply not whitelisted, and log descriptions (which carry plates,
     * driver names, thresholds) are never exposed: each action maps to a
     * fixed label. Anchors are synthesized from shipment columns when the
     * logs are missing (seeders/imports), so the timeline is never empty.
     *
     * @return array<int, array{event: string, label: string, at: string, at_display: string}>
     */
    private function timeline(Shipment $shipment): array
    {
        $labels = [
            'shipment_created' => 'Shipment created',
            'shipment_delayed' => 'Delivery delayed',
            'shipment_started' => 'Out for delivery',
            'shipment_near_destination' => 'Driver is arriving',
            'shipment_delivered' => 'Delivered',
        ];

        $logs = ActivityLog::forSubject('Shipment', (int) $shipment->id)
            ->whereIn('action', array_merge(array_keys($labels), ['shipment_status_overridden']))
            ->orderBy('logged_at')
            ->get();

        $events = [];
        foreach ($logs as $log) {
            $action = $log->action;
            $label = $labels[$action] ?? null;

            // Manual corrections surface as their client-facing outcome — a
            // revert to pending/delayed is internal housekeeping, not an event.
            if ($action === 'shipment_status_overridden') {
                $newStatus = $log->new_values['new_status'] ?? null;
                $label = match ($newStatus) {
                    'cancelled' => 'Shipment cancelled',
                    'delivered' => 'Delivered',
                    'in_transit' => 'Out for delivery',
                    default => null,
                };
                $action = "shipment_status_overridden:{$newStatus}";
            }

            if ($label === null) {
                continue;
            }

            $events[] = ['event' => $action, 'label' => $label, 'at' => $log->logged_at];
        }

        $seenLabels = array_column($events, 'label');

        if (! in_array('Shipment created', $seenLabels, true)) {
            array_unshift($events, ['event' => 'shipment_created', 'label' => 'Shipment created', 'at' => $shipment->created_at]);
        }
        if ($shipment->status === 'delivered' && $shipment->actual_delivery_at && ! in_array('Delivered', $seenLabels, true)) {
            $events[] = ['event' => 'shipment_delivered', 'label' => 'Delivered', 'at' => $shipment->actual_delivery_at];
        }

        usort($events, fn ($a, $b) => $a['at'] <=> $b['at']);

        // Collapse consecutive repeats (e.g. delivered + a later override to
        // delivered) and shape for both the Blade render and the JSON poll.
        $out = [];
        foreach ($events as $ev) {
            if ($out !== [] && end($out)['label'] === $ev['label']) {
                continue;
            }
            $out[] = [
                'event' => $ev['event'],
                'label' => $ev['label'],
                'at' => $ev['at']->toIso8601String(),
                'at_display' => $ev['at']->format('d M Y, H:i'),
            ];
        }

        return $out;
    }

    /**
     * Road ETA + route geometry from the self-hosted OSRM routing engine.
     * Returns ['eta_minutes' => int, 'distance_km' => float, 'geometry' => [[lat,lng], ...]],
     * or null if OSRM is unreachable or returns no route — the page then shows
     * neither an ETA nor a route line rather than breaking tracking.
     *
     * OSRM speaks lng,lat; the returned geometry is flipped to [lat, lng] here
     * (Leaflet's order) and rounded to ~1 m so the browser can feed it straight
     * into L.polyline without reaching OSRM itself (which lives on localhost).
     */
    private function routeEta(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $base = rtrim((string) config('fleet.osrm_url'), '/');
        if ($base === '') {
            return null;
        }

        $coords = "{$fromLng},{$fromLat};{$toLng},{$toLat}";

        try {
            $res = Http::timeout(3)->get("{$base}/route/v1/driving/{$coords}", [
                // 'full' returns OSRM's complete road geometry (every OSM node),
                // so curves and corners trace accurately. 'simplified' (the
                // default) drops vertices and makes bends look faceted.
                'overview' => 'full',
                'geometries' => 'geojson',
            ]);

            if (! $res->ok()) {
                return null;
            }

            $route = $res->json('routes.0');
            if (! $route || ! isset($route['duration'], $route['distance'])) {
                return null;
            }

            // GeoJSON coordinates are [lng, lat]; flip to [lat, lng] for Leaflet.
            $geometry = [];
            foreach ($route['geometry']['coordinates'] ?? [] as $point) {
                if (isset($point[0], $point[1])) {
                    $geometry[] = [round((float) $point[1], 5), round((float) $point[0], 5)];
                }
            }

            return [
                'eta_minutes' => (int) round($route['duration'] / 60),
                'distance_km' => round($route['distance'] / 1000, 1),
                'geometry' => $geometry,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
