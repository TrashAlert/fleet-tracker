<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use Loggable;
    protected $fillable = [
        'vehicle_id',
        'shipment_id',
        'type',
        'message',
        'meta',
        'is_read',
        'triggered_at',
    ];

    protected $casts = [
        'meta'         => 'array',
        'is_read'      => 'boolean',
        'triggered_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
