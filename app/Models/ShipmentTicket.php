<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * A customer's request for a NEW shipment, submitted anonymously from the
 * public /track home screen (no account). On submit the customer is shown a
 * short request_code to read to staff at the counter; a manager looks it up,
 * then approves (creating the real shipment via the prefilled form) or denies.
 * Immutable for the customer once submitted — there is deliberately no
 * client-facing update endpoint.
 *
 * pending → approved (storeShipment with ticket_id creates the real shipment)
 *         → denied   (review action; no email by design)
 */
class ShipmentTicket extends Model
{
    use Loggable, Notifiable;

    protected $fillable = [
        'request_code',
        'status',
        'client_name',
        'client_email',
        'client_phone',
        'destination_address',
        'delivery_notes',
        'requested_delivery_at',
        'reviewed_by',
        'reviewed_at',
        'created_shipment_id',
    ];

    protected $casts = [
        'requested_delivery_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /** Approval emails go straight to the requesting customer. */
    public function routeNotificationForMail(): string
    {
        return $this->client_email;
    }

    protected static function boot()
    {
        parent::boot();

        // Auto-generate the counter code on creation — short enough to read
        // aloud (6 chars), mirrors Shipment's tracking_code hook.
        static::creating(function (ShipmentTicket $ticket) {
            $ticket->request_code ??= strtoupper(Str::random(6));
        });
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function createdShipment()
    {
        return $this->belongsTo(Shipment::class, 'created_shipment_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
