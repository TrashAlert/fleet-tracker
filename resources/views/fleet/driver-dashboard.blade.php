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

{{-- ── Delivery Confirmation Banner (injected by JS when near destination) ── --}}
<div id="delivery-banner" style="display:none; margin-bottom:20px;">
    <div style="
        background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.35);
        border-radius:10px; padding:18px 22px;
        display:flex; align-items:center; justify-content:space-between; gap:16px;
    ">
        <div style="display:flex; align-items:center; gap:14px;">
            <div style="
                width:36px; height:36px; border-radius:8px;
                background:rgba(34,197,94,0.15);
                display:flex; align-items:center; justify-content:center; flex-shrink:0;
            ">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
                </svg>
            </div>
            <div>
                <div style="font-weight:700; font-size:13px; color:var(--success);">You are near the delivery destination</div>
                <div style="font-size:11px; color:var(--subtle); margin-top:3px;" id="banner-distance">
                    Within delivery zone — please confirm when goods are handed over.
                </div>
            </div>
        </div>
        <div style="display:flex; gap:10px; flex-shrink:0;">
            <div id="banner-flag-warning" style="display:none; font-size:11px; color:var(--warning); align-self:center; text-align:right; max-width:180px;">
                You have left the zone.<br>Confirm within 5 minutes or a flag will be raised.
            </div>
            <button id="confirm-delivery-btn" onclick="confirmDelivery()"
                style="
                    background:var(--success); color:#000; border:none; border-radius:7px;
                    padding:10px 20px; font-family:var(--font-display); font-weight:700;
                    font-size:13px; cursor:pointer; transition:opacity .15s; white-space:nowrap;
                "
                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                Confirm Delivery
            </button>
        </div>
    </div>
</div>

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

        {{-- Active Shipment --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">Active Shipment</span>
                @if($vehicle->activeShipment)
                    <span class="pill pill-transit">{{ $vehicle->activeShipment->status }}</span>
                @endif
            </div>
            <div style="padding:16px 18px;">
                @if($vehicle->activeShipment)
                @php $s = $vehicle->activeShipment; @endphp
                <div style="margin-bottom:14px;">
                    <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--subtle); margin-bottom:4px;">Tracking Code</div>
                    <div style="font-family:var(--font-display); font-size:16px; font-weight:700; color:var(--accent);">{{ $s->tracking_code }}</div>
                </div>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <div>
                        <div style="font-size:10px; color:var(--subtle); text-transform:uppercase; letter-spacing:.08em; margin-bottom:3px;">Client</div>
                        <div style="font-size:12px;">{{ $s->client_name }}</div>
                    </div>
                    <div>
                        <div style="font-size:10px; color:var(--subtle); text-transform:uppercase; letter-spacing:.08em; margin-bottom:3px;">Destination</div>
                        <div style="font-size:12px; color:var(--subtle);">{{ $s->destination_address }}</div>
                    </div>
                    <div>
                        <div style="font-size:10px; color:var(--subtle); text-transform:uppercase; letter-spacing:.08em; margin-bottom:3px;">Expected By</div>
                        @php $overdue = $s->expected_delivery_at && $s->expected_delivery_at->isPast() && $s->status !== 'delivered'; @endphp
                        <div style="font-size:12px; {{ $overdue ? 'color:var(--danger);' : '' }}">
                            {{ $s->expected_delivery_at?->format('d M Y, H:i') ?? '—' }}
                            @if($overdue) <span style="font-size:10px;">(overdue)</span> @endif
                        </div>
                    </div>
                </div>
                @else
                <div style="text-align:center; padding:24px 0; color:var(--subtle); font-size:12px;">
                    No active shipment assigned
                </div>
                @endif
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

@if($vehicle->activeShipment)
// Destination marker
L.marker([{{ $vehicle->activeShipment->destination_lat }}, {{ $vehicle->activeShipment->destination_lng }}], {
    icon: L.divIcon({
        className: '',
        html: `<div style="width:12px;height:12px;border-radius:50%;background:#ef4444;border:2px solid #7f1d1d;box-shadow:0 0 6px #ef444488;"></div>`,
        iconSize: [12, 12], iconAnchor: [6, 6],
    })
}).addTo(map).bindPopup('Destination: {{ $vehicle->activeShipment->destination_address }}');
@endif

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

// ── Delivery status polling ───────────────────────────────────────────────
let lastDeliveryState = null;

async function fetchDeliveryStatus() {
    try {
        const res  = await fetch('{{ route("fleet.api.delivery.status") }}', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        const banner      = document.getElementById('delivery-banner');
        const distanceEl  = document.getElementById('banner-distance');
        const flagWarning = document.getElementById('banner-flag-warning');
        const confirmBtn  = document.getElementById('confirm-delivery-btn');

        // No active shipment or not near — hide banner
        if (!data.near_destination && !data.left_radius_at) {
            banner.style.display = 'none';
            lastDeliveryState    = null;
            return;
        }

        // Store shipment id for confirm call
        banner.dataset.shipmentId = data.shipment_id;

        // Show banner
        banner.style.display = 'block';

        if (data.near_destination) {
            // Inside the zone
            distanceEl.textContent  = `${data.distance_metres}m from destination — please confirm when goods are handed over.`;
            flagWarning.style.display = 'none';
            confirmBtn.disabled       = false;
            confirmBtn.style.opacity  = '1';
        } else if (data.left_radius_at) {
            // Left the zone — show warning + countdown
            const leftAt      = new Date(data.left_radius_at);
            const minsOutside = Math.floor((Date.now() - leftAt) / 60000);
            const minsLeft    = Math.max(0, 5 - minsOutside);

            distanceEl.textContent    = `${data.distance_metres}m from destination — you have left the delivery zone.`;
            flagWarning.style.display = 'block';
            flagWarning.innerHTML     = minsLeft > 0
                ? `You have left the zone.<br><span style="color:var(--danger);">${minsLeft} minute${minsLeft !== 1 ? 's' : ''} left</span> before a flag is raised.`
                : `<span style="color:var(--danger);">Flag has been raised</span> — contact your manager.`;

            // Disable confirm button if outside radius
            confirmBtn.disabled      = true;
            confirmBtn.style.opacity = '.4';
        }

        lastDeliveryState = data;

    } catch(e) { console.error('Delivery status error:', e); }
}

// ── Confirm delivery ──────────────────────────────────────────────────────
async function confirmDelivery() {
    const shipmentId = document.getElementById('delivery-banner').dataset.shipmentId;
    if (!shipmentId) return;

    const btn = document.getElementById('confirm-delivery-btn');
    btn.textContent  = 'Confirming...';
    btn.disabled     = true;

    try {
        const res  = await fetch(`/fleet/api/shipments/${shipmentId}/confirm-delivery`, {
            method:  'POST',
            headers: {
                'Content-Type':  'application/json',
                'Accept':        'application/json',
                'X-CSRF-TOKEN':  CSRF,
            },
        });
        const json = await res.json();

        if (!res.ok) {
            alert(json.error || 'Confirmation failed. Please try again.');
            btn.textContent = 'Confirm Delivery';
            btn.disabled    = false;
            return;
        }

        // Success — update the banner
        const banner = document.getElementById('delivery-banner');
        banner.style.background    = 'rgba(0,229,255,0.06)';
        banner.style.borderColor   = 'rgba(0,229,255,0.3)';
        banner.querySelector('svg').setAttribute('stroke', 'var(--accent)');
        banner.querySelector('div[style*="font-weight:700"]').style.color = 'var(--accent)';
        banner.querySelector('div[style*="font-weight:700"]').textContent = 'Delivery confirmed!';
        document.getElementById('banner-distance').textContent = `Shipment ${json.tracking_code} marked as delivered at ${json.actual_delivery_at}.`;
        document.getElementById('banner-flag-warning').style.display = 'none';
        btn.style.display = 'none';

        // Refresh page after 3 seconds
        setTimeout(() => location.reload(), 3000);

    } catch(e) {
        alert('Network error — please try again.');
        btn.textContent = 'Confirm Delivery';
        btn.disabled    = false;
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
