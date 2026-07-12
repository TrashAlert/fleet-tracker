<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Shipment;
use App\Notifications\DeliveryDelayedNotification;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for delay handling, shared by the MQTT ingestion
 * pipeline (per GPS packet) and the scheduled fleet:check-delays sweep (packet-
 * independent). Keeping both callers on this one method is deliberate: the two
 * paths must behave identically, or a shipment can end up "late" in one and not
 * the other.
 */
class ShipmentDelayService
{
    /**
     * Apply delay handling to a single shipment:
     *
     *   - a STARTED (in_transit) shipment past the delay threshold flips to
     *     `delayed` — regardless of whether the client was already alerted while
     *     it was still pending (this is the split that fixes the old bug where a
     *     shipment alerted-while-pending could never enter `delayed` after start);
     *   - the client is alerted + notified exactly ONCE, guarded by
     *     `delay_notified`.
     *
     * PENDING shipments intentionally keep their status so the driver can still
     * "start" them (no deadlock) — they only receive the alert/notification.
     *
     * Safe to call repeatedly (per packet or per sweep): it is a no-op once the
     * shipment is `delayed` and the client has been notified.
     *
     * @return bool whether anything changed (a status flip and/or the first alert)
     */
    public function process(Shipment $shipment): bool
    {
        if (! $shipment->isDelayed()) {
            return false;
        }

        $alreadyNotified = (bool) $shipment->delay_notified;

        $updates = [];
        // Flip a started shipment to delayed — independent of delay_notified.
        if ($shipment->status === 'in_transit') {
            $updates['status'] = 'delayed';
        }
        // Mark the one-time client alert as sent.
        if (! $alreadyNotified) {
            $updates['delay_notified'] = true;
        }

        if (empty($updates)) {
            return false; // already delayed and already notified — nothing to do
        }

        $shipment->update($updates);

        // Alert + notify the client only the first time it goes late.
        if ($alreadyNotified) {
            return true;
        }

        Alert::create([
            'vehicle_id' => $shipment->vehicle_id,
            'shipment_id' => $shipment->id,
            'type' => 'delay',
            'message' => "Shipment {$shipment->tracking_code} is delayed for client {$shipment->client_name}.",
            'meta' => ['expected_at' => $shipment->expected_delivery_at],
            'triggered_at' => now(),
        ]);

        $shipment->notify(new DeliveryDelayedNotification($shipment));
        Log::info("Delay alert sent for shipment {$shipment->tracking_code}.");

        ActivityLogger::logEvent(
            'shipment_delayed',
            "Shipment {$shipment->tracking_code} marked as delayed — client {$shipment->client_name} notified",
            'Shipment', $shipment->id, $shipment->tracking_code,
            ['expected_at' => $shipment->expected_delivery_at, 'client_email' => $shipment->client_email],
            ['causer_type' => 'system']
        );

        return true;
    }
}
