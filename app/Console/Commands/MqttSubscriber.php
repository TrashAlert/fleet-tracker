<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\GpsTelemetry;
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
        $shipment = $vehicle->activeShipment;
        if (! $shipment) return;

        // Mark delivered if within 200m of destination
        if ($shipment->isNearDestination(200)) {
            $shipment->update([
                'status'             => 'delivered',
                'actual_delivery_at' => now(),
            ]);
            Log::info("Shipment {$shipment->tracking_code} marked as delivered.");
            ActivityLogger::logEvent(
                'shipment_delivered',
                "Shipment {$shipment->tracking_code} auto-marked as delivered — vehicle within 200m of destination",
                'Shipment', $shipment->id, $shipment->tracking_code,
                ['lat' => $shipment->destination_lat, 'lng' => $shipment->destination_lng],
                ['causer_type' => 'system']
            );
            return;
        }

        // Notify client once when delay detected
        if ($shipment->isDelayed() && ! $shipment->delay_notified) {
            $shipment->update(['status' => 'delayed', 'delay_notified' => true]);

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
