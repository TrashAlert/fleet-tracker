<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Shipment extends Model
{
    use Loggable;
    protected $fillable = [
        'vehicle_id',
        'tracking_code',
        'client_name',
        'client_email',
        'client_phone',
        'origin_address',
        'destination_address',
        'destination_lat',
        'destination_lng',
        'expected_delivery_at',
        'actual_delivery_at',
        'status',
        'delay_notified',
    ];

    protected $casts = [
        'destination_lat'      => 'float',
        'destination_lng'      => 'float',
        'expected_delivery_at' => 'datetime',
        'actual_delivery_at'   => 'datetime',
        'delay_notified'       => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate tracking code on creation
        static::creating(function (Shipment $shipment) {
            $shipment->tracking_code ??= strtoupper(Str::random(10));
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function isDelayed(): bool
    {
        if (in_array($this->status, ['delivered', 'cancelled'])) return false;
        $threshold = config('fleet.delay_threshold_minutes', 15);
        return now()->diffInMinutes($this->expected_delivery_at, false) < -$threshold;
    }

    public function isNearDestination(float $radiusMetres = 200): bool
    {
        $latest = $this->vehicle->latestPosition;
        if (! $latest) return false;

        return $latest->distanceTo($this->destination_lat, $this->destination_lng) <= $radiusMetres;
    }
}
