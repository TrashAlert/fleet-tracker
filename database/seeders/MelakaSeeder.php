<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Alert;
use App\Models\GpsTelemetry;
use App\Models\OriginLocation;
use App\Models\Shipment;
use App\Models\ShipmentTicket;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Melaka demo dataset. Re-runnable: wipes fleet data (keeps the admin) and
 * seeds a Melaka-centric cross-section — vehicles + drivers on Melaka "M"
 * plates, shipments across every status routed to real Melaka / Ayer Keroh
 * landmarks, live GPS, alerts, pending requests, and client-facing timelines.
 *
 * Same shape and safety guarantees as PresentationSeeder — ALL customer emails
 * use the reserved @example.com domain (never deliverable), so the live SMTP /
 * delay sweep can never reach a real inbox. Like PresentationSeeder it is NOT
 * wired into DatabaseSeeder; invoke it explicitly (and note it resets the same
 * fleet tables, so running it replaces any other demo dataset):
 *
 *   php artisan db:seed --class=MelakaSeeder
 */
class MelakaSeeder extends Seeder
{
    public function run(): void
    {
        $this->wipe();

        $origins = $this->seedOrigins();
        $vehicles = $this->seedVehiclesAndDrivers();
        $this->seedShipments($vehicles, $origins);
        $this->seedTickets();

        $this->summary();
    }

    // ── Reset (keep the admin) ──────────────────────────────────────────────
    private function wipe(): void
    {
        Schema::disableForeignKeyConstraints();
        GpsTelemetry::truncate();
        Alert::truncate();
        ShipmentTicket::truncate();
        Shipment::truncate();
        OriginLocation::truncate();
        ActivityLog::truncate();
        User::where('role', '!=', 'admin')->delete();
        Vehicle::truncate();
        Schema::enableForeignKeyConstraints();
    }

    private function seedOrigins(): array
    {
        $data = [
            ['name' => 'Ayer Keroh Distribution Hub', 'address' => 'Ayer Keroh, Melaka', 'latitude' => 2.24170, 'longitude' => 102.28960, 'contact_name' => 'Ops Desk', 'contact_phone' => '+60 6-231 1000'],
            ['name' => 'Melaka Sentral Depot', 'address' => 'Hang Tuah Jaya, Melaka', 'latitude' => 2.22100, 'longitude' => 102.24050, 'contact_name' => 'Ops Desk', 'contact_phone' => '+60 6-288 2000'],
            ['name' => 'Batu Berendam Warehouse', 'address' => 'Batu Berendam, Melaka', 'latitude' => 2.26200, 'longitude' => 102.25000, 'contact_name' => 'Ops Desk', 'contact_phone' => '+60 6-317 3000'],
        ];

        return array_map(fn ($o) => OriginLocation::create($o + ['is_active' => true]), $data);
    }

    private function seedVehiclesAndDrivers(): array
    {
        // "M" is the Melaka state registration prefix.
        $rows = [
            ['plate' => 'M 1234 A', 'name' => 'Truck Melaka-01', 'mqtt' => 'esp32_mlk_01', 'driver' => 'Zulhelmi Ariff'],
            ['plate' => 'M 2345 B', 'name' => 'Van Bandar Hilir-02', 'mqtt' => 'esp32_mlk_02', 'driver' => 'Puteri Sarah'],
            ['plate' => 'M 3456 C', 'name' => 'Truck Ayer Keroh-03', 'mqtt' => 'esp32_mlk_03', 'driver' => 'Gopal Menon'],
            ['plate' => 'M 4567 D', 'name' => 'Van Melaka-04', 'mqtt' => 'esp32_mlk_04', 'driver' => 'Hafizah Omar'],
            ['plate' => 'M 5678 E', 'name' => 'Truck Klebang-05', 'mqtt' => 'esp32_mlk_05', 'driver' => 'Lim Jia Xin'],
        ];

        $vehicles = [];
        foreach ($rows as $i => $r) {
            $vehicle = Vehicle::create([
                'plate_number' => $r['plate'],
                'name' => $r['name'],
                'mqtt_client_id' => $r['mqtt'],
                'is_active' => true,
            ]);

            $first = strtolower(explode(' ', $r['driver'])[0]);
            User::create([
                'name' => $r['driver'],
                'email' => "{$first}.mlk".($i + 1).'@example.com',
                'phone' => '+60 14-'.str_pad((string) (5000000 + $i), 7, '0', STR_PAD_LEFT),
                'password' => Hash::make('Password@123'),
                'role' => 'driver',
                'vehicle_id' => $vehicle->id,
                'is_active' => true,
            ]);

            $vehicles[] = $vehicle;
        }

        return $vehicles;
    }

    private function seedShipments(array $vehicles, array $origins): void
    {
        // Melaka / Ayer Keroh destinations [address, lat, lng].
        $dest = [
            ['A Famosa (Porta de Santiago), Bandar Hilir, Melaka', 2.19190, 102.25030],
            ['Dutch Square (Stadthuys), Melaka', 2.19430, 102.24900],
            ['Jonker Walk, Chinatown, Melaka', 2.19550, 102.24700],
            ['Dataran Pahlawan Megamall, Melaka', 2.18780, 102.24900],
            ['Mahkota Parade, Bandar Hilir, Melaka', 2.18890, 102.24970],
            ['Melaka Straits Mosque, Pulau Melaka', 2.18610, 102.24780],
            ['Menara Taming Sari, Melaka', 2.19000, 102.24890],
            ['Melaka Sentral, Hang Tuah Jaya', 2.22100, 102.24050],
            ['AEON Bandaraya Melaka', 2.26260, 102.24470],
            ['Multimedia University, Bukit Beruang, Melaka', 2.27600, 102.29200],
            ['Klebang Beach, Melaka', 2.22000, 102.19000],
            ['Melaka Zoo, Ayer Keroh', 2.27570, 102.29960],
        ];

        // Realistic names; emails are always @example.com (unrecognizable / safe).
        $clients = ['Baba Nyonya Kopitiam', 'Jonker Antiques', 'Siti Zubaidah', 'Muthu Traders', 'Hang Tuah Enterprise',
            'Cheng Ho Trading', 'Aminah Salleh', 'Ravindran Stores', 'Melaka Kraf', 'Wong Sin Kee',
            'Faridah Bakery', 'Cendol Corner', 'Rahman Hardware', 'Devi Textiles', 'Bandar Motors',
            'Portuguese Settlement Cafe', 'Hilir Enterprise', 'Nyonya Collections'];

        // status => how many
        $plan = [
            'pending' => 4,
            'in_transit' => 4,
            'delayed' => 2,
            'delivered' => 5,
            'returned' => 2,
            'cancelled' => 1,
        ];
        $tiers = array_keys(config('fleet.delivery_tiers', ['standard' => [], 'express' => []]));

        $n = 0;
        foreach ($plan as $status => $count) {
            for ($k = 0; $k < $count; $k++) {
                $vehicle = $vehicles[$n % count($vehicles)];
                $origin = $origins[$n % count($origins)];
                [$addr, $lat, $lng] = $dest[$n % count($dest)];
                $client = $clients[$n % count($clients)];
                $tier = $tiers[$n % count($tiers)];
                $emailSlug = 'mlkcust'.($n + 1);

                $attrs = [
                    'vehicle_id' => $vehicle->id,
                    'client_name' => $client,
                    'client_email' => "{$emailSlug}@example.com",
                    'client_phone' => '+60 1'.rand(1, 9).'-'.rand(1000000, 9999999),
                    'origin_address' => $origin->address,
                    'destination_address' => $addr,
                    'destination_lat' => $lat,
                    'destination_lng' => $lng,
                    'delivery_tier' => $tier,
                    'status' => $status,
                ];

                $this->applyStatusFields($attrs, $status, $tier);
                $createdAt = $attrs['_created_at'] ?? now()->subDays(2);
                unset($attrs['_created_at']); // helper key, not a column

                $shipment = Shipment::create($attrs);
                $this->backdate($shipment, $createdAt);
                $shipment->refresh(); // pick up the backdated created_at for the timeline base

                if ($status === 'in_transit') {
                    $this->seedLiveTrack($vehicle, $lat, $lng, $k === 0); // first in_transit is "near destination"
                }
                $this->seedTimeline($shipment, $status);

                $n++;
            }
        }

        $this->seedAlerts($vehicles);
    }

    /** Fill status-specific fields (dates, delivery/attempt state). */
    private function applyStatusFields(array &$a, string $status, string $tier): void
    {
        $days = (int) (config("fleet.delivery_tiers.$tier.days") ?? 3);

        switch ($status) {
            case 'pending':
                $a['_created_at'] = now()->subHours(rand(2, 20));
                $a['expected_delivery_at'] = now()->addDays($days);
                break;
            case 'in_transit':
                $a['_created_at'] = now()->subDays(1);
                $a['expected_delivery_at'] = now()->addHours(rand(3, 30));
                break;
            case 'delayed':
                // Past due + already notified, so the delay sweep never re-emails.
                $a['_created_at'] = now()->subDays($days + 2);
                $a['expected_delivery_at'] = now()->subHours(rand(3, 24));
                $a['delay_notified'] = true;
                break;
            case 'delivered':
                $a['_created_at'] = now()->subDays(rand(2, 6));
                $a['expected_delivery_at'] = now()->subDays(rand(1, 3));
                $a['actual_delivery_at'] = now()->subDays(rand(0, 2))->subHours(rand(1, 10));
                break;
            case 'returned':
                $a['_created_at'] = now()->subDays(rand(5, 9));
                $a['expected_delivery_at'] = now()->subDays(rand(2, 5));
                $a['delivery_attempts'] = (int) config('fleet.max_delivery_attempts', 3);
                $a['last_attempt_at'] = now()->subDays(1);
                $a['last_attempt_reason'] = 'recipient_absent';
                $a['delay_notified'] = true;
                break;
            case 'cancelled':
                $a['_created_at'] = now()->subDays(rand(2, 5));
                $a['expected_delivery_at'] = now()->subDays(1);
                break;
        }
    }

    private function backdate(Shipment $s, $createdAt): void
    {
        DB::table('shipments')->where('id', $s->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    /** Recent GPS so the live map + manifest/ETA light up for in-transit trucks. */
    private function seedLiveTrack(Vehicle $vehicle, float $destLat, float $destLng, bool $near): void
    {
        // A short trail approaching the destination over the last ~30 minutes.
        $points = 6;
        for ($i = 0; $i < $points; $i++) {
            $t = $i / ($points - 1);                 // 0 → 1 (start → arrival)
            // "near" trucks finish within the 200 m zone; others stop ~2 km out.
            $offset = $near ? (1 - $t) * 0.02 : (1 - $t) * 0.02 + 0.018;
            GpsTelemetry::create([
                'vehicle_id' => $vehicle->id,
                'latitude' => $destLat + $offset,
                'longitude' => $destLng + $offset * 0.6,
                'speed_kmh' => $i === $points - 1 ? ($near ? 0 : 32) : rand(30, 70),
                'heading' => 210,
                'satellites' => rand(7, 12),
                'hdop' => 0.9,
                'recorded_at' => now()->subMinutes((int) round((1 - $t) * 30)),
            ]);
        }
    }

    /** Client-facing timeline (whitelisted actions, backdated). */
    private function seedTimeline(Shipment $s, string $status): void
    {
        $base = $s->created_at ?? now()->subDays(2);
        $log = function (string $action, $at, array $meta = []) use ($s) {
            ActivityLog::create([
                'causer_type' => 'system',
                'causer_label' => 'seed',
                'subject_type' => 'Shipment',
                'subject_id' => $s->id,
                'subject_label' => $s->tracking_code,
                'action' => $action,
                'description' => "Seed timeline event for {$s->tracking_code}",
                'new_values' => $meta ?: null,
                'logged_at' => $at,
            ]);
        };

        $log('shipment_created', $base);

        if (in_array($status, ['in_transit', 'delivered', 'returned'], true)) {
            $log('shipment_started', (clone $base)->addHours(2));
        }
        if ($status === 'delivered') {
            $log('shipment_near_destination', $s->actual_delivery_at?->copy()->subMinutes(20) ?? now());
            $log('shipment_delivered', $s->actual_delivery_at ?? now());
        }
        if ($status === 'returned') {
            $max = (int) config('fleet.max_delivery_attempts', 3);
            for ($i = 1; $i <= $max; $i++) {
                $log('shipment_delivery_failed', (clone $base)->addDays($i),
                    ['attempt' => $i, 'max' => $max, 'reason' => 'recipient_absent', 'reason_label' => 'Recipient unavailable']);
            }
            $log('shipment_returned', (clone $base)->addDays($max + 1), ['attempts' => $max]);
        }
    }

    private function seedAlerts(array $vehicles): void
    {
        $inTransit = Shipment::where('status', 'in_transit')->first();
        $delayed = Shipment::where('status', 'delayed')->first();

        $alerts = [
            ['vehicle_id' => $vehicles[0]->id, 'shipment_id' => null, 'type' => 'overspeed', 'message' => "{$vehicles[0]->name} ({$vehicles[0]->plate_number}) exceeded speed limit: 124 km/h", 'meta' => ['speed' => 124], 'is_read' => false, 'triggered_at' => now()->subMinutes(11)],
            ['vehicle_id' => $vehicles[2]->id, 'shipment_id' => null, 'type' => 'offline', 'message' => "{$vehicles[2]->name} ({$vehicles[2]->plate_number}) has stopped sending GPS data for 7 minutes.", 'meta' => ['minutes_silent' => 7], 'is_read' => false, 'triggered_at' => now()->subMinutes(7)],
        ];
        if ($delayed) {
            $alerts[] = ['vehicle_id' => $delayed->vehicle_id, 'shipment_id' => $delayed->id, 'type' => 'delay', 'message' => "Shipment {$delayed->tracking_code} is delayed for client {$delayed->client_name}.", 'meta' => [], 'is_read' => false, 'triggered_at' => now()->subHours(2)];
        }
        if ($inTransit) {
            $alerts[] = ['vehicle_id' => $inTransit->vehicle_id, 'shipment_id' => $inTransit->id, 'type' => 'geofence', 'message' => "Driver of {$inTransit->tracking_code} left the delivery zone without confirming.", 'meta' => ['minutes_outside' => 8], 'is_read' => true, 'triggered_at' => now()->subDay()];
        }

        foreach ($alerts as $a) {
            Alert::create($a);
        }
    }

    private function seedTickets(): void
    {
        ShipmentTicket::create([
            'status' => 'pending',
            'client_name' => 'Klebang Coconut Shake',
            'client_email' => 'mlkrequest1@example.com',
            'client_phone' => '+60 13-6667778',
            'destination_address' => 'Klebang, Melaka',
            'delivery_notes' => 'Roadside stall, call on arrival.',
            'delivery_tier' => 'express',
        ]);
        ShipmentTicket::create([
            'status' => 'pending',
            'client_name' => 'Ayer Keroh Resort Supplies',
            'client_email' => 'mlkrequest2@example.com',
            'client_phone' => '+60 19-5556667',
            'destination_address' => 'Ayer Keroh, Melaka',
            'delivery_notes' => 'Deliver to the loading bay at the rear.',
            'delivery_tier' => 'standard',
        ]);
    }

    private function summary(): void
    {
        $sample = Shipment::whereIn('status', ['in_transit', 'delivered'])->pluck('tracking_code')->take(3)->implode(', ');
        $this->command->info('Melaka data seeded.');
        $this->command->info('  Vehicles: '.Vehicle::count().'  Drivers: '.User::where('role', 'driver')->count().'  Shipments: '.Shipment::count());
        $this->command->info('  Driver login: <first>.mlk<n>@example.com  /  Password@123');
        $this->command->info('  Try tracking codes: '.$sample);
        $this->command->warn('  All customer emails are @example.com (non-deliverable).');
    }
}
