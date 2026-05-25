@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

{{-- ── Stat tiles ───────────────────────────────────────────────────────── --}}
<div class="stats-grid">
    <div class="stat-tile">
        <div class="stat-label">
            {{ auth()->user()->isDriver() ? 'My Vehicle' : 'Total Vehicles' }}
        </div>
        <div class="stat-value accent" id="stat-total">{{ $vehicles->count() }}</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Online Now</div>
        <div class="stat-value success" id="stat-online">—</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Active Shipments</div>
        <div class="stat-value" id="stat-shipments">
            {{ $vehicles->filter(fn($v) => $v->activeShipment)->count() }}
        </div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Unread Alerts</div>
        <div class="stat-value danger" id="stat-alerts">{{ $unreadAlerts->count() }}</div>
    </div>
</div>

{{-- ── Map + Alerts ─────────────────────────────────────────────────────── --}}
<div class="grid-3" style="margin-bottom:20px;">

    {{-- Live Map --}}
    <div class="card" style="min-height:520px; display:flex; flex-direction:column;">
        <div class="card-header">
            <span class="card-title">Live Map</span>
            <span style="font-size:10px; color:var(--accent);" id="map-updated">—</span>
        </div>
        <div style="flex:1; position:relative;">
            <div id="fleet-map" style="position:absolute; inset:0; height:100%;"></div>
        </div>
    </div>

    {{-- Alerts panel --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">Alerts</span>
            <span style="font-size:10px; color:var(--subtle);" id="alert-count-label">
                {{ $unreadAlerts->count() }} unread
            </span>
        </div>
        <div id="alerts-panel" style="padding:0 18px; max-height:520px; overflow-y:auto;">
            @forelse($unreadAlerts as $alert)
            <div class="alert-item" id="alert-{{ $alert->id }}">
                <div class="alert-icon {{ $alert->type }}">
                    @if($alert->type === 'overspeed')
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    @elseif($alert->type === 'delay')
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    @else
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 6s4-2 11-2 11 2 11 2"/><path d="M5 10s2.5-1 7-1 7 1 7 1"/><path d="M9 14s1.5-.5 3-.5 3 .5 3 .5"/><line x1="12" y1="18" x2="12" y2="18.5" stroke-width="3"/></svg>
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
            <div id="no-alerts-msg" style="padding:40px 0; text-align:center; color:var(--subtle); font-size:12px;">
                No unread alerts
            </div>
            @endforelse
        </div>
    </div>

</div>

{{-- ── Fleet status table ───────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Fleet Status</span>
        @if(!auth()->user()->isDriver())
        <a href="{{ route('fleet.vehicles') }}" class="btn btn-ghost" style="padding:5px 10px; font-size:11px;">
            Manage →
        </a>
        @endif
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Vehicle</th>
                <th>Plate</th>
                <th>Driver</th>
                <th>Speed</th>
                <th>Status</th>
                <th>Last Seen</th>
                <th>Shipment</th>
            </tr>
        </thead>
        <tbody id="fleet-tbody">
            @foreach($vehicles as $v)
            <tr id="row-{{ $v->id }}">
                <td class="text-accent">{{ $v->name }}</td>
                <td class="mono">{{ $v->plate_number }}</td>
                <td>{{ $v->driver_name ?? '—' }}</td>
                <td class="mono" id="speed-{{ $v->id }}">
                    {{ $v->latestPosition ? number_format($v->latestPosition->speed_kmh, 1).' km/h' : '—' }}
                </td>
                <td id="status-{{ $v->id }}">
                    <span class="pill {{ $v->isOffline() ? 'pill-offline' : 'pill-online' }}">
                        {{ $v->isOffline() ? 'offline' : 'online' }}
                    </span>
                </td>
                <td class="text-subtle" id="seen-{{ $v->id }}">
                    {{ $v->latestPosition ? $v->latestPosition->recorded_at->diffForHumans() : 'never' }}
                </td>
                <td id="ship-{{ $v->id }}">
                    @if($v->activeShipment)
                        <span class="pill pill-transit">{{ $v->activeShipment->tracking_code }}</span>
                    @else
                        <span class="text-subtle">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection

@push('styles')
<style>
/* Match dashboard dark map style */
.leaflet-tile-pane    { filter: brightness(0.65) saturate(0.7) hue-rotate(185deg); }
.leaflet-container    { background: #0a0b0e; }
.vehicle-popup        { font-family: 'JetBrains Mono', monospace; font-size: 12px; }
.vehicle-popup strong { color: #00e5ff; }

/* Alert icon SVG colours */
.alert-icon.overspeed svg { stroke: var(--danger); }
.alert-icon.delay     svg { stroke: var(--warning); }
.alert-icon.offline   svg { stroke: var(--subtle); }
.alert-icon.geofence  svg { stroke: var(--accent); }
</style>
@endpush

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// ── Map setup ─────────────────────────────────────────────────────────────
const map = L.map('fleet-map', { zoomControl: true, attributionControl: false })
    .setView([3.140853, 101.686855], 10);

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
        iconSize: [14, 14],
        iconAnchor: [7, 7],
    });
}

const markers = {};

// ── Live positions — poll every 5s ────────────────────────────────────────
async function fetchLivePositions() {
    try {
        const res  = await fetch('{{ route("fleet.api.live") }}', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        let onlineCount = 0;

        data.forEach(v => {
            if (!v.latitude || !v.longitude) return;
            if (!v.is_offline) onlineCount++;

            const latlng = [v.latitude, v.longitude];

            if (markers[v.id]) {
                markers[v.id].setLatLng(latlng).setIcon(makeIcon(v.is_offline));
            } else {
                markers[v.id] = L.marker(latlng, { icon: makeIcon(v.is_offline) })
                    .addTo(map)
                    .bindPopup('');
            }

            markers[v.id].getPopup().setContent(`
                <div class="vehicle-popup">
                    <strong>${v.name}</strong><br>
                    Plate: ${v.plate}<br>
                    Driver: ${v.driver ?? '—'}<br>
                    Speed: ${(v.speed_kmh ?? 0).toFixed(1)} km/h<br>
                    ${v.is_offline
                        ? '<span style="color:#ef4444">OFFLINE</span>'
                        : '<span style="color:#22c55e">LIVE</span>'}
                </div>
            `);

            // Update table row live
            const speedEl  = document.getElementById(`speed-${v.id}`);
            const statusEl = document.getElementById(`status-${v.id}`);
            const seenEl   = document.getElementById(`seen-${v.id}`);
            if (speedEl)  speedEl.textContent = (v.speed_kmh ?? 0).toFixed(1) + ' km/h';
            if (statusEl) statusEl.innerHTML  = `<span class="pill ${v.is_offline ? 'pill-offline' : 'pill-online'}">${v.is_offline ? 'offline' : 'online'}</span>`;
            if (seenEl)   seenEl.textContent  = v.recorded_at ? timeAgo(v.recorded_at) : 'never';
        });

        document.getElementById('stat-online').textContent   = onlineCount;
        document.getElementById('map-updated').textContent   = 'Updated ' + new Date().toLocaleTimeString();

    } catch(e) { console.error('Live fetch error:', e); }
}

function timeAgo(isoString) {
    const diff = Math.floor((Date.now() - new Date(isoString)) / 1000);
    if (diff < 60)   return diff + 's ago';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    return Math.floor(diff / 3600) + 'h ago';
}

// ── Alert dismiss (incremental — no full repaint) ─────────────────────────
async function dismissAlert(id) {
    try {
        await fetch(`/fleet/api/alerts/${id}/read`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF }
        });
    } catch(e) {}

    document.getElementById(`alert-${id}`)?.remove();
    updateAlertCount();
}

function updateAlertCount() {
    const count = document.querySelectorAll('.alert-item').length;
    document.getElementById('stat-alerts').textContent    = count;
    document.getElementById('alert-count-label').textContent = count + ' unread';

    if (count === 0) {
        const panel = document.getElementById('alerts-panel');
        if (!document.getElementById('no-alerts-msg')) {
            panel.innerHTML = `<div id="no-alerts-msg" style="padding:40px 0;text-align:center;color:var(--subtle);font-size:12px;">No unread alerts</div>`;
        }
    }
}

// ── New alerts — only inject ones not already in DOM ──────────────────────
function alertIconSvg(type) {
    if (type === 'overspeed') return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`;
    if (type === 'delay')     return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`;
    return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--subtle)" stroke-width="2.5"><path d="M1 6s4-2 11-2 11 2 11 2"/><path d="M5 10s2.5-1 7-1 7 1 7 1"/><path d="M9 14s1.5-.5 3-.5 3 .5 3 .5"/><line x1="12" y1="18" x2="12" y2="18.5" stroke-width="3"/></svg>`;
}

async function fetchNewAlerts() {
    try {
        const res  = await fetch('{{ route("fleet.api.alerts") }}', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        const panel = document.getElementById('alerts-panel');

        data.forEach(alert => {
            // Skip if already in DOM
            if (document.getElementById(`alert-${alert.id}`)) return;

            // Remove empty state if present
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

// ── Init ──────────────────────────────────────────────────────────────────
fetchLivePositions();
fetchNewAlerts();
setInterval(fetchLivePositions, 5000);
setInterval(fetchNewAlerts, 10000);
</script>
@endpush
