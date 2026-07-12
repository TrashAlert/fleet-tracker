<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Geocoding against a SELF-HOSTED Nominatim instance.
 *
 * Self-hosting is not an optimisation here — the public nominatim.openstreetmap.org
 * endpoint has an absolute usage policy (1 req/s, no bulk, no autocomplete) that an
 * address-lookup box would violate immediately. Running our own removes the limit
 * and keeps client addresses off a third-party server.
 *
 * Like OsrmService, this is a SOFT DEPENDENCY: every method returns null when the
 * container is down or slow. Callers must degrade (fall back to the manual lat/lng
 * inputs that already exist), never fail the request.
 *
 * Reachable only from the server (bound to 127.0.0.1), so the browser never talks
 * to it directly and no API surface is exposed publicly.
 */
class NominatimService
{
    /** Results are stable for a long time; addresses do not move. */
    private const CACHE_HOURS = 24;

    /**
     * Address -> coordinates. Returns the best matches, ranked by Nominatim.
     *
     * @return array<int, array{display_name: string, lat: float, lng: float, type: ?string}>|null
     */
    public function search(string $query, int $limit = 5): ?array
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) < 3) {
            return [];   // too short to be meaningful; not an error
        }

        $key = 'nominatim:search:' . md5(mb_strtolower($query) . "|{$limit}");

        return Cache::remember($key, now()->addHours(self::CACHE_HOURS), function () use ($query, $limit) {
            $rows = $this->get('/search', [
                'q'            => $query,
                'format'       => 'jsonv2',
                'limit'        => $limit,
                'addressdetails' => 1,
                // Constrain to Malaysia: a delivery address is never in Peru, and
                // this removes most ambiguous global matches.
                'countrycodes' => config('fleet.nominatim_country_codes', 'my'),
            ]);

            if ($rows === null) {
                return null;
            }

            return array_map(fn ($r) => [
                'display_name' => (string) ($r['display_name'] ?? ''),
                'lat'          => (float) ($r['lat'] ?? 0),
                'lng'          => (float) ($r['lon'] ?? 0),
                'type'         => $r['type'] ?? null,
            ], $rows);
        });
    }

    /**
     * Coordinates -> a human-readable address.
     * Useful for showing the driver which road the truck is currently on.
     *
     * @return array{display_name: string, road: ?string, suburb: ?string, postcode: ?string}|null
     */
    public function reverse(float $lat, float $lng): ?array
    {
        // Round to ~11 m so a moving truck doesn't blow the cache every packet.
        $key = sprintf('nominatim:reverse:%.4f,%.4f', $lat, $lng);

        return Cache::remember($key, now()->addHours(self::CACHE_HOURS), function () use ($lat, $lng) {
            $row = $this->get('/reverse', [
                'lat'            => $lat,
                'lon'            => $lng,
                'format'         => 'jsonv2',
                'addressdetails' => 1,
                'zoom'           => 17,   // street level
            ]);

            if ($row === null || ! isset($row['display_name'])) {
                return null;
            }

            $addr = $row['address'] ?? [];

            return [
                'display_name' => (string) $row['display_name'],
                'road'         => $addr['road'] ?? null,
                'suburb'       => $addr['suburb'] ?? $addr['village'] ?? $addr['town'] ?? null,
                'postcode'     => $addr['postcode'] ?? null,
            ];
        });
    }

    /** Is the container up and finished importing? */
    public function healthy(): bool
    {
        $base = rtrim((string) config('fleet.nominatim_url'), '/');
        if ($base === '') {
            return false;
        }

        try {
            return Http::timeout(2)->get("{$base}/status")->ok();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array<mixed>|null */
    private function get(string $path, array $query): ?array
    {
        $base = rtrim((string) config('fleet.nominatim_url'), '/');
        if ($base === '') {
            return null;
        }

        try {
            $res = Http::timeout(4)
                // Nominatim's usage policy requires an identifying User-Agent.
                // Good practice even against our own instance — it makes the
                // container logs readable.
                ->withHeaders(['User-Agent' => 'FleetTracker/1.0 (self-hosted)'])
                ->get($base . $path, $query);

            if (! $res->ok()) {
                return null;
            }

            $json = $res->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            return null;   // soft dependency: caller falls back to manual entry
        }
    }
}
