<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientTrackingController extends Controller
{
    /**
     * Client tracking portal landing page.
     * Accepts tracking code via query string: /track?code=XXXXXXXXXX
     */
    public function index(Request $request)
    {
        $code     = $request->query('code');
        $shipment = null;

        if ($code) {
            $shipment = Shipment::with('vehicle.latestPosition')
                ->where('tracking_code', strtoupper($code))
                ->first();
        }

        return view('client.track', compact('shipment', 'code'));
    }

    /**
     * Live shipment status — polled by client dashboard every 10s.
     */
    public function status(string $trackingCode): JsonResponse
    {
        $shipment = Shipment::with('vehicle.latestPosition')
            ->where('tracking_code', strtoupper($trackingCode))
            ->firstOrFail();

        $position = $shipment->vehicle->latestPosition;

        return response()->json([
            'tracking_code'        => $shipment->tracking_code,
            'status'               => $shipment->status,
            'client_name'          => $shipment->client_name,
            'origin'               => $shipment->origin_address,
            'destination'          => $shipment->destination_address,
            'destination_lat'      => $shipment->destination_lat,
            'destination_lng'      => $shipment->destination_lng,
            'expected_delivery_at' => $shipment->expected_delivery_at?->toIso8601String(),
            'actual_delivery_at'   => $shipment->actual_delivery_at?->toIso8601String(),
            'is_delayed'           => $shipment->isDelayed(),
            'vehicle' => $position ? [
                'latitude'    => $position->latitude,
                'longitude'   => $position->longitude,
                'speed_kmh'   => $position->speed_kmh,
                'heading'     => $position->heading,
                'recorded_at' => $position->recorded_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
