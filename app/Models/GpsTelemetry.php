<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpsTelemetry extends Model
{
    protected $table = 'gps_telemetry';

    protected $fillable = [
        'vehicle_id',
        'latitude',
        'longitude',
        'speed_kmh',
        'heading',
        'satellites',
        'hdop',
        'recorded_at',
    ];

    protected $casts = [
        'latitude'    => 'float',
        'longitude'   => 'float',
        'speed_kmh'   => 'float',
        'heading'     => 'float',
        'hdop'        => 'float',
        'recorded_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Calculate distance in metres to another lat/lng using Haversine formula.
     */
    public function distanceTo(float $lat, float $lng): float
    {
        $earthRadius = 6371000; // metres
        $dLat = deg2rad($lat - $this->latitude);
        $dLng = deg2rad($lng - $this->longitude);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
