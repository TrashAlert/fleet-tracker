<?php

namespace App\Services;

use App\Models\GpsTelemetry;
use Illuminate\Support\Collection;

/**
 * Driver manifest ordering — the single source of truth for "what order do
 * this vehicle's active stops get visited in", shared by the driver dashboard
 * (FleetController::deliveryStatus) and the public tracking page's queue
 * position (ClientTrackingController::status). Keep both callers on this one
 * method: if the orderings diverge, the customer gets promised a queue
 * position the driver's list doesn't actually show.
 */
class ManifestService
{
    /**
     * Visit order for a vehicle's active shipments, as 0-based indexes into
     * $active. The started (in_transit) delivery is always first — the driver
     * is committed to it — and the REMAINING stops are solved as a road tour
     * (OSRM Trip / TSP) starting from ITS destination, or from the truck when
     * nothing is started. With fewer than two free stops the order is exact
     * and no solver is queried.
     *
     * @return array{order: array<int, int>, geometry: ?array}|null
     *                                                              null when an order needs OSRM and it is unavailable, or needs
     *                                                              a truck position that doesn't exist — callers then fall back
     *                                                              to distance sorting.
     */
    public function tour(?GpsTelemetry $pos, Collection $active, bool $withGeometry = false): ?array
    {
        $currentIdx = $active->search(fn ($s) => $s->status === 'in_transit');
        $rest = array_values(array_filter(
            $active->keys()->all(),
            fn ($i) => $i !== $currentIdx
        ));

        // 0 or 1 free stops — the order is trivially exact, no solver needed.
        if (count($rest) < 2) {
            $order = $rest;
            if ($currentIdx !== false) {
                array_unshift($order, $currentIdx);
            }

            return ['order' => $order, 'geometry' => null];
        }

        if ($currentIdx !== false) {
            $startLat = (float) $active[$currentIdx]->destination_lat;
            $startLng = (float) $active[$currentIdx]->destination_lng;
        } elseif ($pos) {
            $startLat = (float) $pos->latitude;
            $startLng = (float) $pos->longitude;
        } else {
            return null; // nothing started and no GPS fix yet — no tour start point
        }

        $stops = array_map(
            fn ($i) => [(float) $active[$i]->destination_lat, (float) $active[$i]->destination_lng],
            $rest
        );

        $trip = app(OsrmService::class)->trip($startLat, $startLng, $stops, $withGeometry);
        if ($trip === null) {
            return null;
        }

        $order = array_map(fn ($j) => $rest[$j], $trip['order']);
        if ($currentIdx !== false) {
            array_unshift($order, $currentIdx);
        }

        return ['order' => $order, 'geometry' => $trip['geometry']];
    }
}
