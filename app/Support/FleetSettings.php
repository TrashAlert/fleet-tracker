<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime overrides for operational thresholds. Admins edit these on the
 * Settings page; each stored value overrides the same-named config('fleet.*')
 * key at boot (AppServiceProvider), so every existing config('fleet.*') reader
 * picks up the change with no code change of its own.
 *
 * KEYS is the single source of truth for which settings exist, their validation
 * bounds, and whether the value is consumed inside the long-running MQTT daemon
 * (which loads config once at startup — 'daemon' settings need a subscriber
 * restart to take effect; the rest apply on the next request/scheduled run).
 */
class FleetSettings
{
    /** @var array<string, array{min: int, max: int, daemon: bool}> */
    public const KEYS = [
        'overspeed_threshold_kmh' => ['min' => 1, 'max' => 300, 'daemon' => true],
        'delay_threshold_minutes' => ['min' => 1, 'max' => 1440, 'daemon' => true],
        'gps_stale_timeout_seconds' => ['min' => 5, 'max' => 3600, 'daemon' => false],
        'offline_alert_threshold_seconds' => ['min' => 5, 'max' => 86400, 'daemon' => false],
        'max_active_shipments' => ['min' => 1, 'max' => 500, 'daemon' => false],
        'max_delivery_attempts' => ['min' => 1, 'max' => 20, 'daemon' => false],
        'geofence_radius_metres' => ['min' => 10, 'max' => 5000, 'daemon' => true],
    ];

    private const CACHE_KEY = 'fleet.settings';

    /**
     * Merge stored overrides over config('fleet.*'). Called from
     * AppServiceProvider::boot() for every web request and the daemon. Guarded
     * so a missing table (fresh clone / mid-migrate / optimize:clear) cannot
     * fatal boot.
     */
    public static function apply(): void
    {
        foreach (static::stored() as $key => $value) {
            if (isset(static::KEYS[$key]) && $value !== null) {
                config(["fleet.$key" => (int) $value]);
            }
        }
    }

    /**
     * Persist a set of key => value overrides (unknown keys ignored) and bust
     * the cache so the next boot re-reads them.
     */
    public static function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! isset(static::KEYS[$key])) {
                continue;
            }
            Setting::updateOrCreate(['key' => $key], ['value' => (string) (int) $value]);
        }

        Cache::forget(static::CACHE_KEY);
    }

    /**
     * Stored key => value pairs, cached. Only real data is cached — the empty
     * pre-migration state is not, so a fresh install picks up settings the
     * moment the table exists.
     *
     * @return array<string, string>
     */
    private static function stored(): array
    {
        try {
            $cached = Cache::get(static::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }

            if (! Schema::hasTable('settings')) {
                return [];
            }

            $data = Setting::pluck('value', 'key')->all();
            Cache::forever(static::CACHE_KEY, $data);

            return $data;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
