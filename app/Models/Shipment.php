<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Shipment extends Model
{
    use Loggable, Notifiable;

    /**
     * Route notifications to the client's email address.
     * Required by the Notifiable trait when the model is not a User.
     */
    public function routeNotificationForMail(): string
    {
        return $this->client_email;
    }
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
        'near_destination_at',
        'left_radius_at',
        'delivery_flag_sent',
        'delivery_photo_path',
    ];

    protected $casts = [
        'destination_lat'      => 'float',
        'destination_lng'      => 'float',
        'expected_delivery_at' => 'datetime',
        'actual_delivery_at'   => 'datetime',
        'delay_notified'       => 'boolean',
        'near_destination_at'   => 'datetime',
        'left_radius_at'        => 'datetime',
        'delivery_flag_sent'    => 'boolean',
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

    /**
     * Public-disk URL for the proof-of-delivery photo, or null if none.
     * Returned as a root-relative path so it resolves against whatever host
     * the admin is using (localhost / VPN) and is NOT exposed via the
     * Cloudflare tunnel, which only allows /track. Requires `php artisan storage:link`.
     */
    public function getDeliveryPhotoUrlAttribute(): ?string
    {
        return $this->delivery_photo_path
            ? '/storage/' . ltrim($this->delivery_photo_path, '/')
            : null;
    }

    public function isDelayed(): bool
    {
        if (in_array($this->status, ['delivered', 'cancelled'])) return false;
        $threshold = config('fleet.delay_threshold_minutes', 15);
        return now()->diffInMinutes($this->expected_delivery_at, false) < -$threshold;
    }

    /**
     * Check if the vehicle is currently inside the destination radius
     * using the latest stored GPS position.
     */
    public function isCurrentlyNearDestination(int $radiusMetres = 200): bool
    {
        $pos = $this->vehicle?->latestPosition;
        if (! $pos) return false;
        return $pos->distanceTo($this->destination_lat, $this->destination_lng) <= $radiusMetres;
    }

    public function isNearDestination(float $radiusMetres = 200): bool
    {
        $latest = $this->vehicle->latestPosition;
        if (! $latest) return false;

        return $latest->distanceTo($this->destination_lat, $this->destination_lng) <= $radiusMetres;
    }
}
