<?php

namespace App\Http\Controllers;

use App\Models\OriginLocation;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OriginLocationController extends Controller
{
    public function index()
    {
        $origins = OriginLocation::orderBy('is_active', 'desc')->orderBy('name')->get();
        return view('fleet.origins', compact('origins'));
    }

    /**
     * JSON list of active origins for the shipment create dropdown.
     */
    public function list(): JsonResponse
    {
        $origins = OriginLocation::active()
            ->orderBy('name')
            ->get(['id', 'name', 'address', 'latitude', 'longitude']);

        return response()->json($origins);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'address'       => 'required|string',
            'latitude'      => 'required|numeric|between:-90,90',
            'longitude'     => 'required|numeric|between:-180,180',
            'contact_name'  => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'notes'         => 'nullable|string',
            'is_active'     => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $origin = OriginLocation::create($data);

        ActivityLogger::logEvent(
            'origin_created',
            "Origin location [{$origin->name}] created",
            'OriginLocation', $origin->id, $origin->name,
            ['address' => $origin->address]
        );

        return response()->json(['ok' => true, 'origin' => $origin], 201);
    }

    public function update(Request $request, OriginLocation $origin): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'address'       => 'required|string',
            'latitude'      => 'required|numeric|between:-90,90',
            'longitude'     => 'required|numeric|between:-180,180',
            'contact_name'  => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'notes'         => 'nullable|string',
            'is_active'     => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $origin->update($data);

        ActivityLogger::logEvent(
            'origin_updated',
            "Origin location [{$origin->name}] updated",
            'OriginLocation', $origin->id, $origin->name
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(OriginLocation $origin): JsonResponse
    {
        $label = $origin->name;
        $id    = $origin->id;
        $origin->delete();

        ActivityLogger::logEvent(
            'origin_deleted',
            "Origin location [{$label}] deleted",
            'OriginLocation', $id, $label
        );

        return response()->json(['ok' => true]);
    }
}
