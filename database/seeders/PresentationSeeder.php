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
 * Demo/presentation data. Re-runnable: wipes fleet data (keeps the admin) and
 * seeds a rich cross-section — vehicles + drivers, shipments across every
 * status, live GPS, alerts, pending requests, and a client-facing timeline.
 *
 * ALL customer emails use the reserved @example.com domain (never deliverable),
 * so the live SMTP / delay sweep can never reach a real inbox.
 *
 *   php artisan db:seed --class=PresentationSeeder
 */
class PresentationSeeder extends Seeder
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
            ['name' => 'KL Central Hub', 'address' => 'Jalan Tun Razak, Kuala Lumpur', 'latitude' => 3.13900, 'longitude' => 101.68690, 'contact_name' => 'Ops Desk', 'contact_phone' => '+60 3-2000 1000'],
            ['name' => 'Shah Alam Depot', 'address' => 'Seksyen 15, Shah Alam, Selangor', 'latitude' => 3.07330, 'longitude' => 101.51850, 'contact_name' => 'Ops Desk', 'contact_phone' => '+60 3-5100 2000'],
            ['name' => 'Melaka Warehouse', 'address' => 'Ayer Keroh, Melaka', 'latitude' => 2.24170, 'longitude' => 102.28960, 'contact_name' => 'Ops Desk', 'contact_phone' => '+60 6-231 3000'],
        ];

        return array_map(fn ($o) => OriginLocation::create($o + ['is_active' => true]), $data);
    }

    private function seedVehiclesAndDrivers(): array
    {
        $rows = [
            ['plate' => 'WKL 1234', 'name' => 'Truck KL-01', 'mqtt' => 'esp32_demo_01', 'driver' => 'Aiman Rahman'],
            ['plate' => 'WSA 5678', 'name' => 'Truck Selangor-02', 'mqtt' => 'esp32_demo_02', 'driver' => 'Bala Krishnan'],
            ['plate' => 'WMK 9012', 'name' => 'Truck Melaka-03', 'mqtt' => 'esp32_demo_03', 'driver' => 'Chong Wei Ming'],
            ['plate' => 'WKL 3456', 'name' => 'Truck KL-04', 'mqtt' => 'esp32_demo_04', 'driver' => 'Devi Nair'],
            ['plate' => 'WPJ 7890', 'name' => 'Van PJ-05', 'mqtt' => 'esp32_demo_05', 'driver' => 'Farid Hassan'],
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
                'email' => "{$first}.driver".($i + 1).'@example.com',
                'phone' => '+60 12-'.str_pad((string) (3000000 + $i), 7, '0', STR_PAD_LEFT),
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
        // Malaysian destinations [address, lat, lng].
        $dest = [
            ['Mid Valley Megamall, Kuala Lumpur', 3.11770, 101.67740],
            ['Sunway Pyramid, Petaling Jaya', 3.07270, 101.60680],
            ['Suria KLCC, Kuala Lumpur', 3.15780, 101.71170],
            ['IOI City Mall, Putrajaya', 2.96860, 101.71200],
            ['Melaka Sentral, Melaka', 2.21000, 102.24050],
            ['AEON Bukit Tinggi, Klang', 3.04300, 101.43900],
            ['Setia City Mall, Shah Alam', 3.10300, 101.45700],
            ['Pavilion, Bukit Bintang, KL', 3.14900, 101.71300],
            ['Batu Caves, Selangor', 3.23790, 101.68400],
            ['UiTM Shah Alam, Selangor', 3.06700, 101.50000],
            ['Gurney Plaza, Penang', 5.43800, 100.30900],
            ['Johor Premium Outlets, Kulai', 1.62800, 103.53400],
        ];

        // Realistic names; emails are always @example.com (unrecognizable / safe).
        $clients = ['Nurul Izzah', 'Tan Sri Logistics', 'Kedai Runcit Maju', 'Siti Aminah', 'Rajesh Kumar',
            'Wong Enterprise', 'Farah Diyana', 'Zulkifli Trading', 'Priya Devi', 'Hafiz Marketing',
            'Lim Boutique', 'Ganesh Stores', 'Aisyah Crafts', 'Meng Hardware', 'Kavitha Textiles',
            'Iskandar Motors', 'Yusof Bakery', 'Chong Electronics'];

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
                $emailSlug = 'cust'.($n + 1);

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
            ['vehicle_id' => $vehicles[0]->id, 'shipment_id' => null, 'type' => 'overspeed', 'message' => "{$vehicles[0]->name} ({$vehicles[0]->plate_number}) exceeded speed limit: 128 km/h", 'meta' => ['speed' => 128], 'is_read' => false, 'triggered_at' => now()->subMinutes(12)],
            ['vehicle_id' => $vehicles[2]->id, 'shipment_id' => null, 'type' => 'offline', 'message' => "{$vehicles[2]->name} ({$vehicles[2]->plate_number}) has stopped sending GPS data for 6 minutes.", 'meta' => ['minutes_silent' => 6], 'is_read' => false, 'triggered_at' => now()->subMinutes(6)],
        ];
        if ($delayed) {
            $alerts[] = ['vehicle_id' => $delayed->vehicle_id, 'shipment_id' => $delayed->id, 'type' => 'delay', 'message' => "Shipment {$delayed->tracking_code} is delayed for client {$delayed->client_name}.", 'meta' => [], 'is_read' => false, 'triggered_at' => now()->subHours(2)];
        }
        if ($inTransit) {
            $alerts[] = ['vehicle_id' => $inTransit->vehicle_id, 'shipment_id' => $inTransit->id, 'type' => 'geofence', 'message' => "Driver of {$inTransit->tracking_code} left the delivery zone without confirming.", 'meta' => ['minutes_outside' => 7], 'is_read' => true, 'triggered_at' => now()->subDay()];
        }

        foreach ($alerts as $a) {
            Alert::create($a);
        }
    }

    private function seedTickets(): void
    {
        ShipmentTicket::create([
            'status' => 'pending',
            'client_name' => 'Rosli Enterprise',
            'client_email' => 'request1@example.com',
            'client_phone' => '+60 13-2223334',
            'destination_address' => 'Taman Melawati, Kuala Lumpur',
            'delivery_notes' => 'Call on arrival, unit above the pharmacy.',
            'delivery_tier' => 'express',
        ]);
        ShipmentTicket::create([
            'status' => 'pending',
            'client_name' => 'Salma Boutique',
            'client_email' => 'request2@example.com',
            'client_phone' => '+60 19-8887776',
            'destination_address' => 'Bandar Puteri, Puchong, Selangor',
            'delivery_notes' => 'Fragile — handle with care.',
            'delivery_tier' => 'standard',
        ]);
    }

    private function summary(): void
    {
        $sample = Shipment::whereIn('status', ['in_transit', 'delivered'])->pluck('tracking_code')->take(3)->implode(', ');
        $this->command->info('Presentation data seeded.');
        $this->command->info('  Vehicles: '.Vehicle::count().'  Drivers: '.User::where('role', 'driver')->count().'  Shipments: '.Shipment::count());
        $this->command->info('  Driver login: <first>.driver<n>@example.com  /  Password@123');
        $this->command->info('  Try tracking codes: '.$sample);
        $this->command->warn('  All customer emails are @example.com (non-deliverable).');
    }
}
