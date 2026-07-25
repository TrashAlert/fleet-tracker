@extends('layouts.app')
@section('title', 'Settings')

@section('content')

@php
    $fields = [
        'overspeed_threshold_kmh'         => ['label' => 'Overspeed Threshold', 'unit' => 'km/h', 'group' => 'Alerts', 'hint' => 'Speed above which an overspeed alert is raised.'],
        'delay_threshold_minutes'         => ['label' => 'Delay Threshold', 'unit' => 'minutes', 'group' => 'Alerts', 'hint' => 'Minutes past the expected time before a shipment is marked delayed.'],
        'offline_alert_threshold_seconds' => ['label' => 'Offline Alert After', 'unit' => 'seconds', 'group' => 'Alerts', 'hint' => 'GPS silence before an offline alert is raised for a vehicle on an active delivery.'],
        'gps_stale_timeout_seconds'       => ['label' => 'Online/Offline Pill Timeout', 'unit' => 'seconds', 'group' => 'Tracking', 'hint' => 'GPS silence before the dashboard shows a vehicle as offline (cosmetic only).'],
        'max_active_shipments'            => ['label' => 'Max Active Shipments', 'unit' => 'per vehicle', 'group' => 'Delivery', 'hint' => 'Cap on pending + in-transit + delayed shipments per vehicle, enforced at creation.'],
        'max_delivery_attempts'           => ['label' => 'Max Delivery Attempts', 'unit' => 'attempts', 'group' => 'Delivery', 'hint' => 'Failed attempts before a shipment is returned to sender.'],
        'geofence_radius_metres'          => ['label' => 'Geofence Radius', 'unit' => 'metres', 'group' => 'Delivery', 'hint' => 'Arrival / confirm zone around each destination.'],
    ];
    $groups = ['Alerts', 'Tracking', 'Delivery'];
@endphp

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <div style="font-family:var(--font-display); font-weight:700; font-size:18px;">Settings</div>
        <div style="font-size:11px; color:var(--subtle); margin-top:3px;">Admin-only — operational thresholds applied across the fleet</div>
    </div>
</div>

@if(session('success'))
<div style="display:flex; gap:10px; align-items:center; background:color-mix(in srgb, var(--success) 12%, transparent); border:1px solid var(--success); border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:12px; color:var(--text);">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5" style="flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div style="background:color-mix(in srgb, var(--danger) 10%, transparent); border:1px solid var(--danger); border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:12px; color:var(--danger);">
    <div style="display:flex; gap:8px; align-items:center; font-weight:700; margin-bottom:6px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Some values were out of range
    </div>
    <ul style="margin-left:20px;">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('fleet.settings.update') }}" style="max-width:680px;">
    @csrf

    @foreach($groups as $group)
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><span class="card-title">{{ $group }}</span></div>
        <div style="padding:6px 20px 16px;">
            @foreach($fields as $key => $f)
            @if($f['group'] === $group)
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:20px; padding:14px 0; border-bottom:1px solid var(--border);">
                <div style="min-width:0;">
                    <label for="s_{{ $key }}" style="display:block; font-size:12px; font-weight:600; color:var(--text);">
                        {{ $f['label'] }}
                        @if($meta[$key]['daemon'])
                        <span title="Takes effect after the MQTT subscriber restarts" style="margin-left:6px; font-size:8px; letter-spacing:.06em; text-transform:uppercase; color:var(--warning); border:1px solid var(--warning); border-radius:4px; padding:1px 5px; vertical-align:middle;">restart to apply</span>
                        @endif
                    </label>
                    <div style="font-size:10px; color:var(--subtle); margin-top:3px; line-height:1.5;">{{ $f['hint'] }}</div>
                    <div style="font-size:9px; color:var(--muted); margin-top:2px;">Allowed: {{ $meta[$key]['min'] }}–{{ $meta[$key]['max'] }} {{ $f['unit'] }}</div>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
                    <input id="s_{{ $key }}" type="number" name="{{ $key }}" inputmode="numeric"
                        value="{{ old($key, $settings[$key]) }}"
                        min="{{ $meta[$key]['min'] }}" max="{{ $meta[$key]['max'] }}" step="1" required
                        style="width:110px; text-align:right; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 12px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
                    <span style="font-size:10px; color:var(--subtle); width:64px;">{{ $f['unit'] }}</span>
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </div>
    @endforeach

    <button type="submit" class="btn btn-primary">Save Settings</button>
</form>

@endsection