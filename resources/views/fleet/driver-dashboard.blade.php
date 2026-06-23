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

{{-- ── Stat tiles ───────────────────────────────────────────────────────── --}}
<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-tile">
        <div class="stat-label">My Vehicle</div>
        <div style="font-family:var(--font-display); font-size:20px; font-weight:800; color:var(--accent); margin-top:4px;">
            {{ $vehicle->plate_number }}
        </div>
        <div style="font-size:10px; color:var(--subtle); margin-top:4px;">{{ $vehicle->name }}</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Current Speed</div>
        <div class="stat-value accent" id="stat-speed">
            {{ $vehicle->latestPosition ? number_format($vehicle->latestPosition->speed_kmh, 1) : '—' }}
        </div>
        <div style="font-size:10px; color:var(--subtle); margin-top:4px;">km/h</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Vehicle Status</div>
        <div style="margin-top:8px;" id="stat-status">
            <span class="pill {{ $vehicle->isOffline() ? 'pill-offline' : 'pill-online' }}">
                {{ $vehicle->isOffline() ? 'Offline' : 'Online' }}
            </span>
        </div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Unread Alerts</div>
        <div class="stat-value danger" id="stat-alerts">{{ $unreadAlerts->count() }}</div>
    </div>
</div>

{{-- ── Delivery Confirmation Banners (one per nearby shipment, injected by JS) ── --}}
<div id="delivery-banners" style="margin-bottom:0;"></div>

{{-- ── Map + Active Shipment ────────────────────────────────────────────── --}}
<div class="grid-3" style="margin-bottom:20px;">

    {{-- Live Map --}}
    <div class="card" style="min-height:480px; display:flex; flex-direction:column;">
        <div class="card-header">
            <span class="card-title">My Location</span>
            <span style="font-size:10px; color:var(--accent);" id="map-updated">—</span>
        </div>
        <div style="flex:1; position:relative;">
            <div id="fleet-map" style="position:absolute; inset:0; height:100%;"></div>
        </div>
    </div>

    {{-- Right column: Active shipment + alerts --}}
    <div style="display:flex; flex-direction:column; gap:14px;">

        {{-- My Deliveries (sorted nearest first by JS) --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">My Deliveries</span>
                <span style="font-size:10px; color:var(--subtle);" id="deliveries-count">
                    {{ $vehicle->activeShipments->count() }} active
                </span>
            </div>
            <div id="deliveries-list" style="padding:0 18px; max-height:300px; overflow-y:auto;">
                @forelse($vehicle->activeShipments as $s)
                @php
                    $pos      = $vehicle->latestPosition;
                    $distance = $pos ? round($pos->distanceTo($s->destination_lat, $s->destination_lng)) : null;
                    $overdue  = $s->expected_delivery_at && $s->expected_delivery_at->isPast();
                @endphp
                @php $isStarted = in_array($s->status, ['in_transit', 'delayed']); @endphp
                <div class="delivery-item" id="delivery-{{ $s->id }}" data-distance="{{ $distance ?? 999999 }}"
                    data-status="{{ $s->status }}"
                    style="padding:13px 0; border-bottom:1px solid var(--border);
                           {{ $isStarted ? 'background:rgba(0,229,255,0.04); margin:0 -18px; padding-left:18px; padding-right:18px; border-left:3px solid var(--accent);' : '' }}">
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

                    @if($s->destination_lat && $s->destination_lng)
                    {{-- Navigate to destination in the driver's preferred app (opens app on mobile, web otherwise) --}}
                    <div style="display:flex; gap:8px; margin-top:10px;">
                        <a href="https://waze.com/ul?ll={{ $s->destination_lat }},{{ $s->destination_lng }}&navigate=yes"
                           target="_blank" rel="noopener"
                           style="flex:1; display:inline-flex; align-items:center; justify-content:center; gap:6px;
                                  background:var(--muted); color:var(--text); text-decoration:none;
                                  border:1px solid var(--border); border-radius:6px; padding:9px;
                                  font-family:var(--font-mono); font-size:11px; transition:all .15s;"
                           onmouseover="this.style.borderColor='var(--accent)'; this.style.color='var(--accent)';"
                           onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text)';">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>
                            Waze
                        </a>
                        <a href="https://www.google.com/maps/dir/?api=1&destination={{ $s->destination_lat }},{{ $s->destination_lng }}&travelmode=driving"
                           target="_blank" rel="noopener"
                           style="flex:1; display:inline-flex; align-items:center; justify-content:center; gap:6px;
                                  background:var(--muted); color:var(--text); text-decoration:none;
                                  border:1px solid var(--border); border-radius:6px; padding:9px;
                                  font-family:var(--font-mono); font-size:11px; transition:all .15s;"
                           onmouseover="this.style.borderColor='var(--accent)'; this.style.color='var(--accent)';"
                           onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text)';">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            Google Maps
                        </a>
                    </div>
                    @endif

                    @if($s->status === 'pending')
                    <button class="start-delivery-btn" onclick="startDelivery({{ $s->id }}, '{{ $s->tracking_code }}')"
                        style="margin-top:10px; width:100%; background:var(--muted); color:var(--text);
                               border:1px solid var(--border); border-radius:6px; padding:9px;
                               font-family:var(--font-mono); font-size:11px; cursor:pointer;
                               transition:all .15s;"
                        onmouseover="this.style.borderColor='var(--accent)'; this.style.color='var(--accent)';"
                        onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text)';">
                        Start Delivery
                    </button>
                    @endif
                </div>
                @empty
                <div style="text-align:center; padding:24px 0; color:var(--subtle); font-size:12px;">
                    No active deliveries assigned
                </div>
                @endforelse
            </div>
        </div>

        {{-- Alerts --}}
        <div class="card" style="flex:1;">
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
</div>

{{-- ── Vehicle details strip ────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Vehicle Details</span>
        <span style="font-size:11px; color:var(--subtle);" id="last-seen">
            Last seen: {{ $vehicle->latestPosition ? $vehicle->latestPosition->recorded_at->diffForHumans() : 'never' }}
        </span>
    </div>
    <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:0; border-top:1px solid var(--border);">
        @foreach([
            ['label' => 'Plate',      'value' => $vehicle->plate_number,                                                       'id' => ''],
            ['label' => 'Satellites', 'value' => $vehicle->latestPosition?->satellites ?? '—',                                 'id' => 'det-sats'],
            ['label' => 'HDOP',       'value' => $vehicle->latestPosition ? number_format($vehicle->latestPosition->hdop, 2) . '' : '—', 'id' => 'det-hdop'],
            ['label' => 'Heading',    'value' => $vehicle->latestPosition ? number_format($vehicle->latestPosition->heading, 1) . ' deg' : '—', 'id' => 'det-heading'],
            ['label' => 'Coordinates','value' => $vehicle->latestPosition ? number_format($vehicle->latestPosition->latitude, 5).', '.number_format($vehicle->latestPosition->longitude, 5) : '—', 'id' => 'det-coords'],
        ] as $i => $d)
        <div style="padding:16px 18px; {{ $i < 4 ? 'border-right:1px solid var(--border);' : '' }}">
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
.leaflet-tile-pane    { filter: brightness(0.65) saturate(0.7) hue-rotate(185deg); }
.leaflet-container    { background: #0a0b0e; }
.vehicle-popup        { font-family: 'JetBrains Mono', monospace; font-size: 12px; }
.vehicle-popup strong { color: #00e5ff; }
.alert-icon.overspeed svg { stroke: var(--danger); }
.alert-icon.delay     svg { stroke: var(--warning); }

/* ── Driver dashboard mobile ── */
@media (max-width: 768px) {
    /* Map card shorter on phones so deliveries are visible without scrolling far */
    .card[style*="min-height:480px"] {
        min-height: 300px !important;
    }
    #fleet-map { min-height: 300px; }

    /* Stat tiles — keep 2-col but tighter */
    .stats-grid { gap: 8px; margin-bottom: 14px !important; }

    /* Vehicle details strip — 2 cols instead of 5 */
    .card div[style*="grid-template-columns:repeat(5,1fr)"] {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    .card div[style*="grid-template-columns:repeat(5,1fr)"] > div {
        border-right: none !important;
        border-bottom: 1px solid var(--border);
    }

    /* Confirmation banners — stack button below text, full width tap target */
    #delivery-banners > div > div {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    #delivery-banners button {
        width: 100%;
        padding: 14px !important;
        font-size: 14px !important;
    }

    /* Deliveries list — taller scroll area, larger rows */
    #deliveries-list {
        max-height: 380px !important;
    }
    .delivery-item { padding: 16px 0 !important; }

    /* On mobile: deliveries + alerts column shows ABOVE the map.
       grid-3 collapses to 1 column at this width; use order to flip. */
    .grid-3 { display: flex !important; flex-direction: column; }
    .grid-3 > .card:first-child { order: 2; }   /* map second */
    .grid-3 > div:last-child   { order: 1; }   /* deliveries/alerts first */
}
</style>
@endpush

@push('scripts')
@if(isset($vehicle) && $vehicle->latestPosition)
<script>
const CSRF       = document.querySelector('meta[name="csrf-token"]').content;
const VEHICLE_ID = {{ $vehicle->id }};

// ── Map ───────────────────────────────────────────────────────────────────
const map = L.map('fleet-map', { zoomControl: true, attributionControl: false })
    .setView([{{ $vehicle->latestPosition->latitude }}, {{ $vehicle->latestPosition->longitude }}], 14);

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

let vehicleMarker = L.marker(
    [{{ $vehicle->latestPosition->latitude }}, {{ $vehicle->latestPosition->longitude }}],
    { icon: makeIcon({{ $vehicle->isOffline() ? 'true' : 'false' }}) }
).addTo(map).bindPopup('');

// Destination markers — one per active delivery
const destIcon = L.divIcon({
    className: '',
    html: `<div style="width:12px;height:12px;border-radius:50%;background:#ef4444;border:2px solid #7f1d1d;box-shadow:0 0 6px #ef444488;"></div>`,
    iconSize: [12, 12], iconAnchor: [6, 6],
});
@foreach($vehicle->activeShipments as $s)
L.marker([{{ $s->destination_lat }}, {{ $s->destination_lng }}], { icon: destIcon })
    .addTo(map)
    .bindPopup('<b>{{ $s->tracking_code }}</b><br>{{ $s->client_name }}<br>{{ $s->destination_address }}');
@endforeach

// ── Live polling ──────────────────────────────────────────────────────────
async function fetchLivePosition() {
    try {
        const res  = await fetch('{{ route("fleet.api.live") }}', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        const v    = data.find(v => v.id === VEHICLE_ID);
        if (!v || !v.latitude) return;

        const latlng = [v.latitude, v.longitude];
        vehicleMarker.setLatLng(latlng).setIcon(makeIcon(v.is_offline));
        vehicleMarker.getPopup().setContent(`
            <div class="vehicle-popup">
                <strong>${v.name}</strong><br>
                Speed: ${(v.speed_kmh ?? 0).toFixed(1)} km/h<br>
                ${v.is_offline ? '<span style="color:#ef4444">OFFLINE</span>' : '<span style="color:#22c55e">LIVE</span>'}
            </div>
        `);

        map.panTo(latlng);

        // Update stat tiles
        document.getElementById('stat-speed').textContent  = (v.speed_kmh ?? 0).toFixed(1);
        document.getElementById('stat-status').innerHTML   = `<span class="pill ${v.is_offline ? 'pill-offline' : 'pill-online'}">${v.is_offline ? 'Offline' : 'Online'}</span>`;
        document.getElementById('map-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();

        // Update details strip
        if (document.getElementById('det-sats'))    document.getElementById('det-sats').textContent    = v.satellites ?? '—';
        if (document.getElementById('det-hdop'))    document.getElementById('det-hdop').textContent    = v.hdop ? parseFloat(v.hdop).toFixed(2) : '—';
        if (document.getElementById('det-heading')) document.getElementById('det-heading').textContent = v.heading ? parseFloat(v.heading).toFixed(1) + ' deg' : '—';
        if (document.getElementById('det-coords'))  document.getElementById('det-coords').textContent  = v.latitude ? v.latitude.toFixed(5) + ', ' + v.longitude.toFixed(5) : '—';
        if (document.getElementById('last-seen'))   document.getElementById('last-seen').textContent   = 'Last seen: ' + (v.recorded_at ? timeAgo(v.recorded_at) : 'never');

    } catch(e) { console.error('Live fetch error:', e); }
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
    } catch(e) { console.error('Alert fetch error:', e); }
}

// ── Delivery status polling (multi-shipment) ─────────────────────────────
async function fetchDeliveryStatus() {
    try {
        const res  = await fetch('{{ route("fleet.api.delivery.status") }}', {
            headers: { 'Accept': 'application/json' }
        });
        const data      = await res.json();
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

            if (!banner) {
                banner = document.createElement('div');
                banner.id = 'banner-' + s.shipment_id;
                banner.style.marginBottom = '14px';
                container.appendChild(banner);
            }

            // Skip re-render if this banner is mid-confirmation
            if (banner.dataset.confirming === '1') return;

            if (s.near_destination) {
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

    } catch(e) { console.error('Delivery status error:', e); }
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
function updateStartButtons() {
    const inProgress = document.querySelector('.delivery-item[data-status="in_transit"], .delivery-item[data-status="delayed"]');
    document.querySelectorAll('.start-delivery-btn').forEach(btn => {
        if (inProgress) {
            btn.disabled            = true;
            btn.style.opacity       = '.4';
            btn.style.cursor        = 'not-allowed';
            btn.textContent         = 'Finish current delivery first';
            btn.onmouseover = btn.onmouseout = null;
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
