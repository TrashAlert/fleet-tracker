<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Services\ShipmentDelayService;
use Illuminate\Console\Command;

/**
 * Packet-independent delay sweep, scheduled every minute (routes/console.php).
 *
 * Delay detection in the MQTT pipeline only runs when a GPS packet arrives, so a
 * shipment whose vehicle has gone silent could sit past its ETA forever without
 * ever flipping to `delayed`. This sweep closes that gap the same way
 * fleet:check-offline does for offline alerts — by re-evaluating active
 * shipments on a timer, through the shared ShipmentDelayService.
 */
class CheckDelayedShipments extends Command
{
    protected $signature = 'fleet:check-delays';

    protected $description = 'Flip overdue started shipments to delayed and alert clients, independent of GPS packets';

    public function handle(ShipmentDelayService $delays): int
    {
        // Only shipments that can still go delayed — delivered/cancelled are
        // excluded by isDelayed() anyway, but scoping keeps the sweep cheap.
        $shipments = Shipment::whereIn('status', ['pending', 'in_transit'])->get();

        $changed = 0;
        foreach ($shipments as $shipment) {
            if ($delays->process($shipment)) {
                $changed++;
            }
        }

        $this->info("Checked {$shipments->count()} active shipment(s) — {$changed} delay update(s).");

        return self::SUCCESS;
    }
}
