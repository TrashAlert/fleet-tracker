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

    {{-- Live Map + Trip History --}}
    <div class="card" style="min-height:520px; display:flex; flex-direction:column;">
        <div class="card-header" style="flex-wrap:wrap; gap:10px;">
            <span class="card-title" id="map-title">Live Map</span>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                {{-- Trip history controls --}}
                <select id="history-vehicle" class="history-input">
                    <option value="">Trip History...</option>
                    @foreach($vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->plate_number }}</option>
                    @endforeach
                </select>
                <input type="date" id="history-date" class="history-input"
                       value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}">
                <button onclick="loadTripHistory()" class="btn btn-ghost" style="padding:5px 12px; font-size:11px;">
                    View Route
                </button>
                <button onclick="exitHistoryMode()" id="exit-history-btn" class="btn btn-ghost"
                        style="padding:5px 12px; font-size:11px; display:none; color:var(--accent); border-color:var(--accent);">
                    Back to Live
                </button>
                <span style="font-size:10px; color:var(--accent);" id="map-updated">—</span>
            </div>
        </div>

        {{-- History summary strip (hidden until route loaded) --}}
        <div id="history-summary" style="display:none; padding:10px 18px; border-bottom:1px solid var(--border); background:rgba(0,229,255,0.03);">
            <div style="display:flex; gap:24px; flex-wrap:wrap; font-size:11px;">
                <div><span style="color:var(--subtle);">Points:</span> <span class="mono" id="hs-points">—</span></div>
                <div><span style="color:var(--subtle);">Distance:</span> <span class="mono" id="hs-distance">—</span></div>
                <div><span style="color:var(--subtle);">Duration:</span> <span class="mono" id="hs-duration">—</span></div>
                <div><span style="color:var(--subtle);">Avg Speed:</span> <span class="mono" id="hs-avg-speed">—</span></div>
                <div><span style="color:var(--subtle);">Max Speed:</span> <span class="mono" id="hs-max-speed">—</span></div>
            </div>
        </div>

        <div style="flex:1; position:relative;">
            <div id="fleet-map" style="position:absolute; inset:0; height:100%;"></div>

            {{-- Speed legend (history mode only) --}}
            <div id="speed-legend" style="display:none; position:absolute; bottom:14px; left:14px; z-index:500;
                background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:10px 14px;">
                <div style="font-size:9px; letter-spacing:.1em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Speed</div>
                <div style="display:flex; align-items:center; gap:6px; font-size:10px;">
                    <span style="width:24px; height:4px; background:#22c55e; border-radius:2px;"></span> &lt;60
                    <span style="width:24px; height:4px; background:#f59e0b; border-radius:2px; margin-left:8px;"></span> 60–110
                    <span style="width:24px; height:4px; background:#ef4444; border-radius:2px; margin-left:8px;"></span> &gt;110 km/h
                </div>
            </div>
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

.history-input {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    font-family: var(--font-mono);
    font-size: 11px;
    padding: 5px 9px;
    outline: none;
}
.history-input:focus { border-color: var(--accent); }
.history-input option { background: var(--surface); }

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
    .setView([2.1896, 102.2501], 10);

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
    if (historyMode) return;   // paused while viewing trip history
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

// ── Trip History ──────────────────────────────────────────────────────────
let historyMode    = false;
let historyLayers  = [];   // polyline segments + markers
let livePollTimer  = null;

function speedColor(kmh) {
    if (kmh > 110) return '#ef4444';   // overspeed — red
    if (kmh >= 60) return '#f59e0b';   // moderate — amber
    return '#22c55e';                  // slow — green
}

function clearHistoryLayers() {
    historyLayers.forEach(l => map.removeLayer(l));
    historyLayers = [];
}

async function loadTripHistory() {
    const vehicleId = document.getElementById('history-vehicle').value;
    const date      = document.getElementById('history-date').value;

    if (!vehicleId) { alert('Select a vehicle first.'); return; }
    if (!date)      { alert('Select a date.'); return; }

    try {
        const res = await fetch(`/fleet/api/vehicle/${vehicleId}/history?date=${date}`, {
            headers: { 'Accept': 'application/json' }
        });
        const points = await res.json();

        if (!Array.isArray(points) || points.length === 0) {
            alert('No GPS data recorded for this vehicle on ' + date + '.');
            return;
        }

        // Enter history mode — stop live updates, hide live markers
        enterHistoryMode();
        clearHistoryLayers();

        // ── Draw speed-coloured polyline segments ──
        let totalDistance = 0;
        let maxSpeed      = 0;
        let speedSum      = 0;

        for (let i = 1; i < points.length; i++) {
            const a = points[i - 1];
            const b = points[i];
            const seg = L.polyline(
                [[a.latitude, a.longitude], [b.latitude, b.longitude]],
                { color: speedColor(b.speed_kmh), weight: 4, opacity: 0.85 }
            ).addTo(map);
            historyLayers.push(seg);

            totalDistance += haversine(a.latitude, a.longitude, b.latitude, b.longitude);
            maxSpeed = Math.max(maxSpeed, b.speed_kmh ?? 0);
            speedSum += (b.speed_kmh ?? 0);
        }

        // ── Start + end markers ──
        const startP = points[0];
        const endP   = points[points.length - 1];

        const startMarker = L.marker([startP.latitude, startP.longitude], {
            icon: L.divIcon({
                className: '',
                html: `<div style="width:14px;height:14px;border-radius:50%;background:#22c55e;border:3px solid #fff;box-shadow:0 0 8px #22c55e99;"></div>`,
                iconSize: [14, 14], iconAnchor: [7, 7],
            })
        }).addTo(map).bindPopup(`<b>Start</b><br>${fmtTime(startP.recorded_at)}`);

        const endMarker = L.marker([endP.latitude, endP.longitude], {
            icon: L.divIcon({
                className: '',
                html: `<div style="width:14px;height:14px;border-radius:50%;background:#ef4444;border:3px solid #fff;box-shadow:0 0 8px #ef444499;"></div>`,
                iconSize: [14, 14], iconAnchor: [7, 7],
            })
        }).addTo(map).bindPopup(`<b>End</b><br>${fmtTime(endP.recorded_at)}`);

        historyLayers.push(startMarker, endMarker);

        // ── Fit map to route ──
        const bounds = points.map(p => [p.latitude, p.longitude]);
        map.fitBounds(bounds, { padding: [40, 40] });

        // ── Summary stats ──
        const durationMs  = new Date(endP.recorded_at) - new Date(startP.recorded_at);
        const durationMin = Math.round(durationMs / 60000);
        const avgSpeed    = points.length > 1 ? (speedSum / (points.length - 1)) : 0;

        document.getElementById('hs-points').textContent    = points.length.toLocaleString();
        document.getElementById('hs-distance').textContent  = totalDistance >= 1000
            ? (totalDistance / 1000).toFixed(2) + ' km'
            : Math.round(totalDistance) + ' m';
        document.getElementById('hs-duration').textContent  = durationMin >= 60
            ? Math.floor(durationMin / 60) + 'h ' + (durationMin % 60) + 'm'
            : durationMin + ' min';
        document.getElementById('hs-avg-speed').textContent = avgSpeed.toFixed(1) + ' km/h';
        document.getElementById('hs-max-speed').textContent = maxSpeed.toFixed(1) + ' km/h';
        document.getElementById('history-summary').style.display = 'block';

    } catch(e) {
        console.error('Trip history error:', e);
        alert('Failed to load trip history.');
    }
}

function enterHistoryMode() {
    if (historyMode) return;
    historyMode = true;

    // Stop live polling
    if (livePollTimer) { clearInterval(livePollTimer); livePollTimer = null; }

    // Hide live vehicle markers
    Object.values(markers).forEach(m => map.removeLayer(m));

    // UI state
    const vSel = document.getElementById('history-vehicle');
    const vName = vSel.options[vSel.selectedIndex].text;
    document.getElementById('map-title').textContent = 'Trip History — ' + vName;
    document.getElementById('exit-history-btn').style.display = 'inline-flex';
    document.getElementById('speed-legend').style.display = 'block';
    document.getElementById('map-updated').textContent = '';
}

function exitHistoryMode() {
    historyMode = false;

    clearHistoryLayers();

    // Restore live markers
    Object.values(markers).forEach(m => m.addTo(map));

    // UI state
    document.getElementById('map-title').textContent = 'Live Map';
    document.getElementById('exit-history-btn').style.display = 'none';
    document.getElementById('speed-legend').style.display = 'none';
    document.getElementById('history-summary').style.display = 'none';
    document.getElementById('history-vehicle').value = '';

    // Resume live polling immediately
    fetchLivePositions();
    livePollTimer = setInterval(fetchLivePositions, 5000);
}

// Haversine — metres between two coordinates
function haversine(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const toRad = d => d * Math.PI / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) ** 2 +
              Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(a));
}

function fmtTime(iso) {
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// ── Init ──────────────────────────────────────────────────────────────────
fetchLivePositions();
fetchNewAlerts();
livePollTimer = setInterval(fetchLivePositions, 5000);
setInterval(fetchNewAlerts, 10000);
</script>
@endpush
