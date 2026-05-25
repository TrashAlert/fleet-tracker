<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientTrackingController extends Controller
{
    /**
     * Public tracking portal — no auth required.
     */
    public function index(Request $request)
    {
        $code     = strtoupper(trim($request->query('code', '')));
        $shipment = null;

        if ($code) {
            $shipment = Shipment::with([
                'vehicle.latestPosition',
            ])->where('tracking_code', $code)->first();
        }

        return view('client.track', compact('code', 'shipment'));
    }

    /**
     * JSON status endpoint — polled every 10s by the tracking page.
     */
    public function status(string $trackingCode): JsonResponse
    {
        $shipment = Shipment::with('vehicle.latestPosition')
            ->where('tracking_code', strtoupper($trackingCode))
            ->firstOrFail();

        $pos = $shipment->vehicle?->latestPosition;

        return response()->json([
            'status'   => $shipment->status,
            'vehicle'  => $pos ? [
                'latitude'    => $pos->latitude,
                'longitude'   => $pos->longitude,
                'speed_kmh'   => $pos->speed_kmh,
                'recorded_at' => $pos->recorded_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
