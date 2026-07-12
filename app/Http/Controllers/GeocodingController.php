<?php

namespace App\Http\Controllers;

use App\Services\NominatimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-side proxy to the self-hosted Nominatim instance (NominatimService).
 * The browser never talks to Nominatim directly — it is bound to localhost and
 * exposed only through these admin/manager, throttled endpoints.
 *
 * NominatimService is a soft dependency: it returns null when the container is
 * down. We translate that into `available: false` so the UI can fall back to the
 * existing manual pin / lat-lng inputs instead of surfacing an error.
 */
class GeocodingController extends Controller
{
    /**
     * Address -> ranked coordinate matches. GET /fleet/api/geocode?q=...
     */
    public function search(Request $request, NominatimService $nominatim): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|max:255',
        ]);

        $results = $nominatim->search($data['q']);

        // null = Nominatim unreachable; [] = reachable but no matches / too short.
        if ($results === null) {
            return response()->json(['available' => false, 'results' => []]);
        }

        return response()->json([
            'available' => true,
            'results' => array_map(fn ($r) => [
                'label' => $r['display_name'],
                'lat' => $r['lat'],
                'lng' => $r['lng'],
                'type' => $r['type'],
            ], $results),
        ]);
    }

    /**
     * Coordinates -> a human-readable address. GET /fleet/api/geocode/reverse?lat=&lng=
     */
    public function reverse(Request $request, NominatimService $nominatim): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $result = $nominatim->reverse((float) $data['lat'], (float) $data['lng']);

        return response()->json([
            'available' => $result !== null,
            'address' => $result['display_name'] ?? null,
        ]);
    }
}
