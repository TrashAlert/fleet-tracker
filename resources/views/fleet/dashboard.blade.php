@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

{{-- Stats row --}}
<div class="stats-grid">
    <div class="stat-tile">
        <div class="stat-label">Total Vehicles</div>
        <div class="stat-value accent" id="stat-total">{{ $vehicles->count() }}</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Online Now</div>
        <div class="stat-value success" id="stat-online">—</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Active Shipments</div>
        <div class="stat-value" id="stat-shipments">{{ $vehicles->filter(fn($v) => $v->activeShipment)->count() }}</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Unread Alerts</div>
        <div class="stat-value danger" id="stat-alerts">{{ $unreadAlerts->count() }}</div>
    </div>
</div>

<div class="grid-3" style="margin-bottom: 20px;">

    {{-- Live Map --}}
    <div class="card" style="min-height: 520px; display:flex; flex-direction:column;">
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
            <span class="text-subtle" style="font-size:10px;">{{ $unreadAlerts->count() }} unread</span>
        </div>
        <div class="card-body" style="padding:0 18px; max-height:520px; overflow-y:auto;">
            @forelse($unreadAlerts as $alert)
            <div class="alert-item" id="alert-{{ $alert->id }}">
                <div class="alert-icon {{ $alert->type }}">
                    @if($alert->type === 'overspeed') 🚨
                    @elseif($alert->type === 'delay') ⏰
                    @else 📡 @endif
                </div>
                <div>
                    <div class="alert-msg">{{ $alert->message }}</div>
                    <div class="alert-time">{{ $alert->triggered_at->diffForHumans() }}</div>
                </div>
                <button onclick="dismissAlert({{ $alert->id }})" style="background:none;border:none;color:var(--subtle);cursor:pointer;margin-left:auto;font-size:16px;align-self:flex-start;">×</button>
            </div>
            @empty
            <div style="padding: 40px 0; text-align:center; color:var(--subtle); font-size:12px;">
                No unread alerts
            </div>
            @endforelse
        </div>
    </div>

</div>

{{-- Vehicle table --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Fleet Status</span>
        <a href="{{ route('fleet.vehicles') }}" class="btn btn-ghost" style="padding:5px 10px; font-size:11px;">Manage →</a>
    </div>
    <table class="data-table" id="fleet-table">
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
                    {{ $v->latestPosition ? number_format($v->latestPosition->speed_kmh, 1) . ' km/h' : '—' }}
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
    /* Dark Leaflet tiles */
    .leaflet-tile-pane { filter: brightness(0.65) saturate(0.7) hue-rotate(185deg); }
    .leaflet-container { background: #0a0b0e; }
    .vehicle-popup { font-family: 'JetBrains Mono', monospace; font-size: 12px; }
    .vehicle-popup strong { color: #00e5ff; }
</style>
@endpush

@push('scripts')
<script>
// ── Map setup ──────────────────────────────────────────────
const map = L.map('fleet-map', { zoomControl: true, attributionControl: false })
    .setView([3.140853, 101.686855], 10); // default: Kuala Lumpur

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
}).addTo(map);

// Custom marker icon
function makeIcon(isOffline) {
    return L.divIcon({
        className: '',
        html: `<div style="
            width:14px;height:14px;border-radius:50%;
            background:${isOffline ? '#ef4444' : '#00e5ff'};
            border:2px solid ${isOffline ? '#7f1d1d' : '#00454f'};
            box-shadow:0 0 8px ${isOffline ? '#ef444488' : '#00e5ff88'};
        "></div>`,
        iconSize: [14, 14],
        iconAnchor: [7, 7],
    });
}

const markers = {};

// ── Live polling ────────────────────────────────────────────
async function fetchLivePositions() {
    try {
        const res  = await fetch('{{ route("fleet.api.live") }}');
        const data = await res.json();

        let onlineCount = 0;

        data.forEach(v => {
            if (! v.latitude || ! v.longitude) return;
            if (! v.is_offline) onlineCount++;

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
                    Speed: ${v.speed_kmh?.toFixed(1) ?? 0} km/h<br>
                    ${v.is_offline ? '<span style="color:#ef4444">OFFLINE</span>' : '<span style="color:#22c55e">● LIVE</span>'}
                </div>
            `);

            // Update table row
            const speed  = document.getElementById(`speed-${v.id}`);
            const status = document.getElementById(`status-${v.id}`);
            const seen   = document.getElementById(`seen-${v.id}`);
            if (speed)  speed.textContent  = (v.speed_kmh?.toFixed(1) ?? 0) + ' km/h';
            if (status) status.innerHTML   = `<span class="pill ${v.is_offline ? 'pill-offline' : 'pill-online'}">${v.is_offline ? 'offline' : 'online'}</span>`;
            if (seen)   seen.textContent   = v.recorded_at ? timeAgo(v.recorded_at) : 'never';
        });

        document.getElementById('stat-online').textContent = onlineCount;
        document.getElementById('map-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();

    } catch (e) { console.error('Live fetch error:', e); }
}

function timeAgo(isoString) {
    const diff = Math.floor((Date.now() - new Date(isoString)) / 1000);
    if (diff < 60)   return diff + 's ago';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    return Math.floor(diff / 3600) + 'h ago';
}

// ── Alert dismiss ───────────────────────────────────────────
async function dismissAlert(id) {
    await fetch(`/fleet/api/alerts/${id}/read`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    });
    document.getElementById(`alert-${id}`)?.remove();
    const count = document.querySelectorAll('.alert-item').length;
    document.getElementById('stat-alerts').textContent = count;
}


// --- Alert Display -------------


async function fetchAlerts() {
    try {
        const res  = await fetch('{{ route("fleet.api.alerts") }}');
        const data = await res.json();

        const panel = document.querySelector('.card-body');
        if (! panel) return;

        // Update alert count
        document.getElementById('stat-alerts').textContent = data.length;

        if (data.length === 0) {
            panel.innerHTML = `<div style="padding:40px 0;text-align:center;color:var(--subtle);font-size:12px;">No unread alerts</div>`;
            return;
        }

        panel.innerHTML = data.map(alert => `
            <div class="alert-item" id="alert-${alert.id}">
                <div class="alert-icon ${alert.type}">
                    ${alert.type === 'overspeed' ? '🚨' : alert.type === 'delay' ? '⏰' : '📡'}
                </div>
                <div>
                    <div class="alert-msg">${alert.message}</div>
                    <div class="alert-time">${alert.triggered_at}</div>
                </div>
                <button onclick="dismissAlert(${alert.id})" style="background:none;border:none;color:var(--subtle);cursor:pointer;margin-left:auto;font-size:16px;align-self:flex-start;">×</button>
            </div>
        `).join('');

    } catch(e) { console.error('Alert fetch error:', e); }
}


// ── Init ────────────────────────────────────────────────────
fetchLivePositions();
fetchAlerts();
setInterval(fetchLivePositions, 5000); // poll every 5s
setInterval(fetchAlerts, 5000);
</script>
@endpush
