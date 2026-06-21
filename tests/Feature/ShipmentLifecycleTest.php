<?php

namespace Tests\Feature;

use App\Models\GpsTelemetry;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the trickiest state machine in the app — the driver-driven
 * delivery lifecycle:
 *
 *     pending --(start)--> in_transit --(confirm within 200m)--> delivered
 *
 * and the guard rails around it:
 *   - only the assigned driver may act on a vehicle's shipment
 *   - a shipment can only be started from `pending`
 *   - only one delivery may be in progress per vehicle
 *   - confirmation requires: started, radius previously entered, AND the
 *     vehicle still inside the 200m zone (re-validated server-side).
 *
 * Class-based PHPUnit so it runs under both `php artisan test` and Pest.
 * Test data is built with explicit create() helpers (no factories needed).
 */
class ShipmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /** Destination used across tests — KL city centre. */
    private float $destLat = 3.140853;
    private float $destLng = 101.686855;

    /** Monotonic counter to keep unique columns (plate, mqtt id, email) distinct. */
    private int $seq = 0;

    private function makeVehicle(): Vehicle
    {
        $this->seq++;

        return Vehicle::create([
            'plate_number'   => 'TEST-' . $this->seq,
            'name'           => 'Test Truck ' . $this->seq,
            'mqtt_client_id' => 'ESP32_TEST_' . $this->seq,
            'is_active'      => true,
        ]);
    }

    private function makeDriver(Vehicle $vehicle): User
    {
        $this->seq++;

        return User::create([
            'name'       => 'Driver ' . $this->seq,
            'email'      => 'driver' . $this->seq . '@example.test',
            'password'   => 'password',          // hashed via the model cast
            'role'       => 'driver',
            'vehicle_id' => $vehicle->id,
            'is_active'  => true,
        ]);
    }

    private function makeShipment(Vehicle $vehicle, array $overrides = []): Shipment
    {
        return Shipment::create(array_merge([
            'vehicle_id'           => $vehicle->id,
            'client_name'          => 'Acme Co',
            'client_email'         => 'client@example.test',
            'origin_address'       => 'Warehouse A',
            'destination_address'  => 'Client Site',
            'destination_lat'      => $this->destLat,
            'destination_lng'      => $this->destLng,
            'expected_delivery_at' => now()->addHours(2),
            'status'               => 'pending',
        ], $overrides));
    }

    /** Record a "current" GPS fix for the vehicle (latest by recorded_at). */
    private function pushPosition(Vehicle $vehicle, float $lat, float $lng): void
    {
        GpsTelemetry::create([
            'vehicle_id'  => $vehicle->id,
            'latitude'    => $lat,
            'longitude'   => $lng,
            'speed_kmh'   => 0,
            'heading'     => 0,
            'satellites'  => 9,
            'hdop'        => 1.0,
            'recorded_at' => now(),
        ]);
    }

    // ───────────────────────────── START ─────────────────────────────

    public function test_driver_can_start_a_pending_shipment(): void
    {
        $vehicle  = $this->makeVehicle();
        $driver   = $this->makeDriver($vehicle);
        $shipment = $this->makeShipment($vehicle);

        $this->actingAs($driver)
            ->postJson(route('fleet.api.shipment.start', $shipment))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('in_transit', $shipment->fresh()->status);
    }

    public function test_cannot_start_a_shipment_that_is_not_pending(): void
    {
        $vehicle  = $this->makeVehicle();
        $driver   = $this->makeDriver($vehicle);
        $shipment = $this->makeShipment($vehicle, ['status' => 'in_transit']);

        $this->actingAs($driver)
            ->postJson(route('fleet.api.shipment.start', $shipment))
            ->assertStatus(422);

        $this->assertSame('in_transit', $shipment->fresh()->status);
    }

    public function test_only_one_delivery_can_be_in_progress_per_vehicle(): void
    {
        $vehicle = $this->makeVehicle();
        $driver  = $this->makeDriver($vehicle);
        $first   = $this->makeShipment($vehicle, ['status' => 'in_transit']);
        $second  = $this->makeShipment($vehicle); // pending

        $this->actingAs($driver)
            ->postJson(route('fleet.api.shipment.start', $second))
            ->assertStatus(422)
            ->assertJsonFragment([
                'error' => "Finish your current delivery ({$first->tracking_code}) before starting a new one.",
            ]);

        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_driver_cannot_start_another_vehicles_shipment(): void
    {
        $vehicleA  = $this->makeVehicle();
        $vehicleB  = $this->makeVehicle();
        $driverB   = $this->makeDriver($vehicleB);    // bound to vehicle B
        $shipmentA = $this->makeShipment($vehicleA);  // belongs to vehicle A

        $this->actingAs($driverB)
            ->postJson(route('fleet.api.shipment.start', $shipmentA))
            ->assertStatus(403);

        $this->assertSame('pending', $shipmentA->fresh()->status);
    }

    // ──────────────────────────── CONFIRM ────────────────────────────

    public function test_cannot_confirm_a_shipment_that_was_never_started(): void
    {
        $vehicle  = $this->makeVehicle();
        $driver   = $this->makeDriver($vehicle);
        $shipment = $this->makeShipment($vehicle); // pending
        $this->pushPosition($vehicle, $this->destLat, $this->destLng); // inside radius

        $this->actingAs($driver)
            ->postJson(route('fleet.api.shipment.confirm', $shipment))
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Start this delivery before confirming it.']);

        $this->assertSame('pending', $shipment->fresh()->status);
    }

    public function test_cannot_confirm_before_entering_the_radius(): void
    {
        $vehicle  = $this->makeVehicle();
        $driver   = $this->makeDriver($vehicle);
        // Started, but the vehicle never entered the radius (near_destination_at is null).
        $shipment = $this->makeShipment($vehicle, ['status' => 'in_transit']);
        $this->pushPosition($vehicle, $this->destLat, $this->destLng);

        $this->actingAs($driver)
            ->postJson(route('fleet.api.shipment.confirm', $shipment))
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Vehicle has not reached the destination radius yet.']);

        $this->assertSame('in_transit', $shipment->fresh()->status);
    }

    public function test_cannot_confirm_when_currently_outside_the_radius(): void
    {
        $vehicle  = $this->makeVehicle();
        $driver   = $this->makeDriver($vehicle);
        $shipment = $this->makeShipment($vehicle, [
            'status'              => 'in_transit',
            'near_destination_at' => now()->subMinutes(5), // entered the zone earlier...
        ]);
        // ...but the latest fix is ~1.7km away — outside the 200m zone.
        $this->pushPosition($vehicle, 3.155000, 101.700000);

        $this->actingAs($driver)
            ->postJson(route('fleet.api.shipment.confirm', $shipment))
            ->assertStatus(422)
            ->assertJsonFragment([
                'error' => 'You are no longer within the delivery zone. Move closer to the destination to confirm.',
            ]);

        $this->assertSame('in_transit', $shipment->fresh()->status);
    }

    // ─────────────────────── FULL HAPPY PATH ──────────────────────────

    public function test_full_lifecycle_start_then_confirm_within_radius_marks_delivered(): void
    {
        $vehicle  = $this->makeVehicle();
        $driver   = $this->makeDriver($vehicle);
        $shipment = $this->makeShipment($vehicle); // pending

        // 1) Driver starts the delivery.
        $this->actingAs($driver)
            ->postJson(route('fleet.api.shipment.start', $shipment))
            ->assertOk();
        $this->assertSame('in_transit', $shipment->fresh()->status);

        // 2) Vehicle reaches the destination radius (normally set by the MQTT
        //    subscriber) and the latest fix is inside the 200m zone.
        $shipment->refresh()->update(['near_destination_at' => now()]);
        $this->pushPosition($vehicle, $this->destLat, $this->destLng);

        // 3) Driver confirms while inside the zone.
        $this->actingAs($driver)
            ->postJson(route('fleet.api.shipment.confirm', $shipment))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $fresh = $shipment->fresh();
        $this->assertSame('delivered', $fresh->status);
        $this->assertNotNull($fresh->actual_delivery_at);
        $this->assertNull($fresh->left_radius_at);
        $this->assertFalse((bool) $fresh->delivery_flag_sent);
    }

    // ─────────────────── RADIUS DETECTION (polling) ───────────────────

    public function test_delivery_status_flags_when_vehicle_is_near_destination(): void
    {
        $vehicle  = $this->makeVehicle();
        $driver   = $this->makeDriver($vehicle);
        $shipment = $this->makeShipment($vehicle, ['status' => 'in_transit']);
        $this->pushPosition($vehicle, $this->destLat, $this->destLng); // inside radius

        $this->actingAs($driver)
            ->getJson(route('fleet.api.delivery.status'))
            ->assertOk()
            ->assertJsonPath('shipments.0.tracking_code', $shipment->tracking_code)
            ->assertJsonPath('shipments.0.near_destination', true);
    }
}
