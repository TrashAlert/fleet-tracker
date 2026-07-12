<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OsrmService
{
    /**
     * Road distance + duration from one source to many destinations, using
     * OSRM's Table service in a single request.
     *
     * @param  array<int, array{0: float, 1: float}>  $destinations  list of [lat, lng]
     * @return array<int, array{distance_m: ?float, duration_s: ?float}|null>|null
     *                                                                             One entry per destination (same order), or null if OSRM is
     *                                                                             unreachable / returns nothing usable — callers then fall back
     *                                                                             to straight-line distance.
     */
    public function table(float $srcLat, float $srcLng, array $destinations): ?array
    {
        $base = rtrim((string) config('fleet.osrm_url'), '/');
        if ($base === '' || empty($destinations)) {
            return null;
        }

        // OSRM coordinates are lng,lat. Source is index 0; destinations are 1..N.
        $coords = ["{$srcLng},{$srcLat}"];
        foreach ($destinations as $d) {
            $coords[] = "{$d[1]},{$d[0]}";
        }
        $destIndexes = range(1, count($destinations));

        try {
            $res = Http::timeout(4)->get(
                "{$base}/table/v1/driving/".implode(';', $coords),
                [
                    'sources' => '0',
                    'destinations' => implode(';', $destIndexes),
                    'annotations' => 'distance,duration',
                ]
            );

            if (! $res->ok()) {
                return null;
            }

            // Row 0 = from the source to each destination.
            $distances = $res->json('distances.0');
            $durations = $res->json('durations.0');

            if (! is_array($distances) && ! is_array($durations)) {
                return null;
            }

            $out = [];
            foreach ($destinations as $i => $_) {
                $dist = (is_array($distances) && isset($distances[$i])) ? (float) $distances[$i] : null;
                $dur = (is_array($durations) && isset($durations[$i])) ? (float) $durations[$i] : null;
                $out[$i] = ($dist === null && $dur === null)
                    ? null
                    : ['distance_m' => $dist, 'duration_s' => $dur];
            }

            return $out;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Road route between two points via OSRM's Route service: drive-time ETA,
     * distance, and the road geometry as Leaflet-ready [lat, lng] pairs.
     *
     * Same soft-dependency contract as table(): returns null when OSRM is
     * unreachable or has no route — callers fall back to straight-line display.
     *
     * @return array{eta_minutes: int, distance_km: float, geometry: array<int, array{0: float, 1: float}>}|null
     */
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $base = rtrim((string) config('fleet.osrm_url'), '/');
        if ($base === '') {
            return null;
        }

        // OSRM coordinates are lng,lat.
        $coords = "{$fromLng},{$fromLat};{$toLng},{$toLat}";

        try {
            $res = Http::timeout(4)->get("{$base}/route/v1/driving/{$coords}", [
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

            // GeoJSON is [lng, lat]; flip to [lat, lng] for Leaflet, ~1 m precision.
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
