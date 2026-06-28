<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
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
        $code     = strtoupper(trim($request->query('code', '')));
        $shipment = null;

        if ($code) {
            $shipment = Shipment::with([
                'vehicle.latestPosition',
                'vehicle.driver',
            ])->where('tracking_code', $code)->first();
        }

        return view('client.track', compact('code', 'shipment'));
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
        // moving (in_transit / delayed). Before the driver starts the trip
        // (pending) it stays hidden, and once the run is complete (delivered /
        // cancelled) it's hidden again. Suppressed here at the source so the
        // coordinates never reach the browser at all — not merely hidden in the UI.
        $locationHidden = in_array($shipment->status, ['pending', 'delivered', 'cancelled']);

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

        return response()->json([
            'status'          => $shipment->status,
            'location_hidden' => $locationHidden,
            'delivered_at'    => $shipment->actual_delivery_at?->toIso8601String(),
            'eta'             => $eta,
            'vehicle'  => $pos ? [
                'latitude'     => $pos->latitude,
                'longitude'    => $pos->longitude,
                'speed_kmh'    => $pos->speed_kmh,
                'recorded_at'  => $pos->recorded_at?->toIso8601String(),
                'driver_name'  => $shipment->vehicle?->driver_name,
                'driver_phone' => $shipment->vehicle?->driver_phone,
            ] : null,
        ]);
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
                'overview'   => 'full',
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
                'geometry'    => $geometry,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
