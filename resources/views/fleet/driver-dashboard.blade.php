@extends('layouts.app')
@section('title', 'My Dashboard')

@section('content')

@php $user = auth()->user(); @endphp

{{-- ── No vehicle assigned warning ─────────────────────────────────────── --}}
@if(!$user->vehicle_id || $vehicles->isEmpty())
<div style="
    background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.25);
    border-radius:10px; padding:20px 24px; margin-bottom:24px;
    display:flex; align-items:flex-start; gap:14px;
">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2" style="flex-shrink:0; margin-top:1px;">
        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
    </svg>
    <div>
        <div style="font-weight:600; color:var(--warning); font-size:13px; margin-bottom:4px;">No vehicle assigned</div>
        <div style="font-size:12px; color:var(--subtle);">
            Your account is not linked to a vehicle yet. Contact your fleet manager to get assigned.
        </div>
    </div>
</div>
@else

@php $vehicle = $vehicles->first(); @endphp

{{-- ── Vehicle header strip: glanceable plate + status + big speed ──────── --}}
<div class="drv-header">
    <div style="min-width:0;">
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <span style="font-family:var(--font-display); font-size:22px; font-weight:800; color:var(--accent);">
                {{ $vehicle->plate_number }}
            </span>
            <span id="stat-status">
                <span class="pill {{ $vehicle->isOffline() ? 'pill-offline' : 'pill-online' }}">
                    {{ $vehicle->isOffline() ? 'Offline' : 'Online' }}
                </span>
            </span>
        </div>
        <div style="font-size:11px; color:var(--subtle); margin-top:3px;">{{ $vehicle->name }}</div>
    </div>
    <span style="flex:1"></span>
    <div style="text-align:right;">
        <div style="font-family:var(--font-display); font-size:26px; font-weight:800; line-height:1;">
            <span id="stat-speed">{{ $vehicle->latestPosition ? number_format($vehicle->latestPosition->speed_kmh, 1) : '—' }}</span>
        </div>
        <div style="font-size:10px; color:var(--subtle); margin-top:3px;">km/h</div>
    </div>
</div>

{{-- ── Utility chips: alerts / keep-screen-on / connection ─────────────── --}}
<div class="drv-chips">
    <button type="button" class="chip" onclick="document.getElementById('alerts-card').scrollIntoView({behavior:'smooth', block:'start'})">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        <span class="chip-val mono" id="stat-alerts">{{ $unreadAlerts->count() }}</span>
        <span class="chip-label">alerts</span>
    </button>
    <button type="button" class="chip" id="wake-btn" style="display:none;" onclick="toggleWakeLock()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
        <span class="chip-label" id="wake-label">Keep screen on</span>
    </button>
    <span style="flex:1"></span>
    <span class="chip" id="conn-pill" style="display:none; border-color:rgba(245,158,11,0.45);">
        <span class="chip-label" style="color:var(--warning);">Reconnecting…</span>
    </span>
    <span style="font-size:10px; color:var(--accent);" id="map-updated">—</span>
</div>

{{-- ── Delivery Confirmation Banners (one per nearby shipment, injected by JS) ── --}}
<div id="delivery-banners" style="margin-bottom:0;"></div>

{{-- ── Deliveries / Map / Alerts (DOM in mobile order; desktop uses grid areas) ── --}}
<div class="drv-grid">

    {{-- My Deliveries (sorted nearest first by JS) --}}
    <div class="card" style="grid-area:deliveries;">
        <div class="card-header">
            <span class="card-title">My Deliveries</span>
            <span style="font-size:10px; color:var(--subtle);" id="deliveries-count">
                {{ $vehicle->activeShipments->count() }} active
            </span>
        </div>
        <div id="deliveries-list" style="padding:0 18px; max-height:340px; overflow-y:auto;">
            @forelse($vehicle->activeShipments as $s)
            @php
                $pos      = $vehicle->latestPosition;
                $distance = $pos ? round($pos->distanceTo($s->destination_lat, $s->destination_lng)) : null;
                $overdue  = $s->expected_delivery_at && $s->expected_delivery_at->isPast();
                $isStarted = $s->status === 'in_transit'; // delayed = late, never started
            @endphp
            <div class="delivery-item {{ $isStarted ? 'delivery-started' : '' }}" id="delivery-{{ $s->id }}"
                 data-distance="{{ $distance ?? 999999 }}" data-status="{{ $s->status }}"
                 onclick="focusDelivery({{ $s->id }})">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                    <div style="min-width:0;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-family:var(--font-display); font-size:13px; font-weight:700; color:var(--accent);">
                                {{ $s->tracking_code }}
                            </span>
                            @if($s->status === 'in_transit')
                                <span class="pill pill-transit" style="font-size:8px;">In Progress</span>
                            @elseif($s->status === 'delayed')
                                <span class="pill pill-delayed" style="font-size:8px;">Delayed</span>
                            @endif
                        </div>
                        <div style="font-size:11px; margin-top:3px;">{{ $s->client_name }}</div>
                        <div style="font-size:10px; color:var(--subtle); margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            {{ $s->destination_address }}
                        </div>
                        @if($s->delivery_notes)
                        <div style="display:flex; gap:5px; align-items:flex-start; font-size:10px; color:var(--accent); margin-top:4px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:1px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>
                            <span style="white-space:pre-wrap;">{{ $s->delivery_notes }}</span>
                        </div>
                        @endif
                        <div style="font-size:10px; margin-top:4px; {{ $overdue ? 'color:var(--danger);' : 'color:var(--subtle);' }}">
                            Due: {{ $s->expected_delivery_at?->format('d M, H:i') ?? '—' }}
                            @if($overdue) (overdue) @endif
                        </div>
                    </div>
                    <div style="text-align:right; flex-shrink:0;">
                        <div class="delivery-distance mono" style="font-size:12px; font-weight:600;">
                            {{ $distance !== null ? ($distance >= 1000 ? number_format($distance/1000, 1).' km' : $distance.' m') : '—' }}
                        </div>
                        <div class="delivery-distance-label" style="font-size:9px; color:var(--subtle); margin-top:2px;">away</div>
                    </div>
                </div>

                <div style="display:flex; gap:8px; margin-top:10px;" onclick="event.stopPropagation()">
                    @if($s->client_phone)
                    {{-- One-tap call — drivers coordinate handover by phone --}}
                    <a href="tel:{{ preg_replace('/[^+0-9]/', '', $s->client_phone) }}" class="drv-btn" style="flex:0 0 auto; padding-left:14px; padding-right:14px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                        Call
                    </a>
                    @endif
                    @if($s->destination_lat && $s->destination_lng)
                    <a href="https://waze.com/ul?ll={{ $s->destination_lat }},{{ $s->destination_lng }}&navigate=yes"
                       target="_blank" rel="noopener" class="drv-btn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>
                        Waze
                    </a>
                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $s->destination_lat }},{{ $s->destination_lng }}&travelmode=driving"
                       target="_blank" rel="noopener" class="drv-btn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        Maps
                    </a>
                    @endif
                </div>

                @if(in_array($s->status, ['pending', 'delayed']))
                {{-- delayed = late but never started — still startable --}}
                <div onclick="event.stopPropagation()">
                    <button class="start-delivery-btn drv-btn" style="margin-top:10px; width:100%;"
                        onclick="startDelivery({{ $s->id }}, '{{ $s->tracking_code }}')">
                        Start Delivery
                    </button>
                </div>
                @endif
            </div>
            @empty
            <div style="text-align:center; padding:24px 0; color:var(--subtle); font-size:12px;">
                No active deliveries assigned
            </div>
            @endforelse
        </div>
    </div>

    {{-- Live Map --}}
    <div class="card drv-map-card" style="grid-area:map;">
        <div class="card-header">
            <span class="card-title">My Location</span>
            <span style="font-size:10px; color:var(--subtle);" id="last-seen">
                Last seen: {{ $vehicle->latestPosition ? $vehicle->latestPosition->recorded_at->diffForHumans() : 'never' }}
            </span>
        </div>
        {{-- z-index:0 traps Leaflet's controls below the nav drawer / bottom nav --}}
        <div style="flex:1; position:relative; z-index:0;">
            <div id="fleet-map" style="position:absolute; inset:0; height:100%;"></div>
        </div>
    </div>

    {{-- Alerts --}}
    <div class="card" style="grid-area:alerts;" id="alerts-card">
        <div class="card-header">
            <span class="card-title">My Alerts</span>
            <span style="font-size:10px; color:var(--subtle);" id="alert-count-label">
                {{ $unreadAlerts->count() }} unread
            </span>
        </div>
        <div id="alerts-panel" style="padding:0 18px; max-height:260px; overflow-y:auto;">
            @forelse($unreadAlerts as $alert)
            <div class="alert-item" id="alert-{{ $alert->id }}">
                <div class="alert-icon {{ $alert->type }}">
                    @if($alert->type === 'overspeed')
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    @elseif($alert->type === 'delay')
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    @else
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--subtle)" stroke-width="2.5"><path d="M1 6s4-2 11-2 11 2 11 2"/><path d="M5 10s2.5-1 7-1 7 1 7 1"/><path d="M9 14s1.5-.5 3-.5 3 .5 3 .5"/><line x1="12" y1="18" x2="12" y2="18.5" stroke-width="3"/></svg>
                    @endif
                </div>
                <div style="flex:1; min-width:0;">
                    <div class="alert-msg">{{ $alert->message }}</div>
                    <div class="alert-time">{{ $alert->triggered_at->diffForHumans() }}</div>
                </div>
                <button onclick="dismissAlert({{ $alert->id }})"
                    style="background:none;border:none;color:var(--subtle);cursor:pointer;font-size:18px;line-height:1;flex-shrink:0;padding:0 4px;">
                    &times;
                </button>
            </div>
            @empty
            <div id="no-alerts-msg" style="padding:32px 0; text-align:center; color:var(--subtle); font-size:12px;">
                No unread alerts
            </div>
            @endforelse
        </div>
    </div>

</div>

{{-- ── Vehicle details strip ────────────────────────────────────────────── --}}
<div class="card" style="margin-top:14px;">
    <div class="card-header">
        <span class="card-title">Vehicle Details</span>
    </div>
    <div class="drv-details">
        @foreach([
            ['label' => 'Plate',      'value' => $vehicle->plate_number,                                                       'id' => ''],
            ['label' => 'Satellites', 'value' => $vehicle->latestPosition?->satellites ?? '—',                                 'id' => 'det-sats'],
            ['label' => 'HDOP',       'value' => $vehicle->latestPosition ? number_format($vehicle->latestPosition->hdop, 2) . '' : '—', 'id' => 'det-hdop'],
            ['label' => 'Heading',    'value' => $vehicle->latestPosition ? number_format($vehicle->latestPosition->heading, 1) . ' deg' : '—', 'id' => 'det-heading'],
            ['label' => 'Coordinates','value' => $vehicle->latestPosition ? number_format($vehicle->latestPosition->latitude, 5).', '.number_format($vehicle->latestPosition->longitude, 5) : '—', 'id' => 'det-coords'],
        ] as $d)
        <div class="drv-detail-cell">
            <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">{{ $d['label'] }}</div>
            <div class="mono" style="font-size:13px;" id="{{ $d['id'] }}">{{ $d['value'] }}</div>
        </div>
        @endforeach
    </div>
</div>

@endif
@endsection

@push('styles')
<style>
/* ── Map theme (same as every page; light-mode override included) ── */
.leaflet-tile-pane    { filter: brightness(0.65) saturate(0.7) hue-rotate(185deg); }
.leaflet-container    { background: #0a0b0e; }
html[data-theme="light"] .leaflet-tile-pane { filter: none; }
html[data-theme="light"] .leaflet-container { background: #dce3e8; }
.vehicle-popup        { font-family: var(--font-mono); font-size: 12px; }
.vehicle-popup strong { color: var(--accent); }
.alert-icon.overspeed svg { stroke: var(--danger); }
.alert-icon.delay     svg { stroke: var(--warning); }

/* ── Header strip + chips ── */
.drv-header {
    display:flex; align-items:center; gap:14px;
    background:var(--surface); border:1px solid var(--border); border-radius:10px;
    padding:14px 18px; margin-bottom:10px;
}
.drv-chips { display:flex; align-items:center; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
.chip {
    display:flex; align-items:center; gap:7px;
    border:1px solid var(--border); border-radius:8px;
    background:var(--surface); padding:7px 11px;
    font-family:var(--font-display); color:var(--text); cursor:pointer;
}
.chip-val   { font-size:14px; font-weight:500; }
.chip-label { font-size:11px; color:var(--subtle); }
.chip.chip-on { border-color:var(--success); }
.chip.chip-on .chip-label { color:var(--success); }

/* ── Layout: DOM is mobile order; desktop arranges via grid areas ── */
.drv-grid { display:flex; flex-direction:column; gap:14px; }
@media (min-width: 900px) {
    .drv-grid {
        display:grid;
        grid-template-columns: 1.6fr 1fr;
        grid-template-areas: "map deliveries" "map alerts";
        align-items:stretch;
    }
    .drv-map-card { min-height:480px; display:flex; flex-direction:column; }
}
@media (max-width: 899px) {
    .drv-map-card { min-height:300px; display:flex; flex-direction:column; }
    #fleet-map { min-height:300px; }
    #deliveries-list { max-height:380px !important; }
    .delivery-item { padding:16px 0 !important; }
}

/* ── Deliveries ── */
.delivery-item { padding:13px 0; border-bottom:1px solid var(--border); cursor:pointer; }
.delivery-started {
    background:rgba(0,229,255,0.04);
    margin:0 -18px; padding-left:18px !important; padding-right:18px !important;
    border-left:3px solid var(--accent);
}
html[data-theme="light"] .delivery-started { background:rgba(0,119,182,0.05); }

.drv-btn {
    flex:1; display:inline-flex; align-items:center; justify-content:center; gap:6px;
    background:var(--muted); color:var(--text); text-decoration:none;
    border:1px solid var(--border); border-radius:6px;
    padding:10px; min-height:38px;
    font-family:var(--font-mono); font-size:11px; cursor:pointer; transition:all .15s;
}
.drv-btn:hover { border-color:var(--accent); color:var(--accent); }
@media (max-width: 899px) {
    .drv-btn { min-height:44px; font-size:12px; }  /* proper touch targets */
}

/* ── Confirmation banners on phones: stack button below text, full width ── */
@media (max-width: 768px) {
    #delivery-banners > div > div {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    #delivery-banners button, #delivery-banners label {
        width: 100%;
        justify-content: center;
        padding: 14px !important;
        font-size: 14px !important;
    }
}

/* ── Vehicle details strip ── */
.drv-details { display:grid; grid-template-columns:repeat(5,1fr); border-top:1px solid var(--border); }
.drv-detail-cell { padding:16px 18px; border-right:1px solid var(--border); }
.drv-detail-cell:last-child { border-right:none; }
@media (max-width: 768px) {
    .drv-details { grid-template-columns:repeat(2,1fr); }
    .drv-detail-cell { border-right:none; border-bottom:1px solid var(--border); }
}
</style>
@endpush

@push('scripts')
@if(isset($vehicle))
<script>
const CSRF       = document.querySelector('meta[name="csrf-token"]').content;
const VEHICLE_ID = {{ $vehicle->id }};

// ── Map ───────────────────────────────────────────────────────────────────
// Polling always runs, even before the first GPS fix — the map just starts
// at the default centre until the vehicle reports in.
const map = L.map('fleet-map', { zoomControl: true, attributionControl: false })
    .setView(
        @if($vehicle->latestPosition)
            [{{ $vehicle->latestPosition->latitude }}, {{ $vehicle->latestPosition->longitude }}], 14
        @else
            [3.140853, 101.686855], 11
        @endif
    );

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

function makeIcon(isOffline) {
    return L.divIcon({
        className: '',
        html: `<div style="
            width:14px; height:14px; border-radius:50%;
            background:${isOffline ? '#ef4444' : '#00e5ff'};
            border:2px solid ${isOffline ? '#7f1d1d' : '#00454f'};
            box-shadow:0 0 8px ${isOffline ? '#ef444488' : '#00e5ff88'};
        "></div>`,
        iconSize: [14, 14], iconAnchor: [7, 7],
    });
}

let vehicleMarker = null;
@if($vehicle->latestPosition)
vehicleMarker = L.marker(
    [{{ $vehicle->latestPosition->latitude }}, {{ $vehicle->latestPosition->longitude }}],
    { icon: makeIcon({{ $vehicle->isOffline() ? 'true' : 'false' }}) }
).addTo(map).bindPopup('');
@endif

// Destination markers — one per active delivery, keyed for tap-to-focus
const destIcon = L.divIcon({
    className: '',
    html: `<div style="width:12px;height:12px;border-radius:50%;background:#ef4444;border:2px solid #7f1d1d;box-shadow:0 0 6px #ef444488;"></div>`,
    iconSize: [12, 12], iconAnchor: [6, 6],
});
const destMarkers = {};
@foreach($vehicle->activeShipments as $s)
@if($s->destination_lat && $s->destination_lng)
destMarkers[{{ $s->id }}] = L.marker([{{ $s->destination_lat }}, {{ $s->destination_lng }}], { icon: destIcon })
    .addTo(map)
    .bindPopup('<b>{{ $s->tracking_code }}</b><br>{{ $s->client_name }}<br>{{ $s->destination_address }}');
@endif
@endforeach

// Tap a delivery card → fly the map to its destination
function focusDelivery(id) {
    const m = destMarkers[id];
    if (!m) return;
    document.querySelector('.drv-map-card')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    map.flyTo(m.getLatLng(), Math.max(map.getZoom(), 15), { duration: 0.6 });
    m.openPopup();
}

// ── Connection indicator (cellular drops are normal for drivers) ──────────
let connFails = 0;
function setConn(ok) {
    connFails = ok ? 0 : connFails + 1;
    // Only surface after two consecutive failures — one blip is noise.
    document.getElementById('conn-pill').style.display = connFails >= 2 ? 'flex' : 'none';
}

// ── Live polling ──────────────────────────────────────────────────────────
async function fetchLivePosition() {
    try {
        const res  = await fetch('{{ route("fleet.api.live") }}', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        setConn(true);
        const v    = data.find(v => v.id === VEHICLE_ID);
        if (!v || !v.latitude) return;

        const latlng = [v.latitude, v.longitude];
        if (vehicleMarker) {
            vehicleMarker.setLatLng(latlng).setIcon(makeIcon(v.is_offline));
        } else {
            // First-ever fix: create the marker and bring the map to it
            vehicleMarker = L.marker(latlng, { icon: makeIcon(v.is_offline) }).addTo(map).bindPopup('');
            map.setView(latlng, 14);
        }
        vehicleMarker.getPopup().setContent(`
            <div class="vehicle-popup">
                <strong>${v.name}</strong><br>
                Speed: ${(v.speed_kmh ?? 0).toFixed(1)} km/h<br>
                ${v.is_offline ? '<span style="color:#ef4444">OFFLINE</span>' : '<span style="color:#22c55e">LIVE</span>'}
            </div>
        `);

        map.panTo(latlng);

        // Header strip
        document.getElementById('stat-speed').textContent  = (v.speed_kmh ?? 0).toFixed(1);
        document.getElementById('stat-status').innerHTML   = `<span class="pill ${v.is_offline ? 'pill-offline' : 'pill-online'}">${v.is_offline ? 'Offline' : 'Online'}</span>`;
        document.getElementById('map-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();

        // Details strip
        if (document.getElementById('det-sats'))    document.getElementById('det-sats').textContent    = v.satellites ?? '—';
        if (document.getElementById('det-hdop'))    document.getElementById('det-hdop').textContent    = v.hdop ? parseFloat(v.hdop).toFixed(2) : '—';
        if (document.getElementById('det-heading')) document.getElementById('det-heading').textContent = v.heading ? parseFloat(v.heading).toFixed(1) + ' deg' : '—';
        if (document.getElementById('det-coords'))  document.getElementById('det-coords').textContent  = v.latitude ? v.latitude.toFixed(5) + ', ' + v.longitude.toFixed(5) : '—';
        if (document.getElementById('last-seen'))   document.getElementById('last-seen').textContent   = 'Last seen: ' + (v.recorded_at ? timeAgo(v.recorded_at) : 'never');

    } catch(e) { setConn(false); console.error('Live fetch error:', e); }
}

function timeAgo(iso) {
    const diff = Math.floor((Date.now() - new Date(iso)) / 1000);
    if (diff < 60)   return diff + 's ago';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    return Math.floor(diff / 3600) + 'h ago';
}

// ── Alerts ────────────────────────────────────────────────────────────────
function alertIconSvg(type) {
    if (type === 'overspeed') return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`;
    if (type === 'delay')     return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`;
    return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--subtle)" stroke-width="2.5"><path d="M1 6s4-2 11-2 11 2 11 2"/><path d="M5 10s2.5-1 7-1 7 1 7 1"/><path d="M9 14s1.5-.5 3-.5 3 .5 3 .5"/><line x1="12" y1="18" x2="12" y2="18.5" stroke-width="3"/></svg>`;
}

async function dismissAlert(id) {
    try {
        await fetch(`/fleet/api/alerts/${id}/read`, {
            method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF }
        });
    } catch(e) {}
    document.getElementById(`alert-${id}`)?.remove();
    updateAlertCount();
}

function updateAlertCount() {
    const count = document.querySelectorAll('.alert-item').length;
    document.getElementById('stat-alerts').textContent       = count;
    document.getElementById('alert-count-label').textContent = count + ' unread';
    if (count === 0 && !document.getElementById('no-alerts-msg')) {
        document.getElementById('alerts-panel').innerHTML =
            `<div id="no-alerts-msg" style="padding:32px 0;text-align:center;color:var(--subtle);font-size:12px;">No unread alerts</div>`;
    }
}

async function fetchNewAlerts() {
    try {
        const res  = await fetch('{{ route("fleet.api.alerts") }}', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        setConn(true);
        const panel = document.getElementById('alerts-panel');

        data.forEach(alert => {
            if (document.getElementById(`alert-${alert.id}`)) return;
            document.getElementById('no-alerts-msg')?.remove();
            const div = document.createElement('div');
            div.className = 'alert-item';
            div.id = `alert-${alert.id}`;
            div.innerHTML = `
                <div class="alert-icon ${alert.type}">${alertIconSvg(alert.type)}</div>
                <div style="flex:1;min-width:0;">
                    <div class="alert-msg">${alert.message}</div>
                    <div class="alert-time">${alert.triggered_at}</div>
                </div>
                <button onclick="dismissAlert(${alert.id})"
                    style="background:none;border:none;color:var(--subtle);cursor:pointer;font-size:18px;line-height:1;flex-shrink:0;padding:0 4px;">
                    &times;
                </button>
            `;
            panel.prepend(div);
        });
        updateAlertCount();
    } catch(e) { setConn(false); console.error('Alert fetch error:', e); }
}

// ── Delivery status polling (multi-shipment) ─────────────────────────────
async function fetchDeliveryStatus() {
    try {
        const res  = await fetch('{{ route("fleet.api.delivery.status") }}', {
            headers: { 'Accept': 'application/json' }
        });
        const data      = await res.json();
        setConn(true);
        const shipments = data.shipments || [];
        const container = document.getElementById('delivery-banners');

        // ── Update banners — one per shipment that is near OR recently left ──
        const bannersNeeded = shipments.filter(s => s.near_destination || s.left_radius_at);
        const neededIds     = bannersNeeded.map(s => 'banner-' + s.shipment_id);

        // Remove banners for shipments no longer relevant
        [...container.children].forEach(el => {
            if (!neededIds.includes(el.id)) el.remove();
        });

        bannersNeeded.forEach(s => {
            let banner = document.getElementById('banner-' + s.shipment_id);
            let isNewNearBanner = false;

            if (!banner) {
                banner = document.createElement('div');
                banner.id = 'banner-' + s.shipment_id;
                banner.style.marginBottom = '14px';
                container.appendChild(banner);
                if (s.near_destination) isNewNearBanner = true;
            } else if (s.near_destination && banner.dataset.mode !== 'near') {
                // Was the amber "left zone" banner, now back in the zone
                isNewNearBanner = true;
            }

            // Skip re-render if this banner is mid-confirmation
            if (banner.dataset.confirming === '1') return;

            if (s.near_destination) {
                banner.dataset.mode = 'near';

                // Buzz once when we ARRIVE in the confirm zone (spec task #5)
                if (isNewNearBanner && 'vibrate' in navigator) {
                    try { navigator.vibrate([200, 100, 200]); } catch(e) {}
                }

                // ── Inside the zone — green confirm banner ──
                banner.innerHTML = `
                    <div style="background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.35);
                        border-radius:10px; padding:16px 20px;
                        display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap;">
                        <div style="display:flex; align-items:center; gap:12px; min-width:0;">
                            <div style="width:34px; height:34px; border-radius:8px; background:rgba(34,197,94,0.15);
                                display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
                                </svg>
                            </div>
                            <div style="min-width:0;">
                                <div style="font-weight:700; font-size:13px; color:var(--success);">
                                    Near destination — ${s.tracking_code}
                                </div>
                                <div style="font-size:11px; color:var(--subtle); margin-top:2px;">
                                    ${s.client_name} · ${s.distance_metres}m away · confirm when goods are handed over
                                </div>
                            </div>
                        </div>
                        <label id="confirm-btn-${s.shipment_id}"
                            style="background:var(--success); color:#000; border:none; border-radius:7px;
                                padding:9px 18px; font-family:var(--font-display); font-weight:700;
                                font-size:12px; cursor:pointer; white-space:nowrap; flex-shrink:0;
                                display:inline-flex; align-items:center; gap:7px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                            Take Photo &amp; Confirm
                            <input type="file" accept="image/*" capture="environment"
                                onchange="confirmDelivery(${s.shipment_id}, this)" style="display:none;">
                        </label>
                    </div>`;
            } else if (s.left_radius_at) {
                banner.dataset.mode = 'left';

                // ── Left the zone — amber warning with countdown ──
                const leftAt      = new Date(s.left_radius_at);
                const minsOutside = Math.floor((Date.now() - leftAt) / 60000);
                const minsLeft    = Math.max(0, 5 - minsOutside);

                const warningText = s.delivery_flag_sent || minsLeft === 0
                    ? '<span style="color:var(--danger);">Flag raised</span> — contact your manager'
                    : `<span style="color:var(--danger);">${minsLeft} min</span> left before a flag is raised`;

                banner.innerHTML = `
                    <div style="background:rgba(245,158,11,0.07); border:1px solid rgba(245,158,11,0.3);
                        border-radius:10px; padding:16px 20px;
                        display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap;">
                        <div style="display:flex; align-items:center; gap:12px; min-width:0;">
                            <div style="width:34px; height:34px; border-radius:8px; background:rgba(245,158,11,0.13);
                                display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2.5">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                            </div>
                            <div style="min-width:0;">
                                <div style="font-weight:700; font-size:13px; color:var(--warning);">
                                    Left delivery zone — ${s.tracking_code}
                                </div>
                                <div style="font-size:11px; color:var(--subtle); margin-top:2px;">
                                    ${s.distance_metres}m from destination · ${warningText}
                                </div>
                            </div>
                        </div>
                        <button disabled
                            style="background:var(--muted); color:var(--subtle); border:none; border-radius:7px;
                                padding:9px 18px; font-family:var(--font-display); font-weight:700;
                                font-size:12px; cursor:not-allowed; white-space:nowrap; flex-shrink:0; opacity:.5;">
                            Return to zone to confirm
                        </button>
                    </div>`;
            }
        });

        // ── Update deliveries list: road distance + ETA, ordered by the server ──
        shipments.forEach(s => {
            const item = document.getElementById('delivery-' + s.shipment_id);
            if (!item) return;

            const distEl  = item.querySelector('.delivery-distance');
            const labelEl = item.querySelector('.delivery-distance-label');

            if (s.route_distance_metres != null) {
                // Road-based: distance by route + drive-time ETA.
                if (distEl)  distEl.textContent  = (s.route_distance_metres / 1000).toFixed(1) + ' km';
                if (labelEl) labelEl.textContent = (s.route_eta_minutes != null)
                    ? ('~' + s.route_eta_minutes + ' min by road')
                    : 'by road';
                item.dataset.distance = s.route_distance_metres;
            } else {
                // OSRM unavailable — fall back to straight-line distance.
                if (distEl)  distEl.textContent  = s.distance_metres >= 1000
                    ? (s.distance_metres / 1000).toFixed(1) + ' km'
                    : s.distance_metres + ' m';
                if (labelEl) labelEl.textContent = 'away';
                item.dataset.distance = s.distance_metres;
            }
        });

        // The server returns shipments nearest-first by road; mirror that order.
        const list = document.getElementById('deliveries-list');
        if (list) {
            shipments.forEach(s => {
                const item = document.getElementById('delivery-' + s.shipment_id);
                if (item) list.appendChild(item);
            });
        }

        document.getElementById('deliveries-count').textContent = shipments.length + ' active';

    } catch(e) { setConn(false); console.error('Delivery status error:', e); }
}

// ── Start delivery (driver acknowledgement, one at a time) ────────────────
async function startDelivery(shipmentId, trackingCode) {
    if (!confirm(`Start delivery ${trackingCode}? Make sure you have reviewed the shipment details and loaded the goods.`)) return;

    try {
        const res  = await fetch(`/fleet/api/shipments/${shipmentId}/start-delivery`, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
        });
        const json = await res.json();

        if (!res.ok) {
            alert(json.error || 'Could not start delivery.');
            return;
        }

        // Reload to reflect new status, highlight, and banner eligibility
        location.reload();

    } catch(e) {
        alert('Network error — please try again.');
    }
}

// Disable all Start buttons if any delivery is already in progress
// (only in_transit counts — delayed means late-but-never-started)
function updateStartButtons() {
    const inProgress = document.querySelector('.delivery-item[data-status="in_transit"]');
    document.querySelectorAll('.start-delivery-btn').forEach(btn => {
        if (inProgress) {
            btn.disabled            = true;
            btn.style.opacity       = '.4';
            btn.style.cursor        = 'not-allowed';
            btn.textContent         = 'Finish current delivery first';
        }
    });
}
updateStartButtons();

// ── Confirm delivery (per shipment) ───────────────────────────────────────
async function confirmDelivery(shipmentId, input) {
    const banner = document.getElementById('banner-' + shipmentId);

    // A package photo is required — the button is a camera input. Bail if none chosen.
    const photo = input && input.files && input.files[0];
    if (!photo) return;

    if (banner) {
        banner.dataset.confirming = '1';
        banner.innerHTML = `
            <div style="background:rgba(0,229,255,0.06); border:1px solid rgba(0,229,255,0.3);
                border-radius:10px; padding:16px 20px; display:flex; align-items:center; gap:12px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                <div style="font-weight:700; font-size:13px; color:var(--accent);">Uploading photo &amp; confirming…</div>
            </div>`;
    }

    try {
        const fd = new FormData();
        fd.append('photo', photo);

        const res  = await fetch(`/fleet/api/shipments/${shipmentId}/confirm-delivery`, {
            method:  'POST',
            headers: {
                'Accept':       'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: fd,
        });
        const json = await res.json();

        if (!res.ok) {
            alert(json.error || 'Confirmation failed. Please try again.');
            // Clear the flag so the next status poll re-renders the confirm banner for a retry.
            if (banner) banner.dataset.confirming = '0';
            return;
        }

        // Success — show confirmation state on this banner
        if (banner) {
            if ('vibrate' in navigator) { try { navigator.vibrate(120); } catch(e) {} }
            banner.innerHTML = `
                <div style="background:rgba(0,229,255,0.06); border:1px solid rgba(0,229,255,0.3);
                    border-radius:10px; padding:16px 20px; display:flex; align-items:center; gap:12px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.5">
                        <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <div>
                        <div style="font-weight:700; font-size:13px; color:var(--accent);">Delivery confirmed</div>
                        <div style="font-size:11px; color:var(--subtle); margin-top:2px;">
                            ${json.tracking_code} delivered at ${json.actual_delivery_at}
                        </div>
                    </div>
                </div>`;
            // Remove banner + list item after 4 seconds
            setTimeout(() => {
                banner.remove();
                document.getElementById('delivery-' + shipmentId)?.remove();
                destMarkers[shipmentId] && map.removeLayer(destMarkers[shipmentId]);
                delete destMarkers[shipmentId];
                const remaining = document.querySelectorAll('.delivery-item').length;
                document.getElementById('deliveries-count').textContent = remaining + ' active';
            }, 4000);
        }

    } catch(e) {
        alert('Network error — please try again.');
        // Clear the flag so the next poll re-renders the confirm banner for a retry.
        if (banner) banner.dataset.confirming = '0';
    }
}

// ── Keep screen on (Wake Lock API; hidden where unsupported) ──────────────
let wakeLock = null;

async function toggleWakeLock() {
    if (wakeLock) {
        try { await wakeLock.release(); } catch(e) {}
        wakeLock = null;
        setWakeUi(false);
        return;
    }
    try {
        wakeLock = await navigator.wakeLock.request('screen');
        wakeLock.addEventListener('release', () => { wakeLock = null; setWakeUi(false); });
        setWakeUi(true);
    } catch(e) {
        // Denied (e.g. low battery) — leave the chip off
        setWakeUi(false);
    }
}

function setWakeUi(on) {
    const btn = document.getElementById('wake-btn');
    btn.classList.toggle('chip-on', on);
    document.getElementById('wake-label').textContent = on ? 'Screen staying on' : 'Keep screen on';
}

if ('wakeLock' in navigator) {
    document.getElementById('wake-btn').style.display = 'flex';
    // Re-acquire when the driver returns to the tab (locks auto-release on hide)
    document.addEventListener('visibilitychange', async () => {
        if (document.visibilityState === 'visible' && wakeLock === null &&
            document.getElementById('wake-btn').classList.contains('chip-on')) {
            try {
                wakeLock = await navigator.wakeLock.request('screen');
                wakeLock.addEventListener('release', () => { wakeLock = null; setWakeUi(false); });
                setWakeUi(true);
            } catch(e) { setWakeUi(false); }
        }
    });
}

// ── Init ──────────────────────────────────────────────────────────────────
fetchLivePosition();
fetchNewAlerts();
fetchDeliveryStatus();
setInterval(fetchLivePosition,    5000);
setInterval(fetchNewAlerts,       10000);
setInterval(fetchDeliveryStatus,  10000);
</script>
@endif
@endpush
