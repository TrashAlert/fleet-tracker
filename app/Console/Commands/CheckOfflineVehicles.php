<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\Vehicle;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOfflineVehicles extends Command
{
    protected $signature   = 'fleet:check-offline';
    protected $description = 'Create offline alerts for vehicles with started deliveries that have stopped sending GPS data';

    public function handle(): int
    {
        $threshold = config('fleet.offline_alert_threshold_seconds', 180);
        $cutoff    = now()->subSeconds($threshold);

        // Only vehicles that are active AND have a started delivery (in_transit / delayed)
        $vehicles = Vehicle::where('is_active', true)
            ->whereHas('shipments', fn($q) => $q->whereIn('status', ['in_transit', 'delayed']))
            ->with('latestPosition')
            ->get();

        $alerted = 0;

        foreach ($vehicles as $vehicle) {
            $pos = $vehicle->latestPosition;

            // No GPS data at all, or last point is recent enough — skip
            if (! $pos || $pos->recorded_at->gt($cutoff)) {
                continue;
            }

            // Alert once per offline episode:
            // if an offline alert already exists that is NEWER than the last GPS
            // point, we have already alerted for this episode. Fresh GPS data
            // (vehicle back online) makes this check pass again next time.
            $alreadyAlerted = Alert::where('vehicle_id', $vehicle->id)
                ->where('type', 'offline')
                ->where('triggered_at', '>=', $pos->recorded_at)
                ->exists();

            if ($alreadyAlerted) {
                continue;
            }

            $minutesSilent = (int) $pos->recorded_at->diffInMinutes(now());

            Alert::create([
                'vehicle_id'   => $vehicle->id,
                'shipment_id'  => $vehicle->activeShipment?->id,
                'type'         => 'offline',
                'message'      => "{$vehicle->name} ({$vehicle->plate_number}) has stopped sending GPS data for {$minutesSilent} minutes while on an active delivery.",
                'meta'         => [
                    'last_seen_at'   => $pos->recorded_at->toIso8601String(),
                    'minutes_silent' => $minutesSilent,
                    'last_lat'       => $pos->latitude,
                    'last_lng'       => $pos->longitude,
                ],
                'triggered_at' => now(),
            ]);

            ActivityLogger::logEvent(
                'vehicle_offline',
                "Vehicle {$vehicle->plate_number} went offline mid-delivery — no GPS for {$minutesSilent} minutes",
                'Vehicle', $vehicle->id, $vehicle->plate_number,
                ['minutes_silent' => $minutesSilent, 'last_lat' => $pos->latitude, 'last_lng' => $pos->longitude],
                ['causer_type' => 'system']
            );

            Log::warning("Offline alert: {$vehicle->plate_number} silent for {$minutesSilent} minutes.");
            $alerted++;
        }

        $this->info("Checked {$vehicles->count()} vehicles with started deliveries — {$alerted} offline alert(s) created.");
        return self::SUCCESS;
    }
}
