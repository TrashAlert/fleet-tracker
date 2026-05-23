<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Shipment — FleetTrack</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f3ef;
            --surface: #ffffff;
            --border: #e2e0d8;
            --text: #1a1a1a;
            --subtle: #6b6b6b;
            --accent: #1a1a2e;
            --accent2: #ff6b35;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
	/*hello*/
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'JetBrains Mono', monospace;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: var(--accent);
            padding: 20px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .wordmark {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 20px;
            color: #00e5ff;
            letter-spacing: -0.5px;
        }
        .wordmark span { color: #fff; font-weight: 400; font-size: 13px; margin-left: 10px; }

        /* Search bar */
        .search-wrap {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 24px 40px;
        }
        .search-label {
            font-size: 11px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--subtle);
            margin-bottom: 10px;
        }
        .search-row {
            display: flex;
            gap: 10px;
            max-width: 560px;
        }
        .search-input {
            flex: 1;
            border: 2px solid var(--border);
            background: var(--bg);
            padding: 11px 16px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            color: var(--text);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            outline: none;
            transition: border-color 0.15s;
        }
        .search-input:focus { border-color: var(--accent); }
        .search-btn {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 11px 24px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            cursor: pointer;
            transition: opacity 0.15s;
        }
        .search-btn:hover { opacity: 0.85; }

        /* Main layout */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 40px;
        }

        /* Not found */
        .not-found {
            text-align: center;
            padding: 80px 20px;
            color: var(--subtle);
        }
        .not-found .code { font-size: 48px; font-weight: 800; color: var(--border); font-family: 'Syne', sans-serif; }

        /* Shipment card */
        .shipment-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 20px;
            align-items: start;
        }

        .info-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        .info-card-header {
            background: var(--accent);
            padding: 20px 22px;
            color: #fff;
        }
        .tracking-code-label { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; opacity: 0.6; }
        .tracking-code-value { font-size: 22px; font-weight: 700; margin-top: 4px; letter-spacing: 0.05em; color: #00e5ff; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-top: 12px;
        }
        .status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .status-transit   { background: #1a2a4a; color: #60a5fa; }
        .status-delayed   { background: #3a2a10; color: #f59e0b; }
        .status-delivered { background: #14291e; color: #22c55e; }
        .status-pending   { background: #2a2a2a; color: #9ca3af; }

        .info-body { padding: 20px 22px; }
        .info-row {
            display: flex;
            flex-direction: column;
            gap: 3px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .info-row:last-child { border-bottom: none; }
        .info-row-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--subtle); }
        .info-row-value { font-size: 13px; color: var(--text); line-height: 1.5; }

        /* Map */
        .map-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        .map-card-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }
        .map-card-title { font-family: 'Syne', sans-serif; font-weight: 700; }
        #client-map { height: 500px; }

        /* Live badge */
        .live-dot {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            color: var(--success);
        }
        .live-dot::before {
            content: '';
            width: 7px; height: 7px;
            background: var(--success);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        @media (max-width: 768px) {
            .container { padding: 20px; }
            .search-wrap { padding: 20px; }
            .header { padding: 16px 20px; }
            .shipment-layout { grid-template-columns: 1fr; }
            #client-map { height: 340px; }
        }
    </style>
</head>
<body>

<header class="header">
    <div>
        <div class="wordmark">FleetTrack <span>/ Shipment Tracker</span></div>
    </div>
</header>

<div class="search-wrap">
    <div class="search-label">Enter your tracking code</div>
    <form method="GET" action="/track">
        <div class="search-row">
            <input class="search-input" name="code" placeholder="e.g. AB12CD34EF"
                   value="{{ $code ?? '' }}" autocomplete="off" maxlength="10">
            <button class="search-btn" type="submit">Track →</button>
        </div>
    </form>
</div>

<div class="container">

    @if(! $code)
        {{-- Landing --}}
        <div class="not-found">
            <div class="code">📦</div>
            <p style="margin-top:16px; font-size:14px;">Enter your 10-character tracking code above to see your shipment status.</p>
        </div>

    @elseif(! $shipment)
        {{-- Not found --}}
        <div class="not-found">
            <div class="code">404</div>
            <p style="margin-top:16px;">No shipment found for code <strong>{{ strtoupper($code) }}</strong>.</p>
            <p style="margin-top:8px; font-size:11px; color:var(--subtle);">Check the code and try again, or contact the sender.</p>
        </div>

    @else
        {{-- Shipment found --}}
        <div class="shipment-layout">

            {{-- Info panel --}}
            <div class="info-card">
                <div class="info-card-header">
                    <div class="tracking-code-label">Tracking Code</div>
                    <div class="tracking-code-value">{{ $shipment->tracking_code }}</div>
                    <div>
                        @php
                            $badgeClass = match($shipment->status) {
                                'in_transit' => 'status-transit',
                                'delayed'    => 'status-delayed',
                                'delivered'  => 'status-delivered',
                                default      => 'status-pending',
                            };
                        @endphp
                        <span class="status-badge {{ $badgeClass }}" id="status-badge">
                            {{ str_replace('_', ' ', $shipment->status) }}
                        </span>
                    </div>
                </div>
                <div class="info-body">
                    <div class="info-row">
                        <span class="info-row-label">Recipient</span>
                        <span class="info-row-value">{{ $shipment->client_name }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">From</span>
                        <span class="info-row-value">{{ $shipment->origin_address }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">To</span>
                        <span class="info-row-value">{{ $shipment->destination_address }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Expected Delivery</span>
                        <span class="info-row-value">{{ $shipment->expected_delivery_at->format('d M Y, H:i') }}</span>
                    </div>
                    @if($shipment->actual_delivery_at)
                    <div class="info-row">
                        <span class="info-row-label">Delivered At</span>
                        <span class="info-row-value" style="color: var(--success);">
                            {{ $shipment->actual_delivery_at->format('d M Y, H:i') }}
                        </span>
                    </div>
                    @endif
                    <div class="info-row">
                        <span class="info-row-label">Vehicle Speed</span>
                        <span class="info-row-value" id="live-speed">
                            {{ $shipment->vehicle->latestPosition ? number_format($shipment->vehicle->latestPosition->speed_kmh, 1) . ' km/h' : '—' }}
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Last Updated</span>
                        <span class="info-row-value" id="live-time">
                            {{ $shipment->vehicle->latestPosition?->recorded_at?->diffForHumans() ?? 'No data' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Map --}}
            <div class="map-card">
                <div class="map-card-header">
                    <span class="map-card-title">Live Location</span>
                    <span class="live-dot" id="live-indicator">LIVE</span>
                </div>
                <div id="client-map"></div>
            </div>

        </div>
    @endif

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

@if($shipment)
<script>
const TRACKING_CODE = '{{ $shipment->tracking_code }}';
const DEST_LAT = {{ $shipment->destination_lat }};
const DEST_LNG = {{ $shipment->destination_lng }};

const map = L.map('client-map').setView(
    [{{ $shipment->vehicle->latestPosition?->latitude ?? $shipment->destination_lat }},
     {{ $shipment->vehicle->latestPosition?->longitude ?? $shipment->destination_lng }}],
    13
);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

// Destination marker (flag)
L.marker([DEST_LAT, DEST_LNG], {
    icon: L.divIcon({
        className: '',
        html: `<div style="font-size:24px; transform:translate(-12px,-24px);">🏁</div>`,
        iconSize: [24, 24],
    })
}).addTo(map).bindPopup('<b>Destination</b>');

// Vehicle marker
const vehicleIcon = L.divIcon({
    className: '',
    html: `<div style="width:16px;height:16px;border-radius:50%;background:#ff6b35;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.3);"></div>`,
    iconSize: [16, 16], iconAnchor: [8, 8],
});

@if($shipment->vehicle->latestPosition)
let vehicleMarker = L.marker(
    [{{ $shipment->vehicle->latestPosition->latitude }}, {{ $shipment->vehicle->latestPosition->longitude }}],
    { icon: vehicleIcon }
).addTo(map).bindPopup('Your shipment is here');
@else
let vehicleMarker = null;
@endif

async function pollStatus() {
    try {
        const res  = await fetch(`/api/track/${TRACKING_CODE}/status`);
        const data = await res.json();

        if (data.vehicle) {
            const latlng = [data.vehicle.latitude, data.vehicle.longitude];
            if (vehicleMarker) {
                vehicleMarker.setLatLng(latlng);
            } else {
                vehicleMarker = L.marker(latlng, { icon: vehicleIcon }).addTo(map).bindPopup('Your shipment is here');
            }
            map.panTo(latlng);

            document.getElementById('live-speed').textContent = (data.vehicle.speed_kmh?.toFixed(1) ?? 0) + ' km/h';
            document.getElementById('live-time').textContent  = timeAgo(data.vehicle.recorded_at);
        }

        // Update status badge
        const badge = document.getElementById('status-badge');
        const classMap = { 'in_transit':'status-transit', 'delayed':'status-delayed', 'delivered':'status-delivered', 'pending':'status-pending' };
        badge.className = 'status-badge ' + (classMap[data.status] ?? 'status-pending');
        badge.textContent = data.status.replace('_', ' ');

    } catch(e) { console.error(e); }
}

function timeAgo(iso) {
    if (!iso) return 'Unknown';
    const diff = Math.floor((Date.now() - new Date(iso)) / 1000);
    if (diff < 60)   return diff + 's ago';
    if (diff < 3600) return Math.floor(diff/60) + 'm ago';
    return Math.floor(diff/3600) + 'h ago';
}

pollStatus();
setInterval(pollStatus, 10000); // poll every 10s
</script>
@endif

</body>
</html>
