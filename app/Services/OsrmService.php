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
     *         One entry per destination (same order), or null if OSRM is
     *         unreachable / returns nothing usable — callers then fall back
     *         to straight-line distance.
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
                "{$base}/table/v1/driving/" . implode(';', $coords),
                [
                    'sources'      => '0',
                    'destinations' => implode(';', $destIndexes),
                    'annotations'  => 'distance,duration',
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
                $dur  = (is_array($durations) && isset($durations[$i])) ? (float) $durations[$i] : null;
                $out[$i] = ($dist === null && $dur === null)
                    ? null
                    : ['distance_m' => $dist, 'duration_s' => $dur];
            }

            return $out;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
