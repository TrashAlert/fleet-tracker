<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehicle extends Model
{
    use Loggable;

    protected $fillable = [
        'plate_number',
        'name',
        'mqtt_client_id',
        'is_active',
        // driver_name and driver_phone kept in DB for legacy data
        // but no longer collected via forms — use driver() relationship instead
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function telemetry(): HasMany
    {
        return $this->hasMany(GpsTelemetry::class);
    }

    public function latestPosition(): HasOne
    {
        return $this->hasOne(GpsTelemetry::class)->latestOfMany('recorded_at');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function activeShipment(): HasOne
    {
        return $this->hasOne(Shipment::class)
            ->whereIn('status', ['pending', 'in_transit', 'delayed'])
            ->latestOfMany();
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * The driver linked to this vehicle via the users table.
     * A driver user sets vehicle_id = this vehicle's id.
     */
    public function driver(): HasOne
    {
        return $this->hasOne(User::class)->where('role', 'driver');
    }

    public function activityLogs()
    {
        return ActivityLog::where('subject_type', 'Vehicle')
            ->where('subject_id', $this->id)
            ->orderByDesc('logged_at');
    }

    /**
     * Convenience — driver's display name from linked user or legacy field.
     */
    public function getDriverNameAttribute(): ?string
    {
        return $this->driver?->name ?? $this->getRawOriginal('driver_name');
    }

    /**
     * Convenience — driver's phone from linked user or legacy field.
     */
    public function getDriverPhoneAttribute(): ?string
    {
        return $this->driver?->phone ?? $this->getRawOriginal('driver_phone');
    }

    /**
     * Check if the vehicle has stopped sending data (offline).
     */
    public function isOffline(): bool
    {
        $latest = $this->latestPosition;
        if (! $latest) return true;

        $timeout = config('fleet.gps_stale_timeout_seconds', 60);
        return $latest->recorded_at->diffInSeconds(now()) > $timeout;
    }
}
