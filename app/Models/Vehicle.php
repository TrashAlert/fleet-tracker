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
        'driver_name',
        'driver_phone',
        'mqtt_client_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

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

    public function activityLogs()
    {
        return ActivityLog::where('subject_type', 'Vehicle')
            ->where('subject_id', $this->id)
            ->orderByDesc('logged_at');
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
