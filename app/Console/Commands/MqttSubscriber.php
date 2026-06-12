<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\GpsTelemetry;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Notifications\DeliveryDelayedNotification;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;

/**
 * MqttSubscriber
 *
 * Long-running daemon managed by Supervisor.
 * Subscribes to fleet/+/telemetry and processes incoming GPS payloads.
 *
 * Expected MQTT topic: fleet/{mqtt_client_id}/telemetry
 *
 * Expected ESP32 JSON payload:
 *   { "lat": 3.123, "lng": 101.123, "speed": 65.3,
 *     "heading": 182.5, "satellites": 8, "hdop": 1.2, "ts": 1712345678 }
 */
class MqttSubscriber extends Command
{
    protected $signature   = 'mqtt:subscribe';
    protected $description = 'Subscribe to MQTT broker and process incoming GPS telemetry';

    public function handle(): int
    {
        $topicPrefix = config('fleet.mqtt_topic_prefix', 'fleet/');

        $this->info('Connecting to MQTT broker...');

        $mqtt = MQTT::connection();

        $mqtt->subscribe($topicPrefix . '+/telemetry', function (string $topic, string $payload) {
            $this->processTelemetry($topic, $payload);
        }, 1);

        $this->info("Subscribed to {$topicPrefix}+/telemetry — waiting for GPS data...");

        $mqtt->loop(true);   // blocks; Supervisor restarts on crash

        MQTT::disconnect();

        return self::SUCCESS;
    }

    private function processTelemetry(string $topic, string $payload): void
    {
        try {
            // Extract device client ID: fleet/{clientId}/telemetry
            $parts    = explode('/', $topic);
            $clientId = $parts[1] ?? null;

            if (! $clientId) {
                Log::warning("MQTT: could not parse client ID from topic: {$topic}");
                return;
            }

            $vehicle = Vehicle::where('mqtt_client_id', $clientId)->first();
            if (! $vehicle) {
                Log::warning("MQTT: unknown device ID: {$clientId}");
                ActivityLogger::logEvent(
                    'mqtt_unknown_device',
                    "MQTT packet received from unregistered device ID: {$clientId}",
                    'System', null, $clientId,
                    ['topic' => $topic],
                    ['causer_type' => 'mqtt', 'causer_label' => $clientId]
                );
                return;
            }

            $data = json_decode($payload, true);
            if (! $data || ! isset($data['lat'], $data['lng'])) {
                Log::warning("MQTT: invalid payload from {$clientId}: {$payload}");
                ActivityLogger::logEvent(
                    'mqtt_invalid_payload',
                    "Invalid GPS payload received from device: {$clientId}",
                    'Vehicle', $vehicle->id, $vehicle->plate_number,
                    ['raw_payload' => $payload],
                    ['causer_type' => 'mqtt', 'causer_label' => $clientId]
                );
                return;
            }

            $telemetry = GpsTelemetry::create([
                'vehicle_id'  => $vehicle->id,
                'latitude'    => $data['lat'],
                'longitude'   => $data['lng'],
                'speed_kmh'   => $data['speed']      ?? 0,
                'heading'     => $data['heading']    ?? 0,
                'satellites'  => $data['satellites'] ?? 0,
                'hdop'        => $data['hdop']       ?? null,
                'recorded_at' => $this->resolveTimestamp($data['ts'] ?? null),
            ]);

            $this->checkOverspeed($vehicle, $telemetry);
            $this->checkDeliveryStatus($vehicle, $telemetry);

            ActivityLogger::logEvent(
                'mqtt_telemetry_received',
                "GPS telemetry received from {$vehicle->name} ({$vehicle->plate_number}): {$telemetry->latitude}, {$telemetry->longitude} @ {$telemetry->speed_kmh} km/h",
                'Vehicle', $vehicle->id, $vehicle->plate_number,
                [
                    'lat'        => $telemetry->latitude,
                    'lng'        => $telemetry->longitude,
                    'speed_kmh'  => $telemetry->speed_kmh,
                    'heading'    => $telemetry->heading,
                    'satellites' => $telemetry->satellites,
                ],
                ['causer_type' => 'mqtt', 'causer_label' => $clientId]
            );

        } catch (\Throwable $e) {
            Log::error("MQTT processing error: {$e->getMessage()}", ['topic' => $topic]);
            ActivityLogger::logEvent(
                'mqtt_processing_error',
                "MQTT processing error on topic [{$topic}]: {$e->getMessage()}",
                null, null, null,
                ['topic' => $topic, 'error' => $e->getMessage()],
                ['causer_type' => 'system']
            );
        }
    }

    private function checkOverspeed(Vehicle $vehicle, GpsTelemetry $telemetry): void
    {
        $threshold = config('fleet.overspeed_threshold_kmh', 110);

        if ($telemetry->speed_kmh > $threshold) {
            Alert::create([
                'vehicle_id'   => $vehicle->id,
                'type'         => 'overspeed',
                'message'      => "{$vehicle->name} ({$vehicle->plate_number}) exceeded speed limit: {$telemetry->speed_kmh} km/h",
                'meta'         => [
                    'speed'     => $telemetry->speed_kmh,
                    'latitude'  => $telemetry->latitude,
                    'longitude' => $telemetry->longitude,
                ],
                'triggered_at' => now(),
            ]);

            Log::info("Overspeed alert: {$vehicle->plate_number} at {$telemetry->speed_kmh} km/h");

            ActivityLogger::logEvent(
                'overspeed_detected',
                "{$vehicle->name} ({$vehicle->plate_number}) exceeded speed limit at {$telemetry->speed_kmh} km/h (threshold: {$threshold} km/h)",
                'Vehicle', $vehicle->id, $vehicle->plate_number,
                ['speed_kmh' => $telemetry->speed_kmh, 'threshold' => $threshold, 'lat' => $telemetry->latitude, 'lng' => $telemetry->longitude],
                ['causer_type' => 'mqtt', 'causer_label' => $vehicle->mqtt_client_id]
            );
        }
    }

    private function checkDeliveryStatus(Vehicle $vehicle, GpsTelemetry $telemetry): void
    {
        $shipments = $vehicle->activeShipments;
        if ($shipments->isEmpty()) return;

        foreach ($shipments as $shipment) {
            // Radius monitoring only applies to started shipments.
            // Pending shipments are skipped until the driver acknowledges them,
            // but delay detection still runs for them (client should know either way).
            if (in_array($shipment->status, ['in_transit', 'delayed'])) {
                $this->checkShipmentRadius($vehicle, $telemetry, $shipment);
            } else {
                $this->checkShipmentDelay($vehicle, $shipment);
            }
        }
    }

    private function checkShipmentRadius(Vehicle $vehicle, GpsTelemetry $telemetry, Shipment $shipment): void
    {
        $distance     = $telemetry->distanceTo($shipment->destination_lat, $shipment->destination_lng);
        $withinRadius = $distance <= 200;

        // ── Vehicle is within destination radius ───────────────────────────
        if ($withinRadius) {
            // First time entering — record arrival time and notify driver
            if (! $shipment->near_destination_at) {
                $shipment->update([
                    'near_destination_at' => now(),
                    'left_radius_at'      => null,
                ]);
                Log::info("Shipment {$shipment->tracking_code}: vehicle entered destination radius.");
                ActivityLogger::logEvent(
                    'shipment_near_destination',
                    "Vehicle {$vehicle->plate_number} entered destination radius for shipment {$shipment->tracking_code} — awaiting driver confirmation",
                    'Shipment', $shipment->id, $shipment->tracking_code,
                    ['distance_metres' => round($distance)],
                    ['causer_type' => 'system']
                );
            }

            // Re-entered radius after leaving — clear the left timer
            if ($shipment->left_radius_at) {
                $shipment->update(['left_radius_at' => null]);
                Log::info("Shipment {$shipment->tracking_code}: vehicle re-entered radius.");
            }

            return;
        }

        // ── Vehicle is outside the radius ──────────────────────────────────
        if ($shipment->near_destination_at) {
            // Just left — record departure time
            if (! $shipment->left_radius_at) {
                $shipment->update(['left_radius_at' => now()]);
                Log::info("Shipment {$shipment->tracking_code}: vehicle left destination radius.");
                ActivityLogger::logEvent(
                    'shipment_left_radius',
                    "Vehicle {$vehicle->plate_number} left destination radius for shipment {$shipment->tracking_code} without confirming delivery",
                    'Shipment', $shipment->id, $shipment->tracking_code,
                    ['distance_metres' => round($distance)],
                    ['causer_type' => 'system']
                );
            }

            // Outside for more than 5 minutes — raise a flag
            $minutesOutside = $shipment->left_radius_at->diffInMinutes(now());
            if ($minutesOutside >= 5 && ! $shipment->delivery_flag_sent) {
                $shipment->update(['delivery_flag_sent' => true]);

                Alert::create([
                    'vehicle_id'   => $vehicle->id,
                    'shipment_id'  => $shipment->id,
                    'type'         => 'geofence',
                    'message'      => "Driver of {$vehicle->name} ({$vehicle->plate_number}) left the delivery zone for shipment {$shipment->tracking_code} without confirming delivery.",
                    'meta'         => ['minutes_outside' => $minutesOutside, 'tracking_code' => $shipment->tracking_code],
                    'triggered_at' => now(),
                ]);

                Log::warning("Shipment {$shipment->tracking_code}: delivery flag raised — driver left radius {$minutesOutside} min.");
                ActivityLogger::logEvent(
                    'delivery_flag_raised',
                    "FLAGGED: Driver of {$vehicle->plate_number} left delivery zone for {$minutesOutside} min without confirming shipment {$shipment->tracking_code}",
                    'Shipment', $shipment->id, $shipment->tracking_code,
                    ['minutes_outside' => $minutesOutside],
                    ['causer_type' => 'system']
                );
            }
        }

        // ── Delay check (independent of radius logic) ─────────────────────
        $this->checkShipmentDelay($vehicle, $shipment);
    }

    /**
     * Delay detection — runs for every active shipment including pending
     * (client should be notified their delivery is late regardless of
     * whether the driver has started it).
     */
    private function checkShipmentDelay(Vehicle $vehicle, Shipment $shipment): void
    {
        if (! $shipment->isDelayed() || $shipment->delay_notified) return;

        // Pending shipments keep their status so the driver can still "start"
        // them normally — they only get the alert + client notification.
        // Started shipments (in_transit) flip to delayed.
        if ($shipment->status === 'in_transit') {
            $shipment->update(['status' => 'delayed', 'delay_notified' => true]);
        } else {
            $shipment->update(['delay_notified' => true]);
        }

        Alert::create([
            'vehicle_id'   => $vehicle->id,
            'shipment_id'  => $shipment->id,
            'type'         => 'delay',
            'message'      => "Shipment {$shipment->tracking_code} is delayed for client {$shipment->client_name}.",
            'meta'         => ['expected_at' => $shipment->expected_delivery_at],
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
    }

    /**
     * Resolve a GPS timestamp safely.
     * The ESP32 sends millis()/1000 before a GPS time fix, which produces
     * timestamps near 1970. Anything before 2020-01-01 is treated as invalid
     * and falls back to now().
     */
    private function resolveTimestamp(mixed $ts): \Carbon\Carbon
    {
        if (empty($ts)) {
            return now();
        }

        // If ts is an ISO string (e.g. "2025-05-20T08:30:00Z") parse directly
        if (is_string($ts) && str_contains($ts, '-')) {
            try {
                $parsed = \Carbon\Carbon::parse($ts);
                if ($parsed->year >= 2020) {
                    return $parsed;
                }
            } catch (\Throwable) {}
            return now();
        }

        // Numeric Unix timestamp — reject anything before 2020-01-01 (1577836800)
        $unix = (int) $ts;
        if ($unix < 1577836800) {
            return now();
        }

        return \Carbon\Carbon::createFromTimestamp($unix);
    }
}
