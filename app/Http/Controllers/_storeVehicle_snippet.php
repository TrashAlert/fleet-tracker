<?php

// Add this storeVehicle method to your FleetController.php
// and add this route to routes/web.php:
//   Route::post('/vehicles', [FleetController::class, 'storeVehicle'])->name('fleet.vehicles.store');

// ── In FleetController.php, add: ──────────────────────────────────────

    public function storeVehicle(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'plate_number'  => 'required|string|max:20|unique:vehicles',
            'mqtt_client_id'=> 'required|string|max:100|unique:vehicles',
            'driver_name'   => 'nullable|string|max:255',
            'driver_phone'  => 'nullable|string|max:20',
        ]);

        Vehicle::create($data);

        return redirect()->route('fleet.vehicles')->with('success', 'Vehicle registered successfully.');
    }
