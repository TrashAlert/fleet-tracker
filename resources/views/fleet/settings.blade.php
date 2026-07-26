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

    // Group metadata — icon + one-liner. Icons are inline SVG (no emojis), tinted
    // with --accent via the .set-group-icon tile.
    $groupMeta = [
        'Alerts' => [
            'desc' => 'Thresholds that raise operational alerts.',
            'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        ],
        'Tracking' => [
            'desc' => 'How live GPS staleness is interpreted.',
            'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4.93 19.07a10 10 0 0 1 0-14.14"/><path d="M7.76 16.24a6 6 0 0 1 0-8.48"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M16.24 7.76a6 6 0 0 1 0 8.48"/><circle cx="12" cy="12" r="2"/></svg>',
        ],
        'Delivery' => [
            'desc' => 'Rules applied across the delivery lifecycle.',
            'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
        ],
    ];
    $groups = ['Alerts', 'Tracking', 'Delivery'];
@endphp

<div class="set-page-head">
    <span class="set-head-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
    </span>
    <div>
        <div class="set-title">Settings</div>
        <div class="set-subtitle">Admin-only — operational thresholds applied across the fleet</div>
    </div>
</div>

@if(session('success'))
<div class="set-flash set-flash-ok">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5" style="flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="set-flash set-flash-err">
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

<form method="POST" action="{{ route('fleet.settings.update') }}" id="setForm" style="max-width:680px;">
    @csrf

    @foreach($groups as $group)
    <div class="card set-group">
        <div class="card-header set-group-head">
            <span class="set-group-icon">{!! $groupMeta[$group]['icon'] !!}</span>
            <div>
                <span class="card-title">{{ $group }}</span>
                <div class="set-group-desc">{{ $groupMeta[$group]['desc'] }}</div>
            </div>
        </div>
        <div class="set-group-body">
            @foreach($fields as $key => $f)
            @if($f['group'] === $group)
            <div class="set-row">
                <div class="set-row-info">
                    <label class="set-label" for="s_{{ $key }}">
                        {{ $f['label'] }}
                        @if($meta[$key]['daemon'])
                        <span class="set-daemon-badge" title="Takes effect after the MQTT subscriber restarts">restart to apply</span>
                        @endif
                    </label>
                    <div class="set-hint">{{ $f['hint'] }}</div>
                    <span class="set-range">Allowed {{ $meta[$key]['min'] }}–{{ $meta[$key]['max'] }} {{ $f['unit'] }}</span>
                    <div class="set-error" id="err_{{ $key }}"></div>
                </div>
                <div class="set-row-control">
                    <input id="s_{{ $key }}" class="set-input" type="number" name="{{ $key }}" inputmode="numeric"
                        value="{{ old($key, $settings[$key]) }}"
                        data-initial="{{ old($key, $settings[$key]) }}"
                        min="{{ $meta[$key]['min'] }}" max="{{ $meta[$key]['max'] }}" step="1" required>
                    <span class="set-unit">{{ $f['unit'] }}</span>
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </div>
    @endforeach

    <div class="set-actionbar">
        <div class="set-status" id="setStatus">
            <span class="set-status-dot"></span>
            <span id="setStatusText">No unsaved changes</span>
        </div>
        <div class="set-actionbar-btns">
            <button type="button" class="btn btn-ghost" id="setReset" disabled>Reset</button>
            <button type="submit" class="btn btn-primary" id="setSave" disabled>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Save Settings
            </button>
        </div>
    </div>
</form>

@endsection

@push('styles')
<style>
/* ── Settings page ── */
.set-page-head { display:flex; align-items:center; gap:12px; margin-bottom:20px; }
.set-head-icon,
.set-group-icon {
    flex-shrink:0; display:inline-flex; align-items:center; justify-content:center;
    border-radius:8px; background:color-mix(in srgb, var(--accent) 13%, transparent); color:var(--accent);
}
.set-head-icon { width:38px; height:38px; }
.set-head-icon svg { width:19px; height:19px; }
.set-title { font-family:var(--font-display); font-weight:700; font-size:18px; }
.set-subtitle { font-size:11px; color:var(--subtle); margin-top:3px; }

.set-flash {
    display:flex; gap:10px; align-items:flex-start;
    border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:12px;
}
.set-flash-ok  { align-items:center; background:color-mix(in srgb, var(--success) 12%, transparent); border:1px solid var(--success); color:var(--text); }
.set-flash-err { flex-direction:column; background:color-mix(in srgb, var(--danger) 10%, transparent); border:1px solid var(--danger); color:var(--danger); }

.set-group { margin-bottom:16px; }
.set-group-head { justify-content:flex-start; gap:12px; }
.set-group-icon { width:30px; height:30px; }
.set-group-icon svg { width:16px; height:16px; }
.set-group-desc { font-size:10px; color:var(--subtle); margin-top:2px; }
.set-group-body { padding:4px 18px 8px; }

.set-row {
    display:flex; align-items:flex-start; justify-content:space-between; gap:20px;
    padding:16px 0; border-bottom:1px solid var(--border);
}
.set-row:last-child { border-bottom:none; }
.set-row-info { min-width:0; }
.set-label {
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
    font-size:12px; font-weight:600; color:var(--text);
}
.set-daemon-badge {
    font-size:8px; letter-spacing:.06em; text-transform:uppercase; color:var(--warning);
    border:1px solid var(--warning); border-radius:4px; padding:1px 5px;
}
.set-hint { font-size:10px; color:var(--subtle); margin-top:4px; line-height:1.5; }
.set-range {
    display:inline-block; font-size:9px; font-family:var(--font-mono); color:var(--subtle);
    background:var(--bg); border:1px solid var(--border); border-radius:5px; padding:2px 7px; margin-top:6px;
}
.set-error { font-size:10px; color:var(--danger); margin-top:6px; }
.set-error:empty { display:none; }

.set-row-control { display:flex; align-items:center; gap:8px; flex-shrink:0; }
.set-input {
    width:110px; text-align:right; background:var(--bg); border:1px solid var(--border);
    border-radius:8px; padding:10px 12px; font-family:var(--font-mono); font-size:13px; color:var(--text);
    outline:none; transition:border-color .15s, box-shadow .15s;
}
.set-input:focus { border-color:var(--accent); }
.set-input.is-invalid { border-color:var(--danger); box-shadow:0 0 0 3px color-mix(in srgb, var(--danger) 18%, transparent); }
.set-unit { font-size:10px; color:var(--subtle); width:64px; }

.set-actionbar {
    display:flex; align-items:center; justify-content:space-between; gap:16px;
    background:var(--surface); border:1px solid var(--border); border-radius:10px;
    padding:13px 18px; position:sticky; bottom:16px; box-shadow:0 6px 20px rgba(0,0,0,.16);
}
.set-status { display:flex; align-items:center; gap:8px; font-size:11px; color:var(--subtle); transition:color .15s; }
.set-status-dot { width:8px; height:8px; border-radius:50%; background:var(--subtle); transition:background .15s, box-shadow .15s; }
.set-status.is-dirty { color:var(--warning); }
.set-status.is-dirty .set-status-dot { background:var(--warning); box-shadow:0 0 8px color-mix(in srgb, var(--warning) 60%, transparent); }
.set-actionbar-btns { display:flex; gap:8px; }
.set-actionbar .btn:disabled { opacity:.45; cursor:not-allowed; }
.set-actionbar .btn:disabled:hover { opacity:.45; }

@media (max-width: 560px) {
    .set-row { flex-direction:column; align-items:stretch; gap:10px; }
    .set-row-control { justify-content:flex-start; }
    .set-input { flex:1; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    const form       = document.getElementById('setForm');
    const inputs     = Array.from(form.querySelectorAll('.set-input'));
    const saveBtn    = document.getElementById('setSave');
    const resetBtn   = document.getElementById('setReset');
    const statusWrap = document.getElementById('setStatus');
    const statusText = document.getElementById('setStatusText');

    // Validate one field against its own min/max; write/clear the inline message.
    // Mirrors the server rule (required|integer|min|max) so Save stays disabled
    // until every value is submittable.
    function validate(inp) {
        const raw = inp.value.trim();
        const min = parseInt(inp.min, 10);
        const max = parseInt(inp.max, 10);
        const errEl = document.getElementById('err_' + inp.name);
        let msg = '';

        if (raw === '') {
            msg = 'Required.';
        } else if (!/^-?\d+$/.test(raw)) {
            msg = 'Whole number only.';
        } else {
            const v = parseInt(raw, 10);
            if (v < min) msg = `Minimum is ${min}.`;
            else if (v > max) msg = `Maximum is ${max}.`;
        }

        inp.classList.toggle('is-invalid', !!msg);
        if (errEl) errEl.textContent = msg;
        return !msg;
    }

    function refresh() {
        let dirty = false, valid = true;
        inputs.forEach(inp => {
            if (inp.value.trim() !== inp.dataset.initial) dirty = true;
            if (!validate(inp)) valid = false;
        });
        statusWrap.classList.toggle('is-dirty', dirty);
        statusText.textContent = dirty ? 'Unsaved changes' : 'No unsaved changes';
        saveBtn.disabled  = !dirty || !valid;
        resetBtn.disabled = !dirty;
    }

    inputs.forEach(inp => inp.addEventListener('input', refresh));

    resetBtn.addEventListener('click', () => {
        inputs.forEach(inp => {
            inp.value = inp.dataset.initial;
            inp.classList.remove('is-invalid');
            const errEl = document.getElementById('err_' + inp.name);
            if (errEl) errEl.textContent = '';
        });
        refresh();
    });

    refresh(); // initial paint: clean state, Save/Reset disabled (or flag a value the server bounced)
})();
</script>
@endpush
