<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Support\FleetSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin-only operational settings. Values persist as overrides that
 * FleetSettings merges over config('fleet.*') at boot. Admin-gated via the
 * route group (role:admin); see routes/web.php.
 */
class SettingsController extends Controller
{
    public function index()
    {
        // Current effective values (already reflect any stored overrides,
        // applied at boot). Cast to int for the number inputs.
        $settings = [];
        foreach (array_keys(FleetSettings::KEYS) as $key) {
            $settings[$key] = (int) config("fleet.$key");
        }

        return view('fleet.settings', [
            'settings' => $settings,
            'meta' => FleetSettings::KEYS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (FleetSettings::KEYS as $key => $bounds) {
            $rules[$key] = "required|integer|min:{$bounds['min']}|max:{$bounds['max']}";
        }

        $data = $request->validate($rules);

        FleetSettings::save($data);

        ActivityLogger::logEvent(
            'settings_updated',
            'Operational thresholds updated by '.auth()->user()->name,
            'System', null, null,
            $data
        );

        return redirect()->route('fleet.settings')
            ->with('success', 'Settings saved. Daemon-side thresholds apply after the MQTT subscriber restarts.');
    }
}
