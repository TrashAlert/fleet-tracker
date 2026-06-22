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
                'vehicle.driver',
            ])->where('tracking_code', $code)->first();
        }

        return view('client.track', compact('code', 'shipment'));
    }

    /**
     * JSON status endpoint — polled every 10s by the tracking page.
     */
    public function status(string $trackingCode): JsonResponse
    {
        $shipment = Shipment::with(['vehicle.latestPosition', 'vehicle.driver'])
            ->where('tracking_code', strtoupper($trackingCode))
            ->firstOrFail();

        // The truck's live location is shared only while the shipment is actually
        // moving (in_transit / delayed). Before the driver starts the trip
        // (pending) it stays hidden, and once the run is complete (delivered /
        // cancelled) it's hidden again. Suppressed here at the source so the
        // coordinates never reach the browser at all — not merely hidden in the UI.
        $locationHidden = in_array($shipment->status, ['pending', 'delivered', 'cancelled']);

        $pos = $locationHidden ? null : $shipment->vehicle?->latestPosition;

        return response()->json([
            'status'          => $shipment->status,
            'location_hidden' => $locationHidden,
            'delivered_at'    => $shipment->actual_delivery_at?->toIso8601String(),
            'vehicle'  => $pos ? [
                'latitude'     => $pos->latitude,
                'longitude'    => $pos->longitude,
                'speed_kmh'    => $pos->speed_kmh,
                'recorded_at'  => $pos->recorded_at?->toIso8601String(),
                'driver_name'  => $shipment->vehicle?->driver_name,
                'driver_phone' => $shipment->vehicle?->driver_phone,
            ] : null,
        ]);
    }
}
