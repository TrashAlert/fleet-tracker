@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

{{-- ── Command bar: title + Live/History mode ──────────────────────────── --}}
<div class="cmd-bar">
    <span class="cmd-title">Fleet Command</span>
    <div class="seg">
        <button type="button" id="seg-live" class="seg-btn active" onclick="switchToLive()">Live</button>
        <button type="button" id="seg-history" class="seg-btn" onclick="switchToHistory()">History</button>
    </div>
</div>

{{-- ── Stat chips (live mode) ──────────────────────────────────────────── --}}
<div class="chip-row" id="chip-row">
    <button type="button" class="chip" onclick="setOnlineFilter(false)" title="Show all vehicles">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="1" y="6" width="15" height="10" rx="1"/><path d="M16 10h3l3 3v3h-6"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>
        <span class="chip-val mono">{{ $vehicles->count() }}</span>
        <span class="chip-label">vehicles</span>
    </button>
    <button type="button" class="chip" id="chip-online" onclick="toggleOnlineFilter()" title="Filter to online vehicles">
        <span class="fdot fdot-on"></span>
        <span class="chip-val mono" id="stat-online">—</span>
        <span class="chip-label">online</span>
    </button>
    <a href="{{ route('fleet.shipments') }}" class="chip" title="Open shipments">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent2)" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        <span class="chip-val mono" id="stat-shipments">{{ $vehicles->sum(fn($v) => $v->activeShipments->count()) }}</span>
        <span class="chip-label">active deliveries</span>
    </a>
    <button type="button" id="chip-alerts" class="chip chip-alerts {{ $unreadAlerts->count() > 0 ? 'chip-danger' : '' }}" onclick="openAlertsPanel()" title="Show alerts">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        <span class="chip-val mono" id="stat-alerts">{{ $unreadAlerts->count() }}</span>
        <span class="chip-label">alerts</span>
    </button>
    <span style="flex:1"></span>
    <a href="{{ route('fleet.shipments') }}" class="chip chip-accent">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span class="chip-label" style="color:var(--accent); font-weight:600;">New shipment</span>
    </a>
</div>

{{-- ── History toolbar (swaps in for the chips) ────────────────────────── --}}
<div class="chip-row" id="history-toolbar" style="display:none;">
    <select id="history-vehicle" class="history-input">
        <option value="">Select vehicle…</option>
        @foreach($vehicles as $v)
            <option value="{{ $v->id }}">{{ $v->plate_number }} — {{ $v->name }}</option>
        @endforeach
    </select>
    <input type="date" id="history-date" class="history-input"
           value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}">
    <button type="button" onclick="loadTripHistory()" class="chip chip-accent" style="cursor:pointer;">
        <span class="chip-label" style="color:var(--accent); font-weight:600;">Load trip</span>
    </button>
    <span style="flex:1"></span>
    <button type="button" onclick="switchToLive()" class="chip" style="cursor:pointer;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        <span class="chip-label">Exit history</span>
    </button>
</div>

{{-- ── Map canvas with floating panels ─────────────────────────────────── --}}
<div class="map-canvas">

    {{-- Fleet panel (floating left) --}}
    <div class="float-panel fleet-panel" id="fleet-panel">
        <div class="fp-head">
            <span class="fp-title">Fleet</span>
            <span class="mono fp-sub" id="fleet-online-label">—</span>
        </div>
        <div class="fleet-rows">
            @forelse($vehicles as $v)
            <div class="frow" id="frow-{{ $v->id }}" onclick="focusVehicle({{ $v->id }})">
                <div class="frow-line">
                    <span class="fdot {{ $v->isOffline() ? 'fdot-off' : 'fdot-on' }}" id="dot-{{ $v->id }}"></span>
                    <span class="frow-name">{{ $v->name }}</span>
                    <span class="mono frow-speed" id="speed-{{ $v->id }}">
                        {{ $v->latestPosition ? number_format($v->latestPosition->speed_kmh, 1).' km/h' : '—' }}
                    </span>
                </div>
                <div class="frow-line frow-meta">
                    <span class="mono">{{ $v->plate_number }}</span>
                    <span style="flex:1"></span>
                    <span class="frow-driver">{{ $v->driver_name ?? '—' }}</span>
                </div>
                <div class="frow-line frow-meta">
                    <span id="state-{{ $v->id }}" class="{{ $v->isOffline() ? 'frow-state-off' : 'frow-state-on' }}">{{ $v->isOffline() ? 'offline' : 'online' }}</span>
                    <span>&middot;</span>
                    <span id="seen-{{ $v->id }}">{{ $v->latestPosition ? $v->latestPosition->recorded_at->diffForHumans() : 'never' }}</span>
                    <span style="flex:1"></span>
                    @if($v->activeShipments->count() === 1)
                        <span class="mono" style="color:var(--accent2);">{{ $v->activeShipments->first()->tracking_code }}</span>
                    @elseif($v->activeShipments->count() > 1)
                        <span style="color:var(--accent2);">{{ $v->activeShipments->count() }} deliveries</span>
                    @endif
                </div>
            </div>
            @empty
            <div style="padding:24px 12px; text-align:center; color:var(--subtle); font-size:12px;">No active vehicles</div>
            @endforelse
        </div>
        <a href="{{ route('fleet.vehicles') }}" class="fp-foot">
            All vehicles
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
    </div>

    {{-- Map --}}
    <div class="map-wrap">
        <div id="fleet-map"></div>

        {{-- Live status pill --}}
        <div class="live-pill" id="live-pill">
            <span class="fdot fdot-on"></span>
            <span style="color:var(--subtle);">Live</span>
            <span class="mono" style="color:var(--subtle);" id="live-pill-time">—</span>
        </div>

        {{-- Destination route strip (click a vehicle; hidden until then) --}}
        <div id="route-strip" class="hist-summary" style="display:none;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--accent2)" stroke-width="2" style="flex-shrink:0;"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
            <span class="hs-item mono" id="rs-main" style="color:var(--text); font-weight:600;"></span>
            <span class="hs-item mono" id="rs-meta"></span>
            <span style="flex:1"></span>
            <button class="pb-btn" onclick="clearVehicleRoute()" title="Clear route" style="width:24px; height:24px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Trip playback bar (docked above the summary, hidden until a trip loads) --}}
        <div id="playback-bar" class="hist-summary pb-bar" style="display:none;">
            <button type="button" class="pb-btn" id="pb-play" onclick="togglePlay()" title="Play">
                <svg id="pb-icon-play" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="6 3 20 12 6 21"/></svg>
                <svg id="pb-icon-pause" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="display:none;"><rect x="5" y="4" width="5" height="16" rx="1"/><rect x="14" y="4" width="5" height="16" rx="1"/></svg>
            </button>
            <button type="button" class="pb-btn" onclick="restartPlayback()" title="Restart">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="19 20 9 12 19 4" fill="currentColor" stroke="none"/><line x1="5" y1="19" x2="5" y2="5"/></svg>
            </button>
            <input type="range" id="pb-scrub" min="0" max="1000" value="0" oninput="scrubTo(this.value)" aria-label="Trip position">
            <span class="mono pb-readout" id="pb-clock">--:--:--</span>
            <span class="mono pb-readout" id="pb-speed" style="min-width:64px; text-align:right;">— km/h</span>
            <select id="pb-rate" class="pb-rate" onchange="setPlaybackSpeed(this.value)" title="Playback speed">
                <option value="10">10&times;</option>
                <option value="60" selected>60&times;</option>
                <option value="300">300&times;</option>
            </select>
        </div>

        {{-- History summary strip (docked, hidden until a trip loads) --}}
        <div id="history-summary" class="hist-summary" style="display:none;">
            <span class="hs-item mono" id="hs-replay" style="color:var(--accent2);"></span>
            <span class="hs-item">Points <span class="mono" id="hs-points">—</span></span>
            <span class="hs-item">Distance <span class="mono" id="hs-distance">—</span></span>
            <span class="hs-item">Duration <span class="mono" id="hs-duration">—</span></span>
            <span class="hs-item">Avg <span class="mono" id="hs-avg-speed">—</span></span>
            <span class="hs-item">Max <span class="mono" id="hs-max-speed" style="color:var(--danger);">—</span></span>
            <span style="flex:1"></span>
            <span class="hs-leg"><span class="hs-swatch" style="background:#22c55e;"></span>&lt;60</span>
            <span class="hs-leg"><span class="hs-swatch" style="background:#f59e0b;"></span>60–110</span>
            <span class="hs-leg"><span class="hs-swatch" style="background:#ef4444;"></span>&gt;110 km/h</span>
        </div>
    </div>

    {{-- Alerts panel (floating right) --}}
    <div class="float-panel alerts-panel" id="alerts-panel-wrap">
        <div class="fp-head">
            <span class="fp-title">Alerts</span>
            <span class="mono fp-sub" style="color:var(--danger);" id="alert-count-label">{{ $unreadAlerts->count() }} unread</span>
            <button type="button" class="fp-collapse" onclick="closeAlertsPanel()" title="Collapse alerts">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
        <div id="alerts-panel" class="alerts-scroll">
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

    {{-- Collapsed alerts badge (shown when panel is closed) --}}
    <button type="button" class="alerts-fab" id="alerts-fab" style="display:none;" onclick="openAlertsPanel()" title="Show alerts">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        <span class="mono" id="alerts-fab-count">0</span>
    </button>

</div>

@endsection

@push('styles')
<style>
/* ── Map theme (dark filter removed automatically in light mode) ── */
.leaflet-tile-pane    { filter: brightness(0.65) saturate(0.7) hue-rotate(185deg); }
.leaflet-container    { background: #0a0b0e; }
html[data-theme="light"] .leaflet-tile-pane { filter: none; }
html[data-theme="light"] .leaflet-container { background: #dce3e8; }
.vehicle-popup        { font-family: var(--font-mono); font-size: 12px; }
.vehicle-popup strong { color: var(--accent); }

/* ── Command bar ── */
.cmd-bar {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 12px; flex-wrap: wrap;
}
.cmd-title {
    font-family: var(--font-display);
    font-weight: 700; font-size: 14px; letter-spacing: 0.05em;
}

.seg { display: flex; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
.seg-btn {
    background: none; border: none; cursor: pointer;
    padding: 6px 16px; font-size: 11px; font-family: var(--font-display);
    color: var(--subtle);
}
.seg-btn.active { background: rgba(0,229,255,0.12); color: var(--accent); font-weight: 600; }
#seg-history.active { background: rgba(255,107,53,0.14); color: var(--accent2); }
html[data-theme="light"] .seg-btn.active { background: rgba(0,119,182,0.10); }
html[data-theme="light"] #seg-history.active { background: rgba(232,93,47,0.12); }

/* ── Stat chips / toolbar row ── */
.chip-row { display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; align-items: center; }
.chip {
    display: flex; align-items: center; gap: 8px;
    border: 1px solid var(--border); border-radius: 8px;
    background: var(--surface); padding: 8px 12px;
    font-family: var(--font-display); text-decoration: none; color: var(--text);
    cursor: pointer;
}
.chip:hover { border-color: var(--accent); }
.chip-val   { font-size: 15px; font-weight: 500; }
.chip-label { font-size: 11px; color: var(--subtle); }
.chip-danger { border-color: rgba(239,68,68,0.45); background: rgba(239,68,68,0.08); }
.chip-danger .chip-val, .chip-danger .chip-label { color: var(--danger); }
.chip-alerts svg { stroke: var(--subtle); }
.chip-alerts.chip-danger svg { stroke: var(--danger); }
.chip-accent { border-color: rgba(0,229,255,0.4); background: none; }
html[data-theme="light"] .chip-accent { border-color: rgba(0,119,182,0.4); }
.chip-accent svg { stroke: var(--accent); }
.chip.chip-on { border-color: var(--success); background: rgba(34,197,94,0.08); }

.history-input {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text);
    font-family: var(--font-mono); font-size: 11px;
    padding: 8px 10px; outline: none;
}
.history-input:focus { border-color: var(--accent); }
.history-input option { background: var(--surface); }

/* ── Map canvas + floating panels ── */
.map-canvas {
    position: relative;
    z-index: 0; /* stacking context: traps Leaflet's z-1000 controls + the z-700
                   overlays below the layout's drawer (400), backdrop (399) and
                   sticky mobile topbar (300) */
    height: calc(100vh - 235px);
    min-height: 460px;
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}
.map-wrap { position: absolute; inset: 0; }
#fleet-map { position: absolute; inset: 0; height: 100%; }

.float-panel {
    position: absolute; z-index: 700;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 10px; display: flex; flex-direction: column;
    overflow: hidden;
}
.fleet-panel  { left: 12px; top: 12px; bottom: 12px; width: 236px; }
.alerts-panel { right: 12px; top: 12px; width: 230px; max-height: calc(100% - 24px); }

.fp-head {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 12px 8px; flex-shrink: 0;
}
.fp-title {
    font-size: 10px; font-weight: 700; letter-spacing: 0.1em;
    text-transform: uppercase; color: var(--subtle); flex: 1;
}
.fp-sub { font-size: 10px; color: var(--subtle); }
.fp-collapse {
    background: none; border: none; cursor: pointer;
    color: var(--subtle); padding: 0 0 0 4px; display: flex; align-items: center;
}
.fp-collapse:hover { color: var(--text); }
.fp-foot {
    padding: 9px 12px; border-top: 1px solid var(--border);
    font-size: 11px; color: var(--accent); text-decoration: none;
    display: flex; align-items: center; gap: 4px; flex-shrink: 0;
}

.fleet-rows { flex: 1; overflow-y: auto; }
.frow { padding: 8px 12px; border-top: 1px solid var(--border); cursor: pointer; }
.frow:hover  { background: rgba(0,229,255,0.04); }
.frow-active { background: rgba(0,229,255,0.07); border-left: 2px solid var(--accent); padding-left: 10px; }
html[data-theme="light"] .frow:hover  { background: rgba(0,119,182,0.05); }
html[data-theme="light"] .frow-active { background: rgba(0,119,182,0.08); }
.frow-dim { opacity: 0.35; }
.frow-line { display: flex; align-items: center; gap: 6px; }
.frow-name { font-size: 12px; font-weight: 600; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.frow-speed { font-size: 11px; color: var(--accent); flex-shrink: 0; }
.frow-meta { margin-top: 3px; padding-left: 12px; font-size: 10px; color: var(--subtle); }
.frow-driver { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 120px; }
.frow-state-on  { color: var(--success); }
.frow-state-off { color: var(--danger); }

.fdot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; display: inline-block; }
.fdot-on  { background: var(--success); }
.fdot-off { background: var(--danger); }

.alerts-scroll { overflow-y: auto; padding: 0 12px; }

.alerts-fab {
    position: absolute; right: 12px; top: 12px; z-index: 700;
    display: flex; align-items: center; gap: 7px;
    background: var(--surface); border: 1px solid rgba(239,68,68,0.45);
    border-radius: 999px; padding: 8px 13px;
    color: var(--danger); font-size: 12px; cursor: pointer;
}

.live-pill {
    position: absolute; left: 260px; bottom: 12px; z-index: 700;
    display: flex; align-items: center; gap: 7px;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 999px; padding: 6px 12px; font-size: 11px;
}

.hist-summary {
    position: absolute; left: 12px; right: 254px; bottom: 12px; z-index: 700;
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 10px; padding: 9px 14px;
}
.hs-item { font-size: 11px; color: var(--subtle); }
.hs-item .mono { color: var(--text); }
.hs-leg { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--subtle); }
.hs-swatch { width: 16px; height: 3px; border-radius: 2px; display: inline-block; }

/* ── Trip playback bar (docked just above the summary strip) ── */
.pb-bar { bottom: 60px; gap: 10px; flex-wrap: nowrap; }
.pb-btn {
    background: var(--muted); border: none; border-radius: 6px;
    width: 28px; height: 28px; flex-shrink: 0; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    color: var(--text);
}
.pb-btn:hover { color: var(--accent); }
.pb-readout { font-size: 11px; color: var(--text); flex-shrink: 0; }
.pb-rate {
    background: var(--muted); border: 1px solid var(--border); border-radius: 6px;
    color: var(--text); font-family: var(--font-mono); font-size: 11px;
    padding: 4px 6px; outline: none; flex-shrink: 0; cursor: pointer;
}
#pb-scrub {
    flex: 1; min-width: 80px; height: 4px; cursor: pointer;
    -webkit-appearance: none; appearance: none;
    background: var(--muted); border-radius: 2px; outline: none;
}
#pb-scrub::-webkit-slider-thumb {
    -webkit-appearance: none; appearance: none;
    width: 14px; height: 14px; border-radius: 50%;
    background: var(--accent); border: none; cursor: pointer;
}
#pb-scrub::-moz-range-thumb {
    width: 14px; height: 14px; border-radius: 50%;
    background: var(--accent); border: none; cursor: pointer;
}

/* ── Alert icon colours (unchanged from previous dashboard) ── */
.alert-icon.overspeed svg { stroke: var(--danger); }
.alert-icon.delay     svg { stroke: var(--warning); }
.alert-icon.offline   svg { stroke: var(--subtle); }
.alert-icon.geofence  svg { stroke: var(--accent); }

/* ── Mobile: panels leave the map and stack ── */
@media (max-width: 900px) {
    .map-canvas { height: auto; min-height: 0; border: none; border-radius: 0; overflow: visible; }
    .map-wrap   { position: relative; inset: auto; height: 420px; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
    .float-panel { position: static; margin-bottom: 12px; }
    .fleet-panel { width: auto; }
    .fleet-rows  { max-height: 230px; }
    .alerts-panel { width: auto; max-height: 320px; margin-top: 12px; margin-bottom: 0; }
    .alerts-fab  { display: none !important; }
    .live-pill   { left: 12px; }
    .hist-summary { right: 12px; }
}
</style>
@endpush

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// ── Map setup ─────────────────────────────────────────────────────────────
const map = L.map('fleet-map', { zoomControl: false, attributionControl: false })
    .setView([2.1896, 102.2501], 10);
L.control.zoom({ position: 'bottomright' }).addTo(map);

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

const markers  = {};
const vehState = {};          // id -> is_offline (for the online filter)
let onlineFilter = false;
let focusedVehicle = null;

// ── Live positions — poll every 5s ────────────────────────────────────────
async function fetchLivePositions() {
    if (historyMode) return;
    try {
        const res  = await fetch('{{ route("fleet.api.live") }}', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        let onlineCount = 0;

        data.forEach(v => {
            vehState[v.id] = !!v.is_offline;
            if (!v.is_offline) onlineCount++;

            // Fleet panel row (updates even for vehicles with no GPS fix yet)
            const speedEl = document.getElementById(`speed-${v.id}`);
            const dotEl   = document.getElementById(`dot-${v.id}`);
            const seenEl  = document.getElementById(`seen-${v.id}`);
            const stateEl = document.getElementById(`state-${v.id}`);
            if (speedEl) speedEl.textContent = v.latitude ? (v.speed_kmh ?? 0).toFixed(1) + ' km/h' : '—';
            if (dotEl)   dotEl.className = 'fdot ' + (v.is_offline ? 'fdot-off' : 'fdot-on');
            if (seenEl)  seenEl.textContent = v.recorded_at ? timeAgo(v.recorded_at) : 'never';
            if (stateEl) {
                stateEl.textContent = v.is_offline ? 'offline' : 'online';
                stateEl.className   = v.is_offline ? 'frow-state-off' : 'frow-state-on';
            }

            if (!v.latitude || !v.longitude) return;
            const latlng = [v.latitude, v.longitude];

            if (markers[v.id]) {
                markers[v.id].setLatLng(latlng).setIcon(makeIcon(v.is_offline));
            } else {
                markers[v.id] = L.marker(latlng, { icon: makeIcon(v.is_offline) })
                    .addTo(map)
                    .bindPopup('')
                    .on('click', () => focusVehicle(v.id)); // also shows its destination route
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
        });

        applyOnlineFilter();

        const total = document.querySelectorAll('.frow').length;
        document.getElementById('stat-online').textContent       = onlineCount;
        document.getElementById('fleet-online-label').textContent = onlineCount + '/' + total + ' online';
        document.getElementById('live-pill-time').textContent =
            'updated ' + new Date().toLocaleTimeString();

    } catch(e) { console.error('Live fetch error:', e); }
}

function timeAgo(isoString) {
    const diff = Math.floor((Date.now() - new Date(isoString)) / 1000);
    if (diff < 60)   return diff + 's ago';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    return Math.floor(diff / 3600) + 'h ago';
}

// ── Online filter (chip toggle) ───────────────────────────────────────────
function applyOnlineFilter() {
    Object.keys(markers).forEach(id => {
        const hide = onlineFilter && vehState[id];
        if (hide) {
            if (map.hasLayer(markers[id])) map.removeLayer(markers[id]);
        } else if (!historyMode) {
            if (!map.hasLayer(markers[id])) markers[id].addTo(map);
        }
        const row = document.getElementById(`frow-${id}`);
        if (row) row.classList.toggle('frow-dim', onlineFilter && vehState[id]);
    });
    document.getElementById('chip-online').classList.toggle('chip-on', onlineFilter);
}

function toggleOnlineFilter() { setOnlineFilter(!onlineFilter); }
function setOnlineFilter(on)  { onlineFilter = on; applyOnlineFilter(); }

// ── Fleet panel row → focus vehicle on map ────────────────────────────────
function focusVehicle(id) {
    if (historyMode) return;
    document.querySelectorAll('.frow').forEach(r => r.classList.remove('frow-active'));
    document.getElementById(`frow-${id}`)?.classList.add('frow-active');
    focusedVehicle = id;

    const m = markers[id];
    if (m && map.hasLayer(m)) {
        map.flyTo(m.getLatLng(), Math.max(map.getZoom(), 15), { duration: 0.6 });
        m.openPopup();
    }
    showVehicleRoute(id);
}

// ── Alerts panel open/collapse ────────────────────────────────────────────
function openAlertsPanel() {
    document.getElementById('alerts-panel-wrap').style.display = 'flex';
    document.getElementById('alerts-fab').style.display = 'none';
    if (window.innerWidth <= 900) {
        document.getElementById('alerts-panel-wrap').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}
function closeAlertsPanel() {
    document.getElementById('alerts-panel-wrap').style.display = 'none';
    document.getElementById('alerts-fab').style.display = 'flex';
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
    document.getElementById('stat-alerts').textContent        = count;
    document.getElementById('alert-count-label').textContent  = count + ' unread';
    document.getElementById('alerts-fab-count').textContent   = count;
    // Red glow only while there are unread alerts
    document.getElementById('chip-alerts').classList.toggle('chip-danger', count > 0);

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

// ── Mode switching (segmented control) ────────────────────────────────────
function setSeg(mode) {
    document.getElementById('seg-live').classList.toggle('active', mode === 'live');
    document.getElementById('seg-history').classList.toggle('active', mode === 'history');
    document.getElementById('chip-row').style.display        = mode === 'live' ? 'flex' : 'none';
    document.getElementById('history-toolbar').style.display = mode === 'live' ? 'none' : 'flex';
}

function switchToHistory() {
    setSeg('history');
    // History mode proper starts when a trip is loaded; until then the live
    // map keeps ticking underneath so the switch is non-destructive.
}

function switchToLive() {
    setSeg('live');
    exitHistoryMode();
}

// ── Destination route (click a vehicle → where is it heading?) ────────────
// Fetched on demand only — one cached OSRM call per click, plus a 20s refresh
// while a vehicle stays selected. Never part of the 5s live poll.
let routeView = { vehicleId: null, layers: [], timer: null };

async function showVehicleRoute(id) {
    try {
        const res = await fetch(`/fleet/api/vehicle/${id}/route`, {
            headers: { 'Accept': 'application/json' },
        });
        if (!res.ok) return;
        const data = await res.json();

        clearVehicleRoute(); // one route at a time; also stops the old timer

        const strip = document.getElementById('route-strip');
        const main  = document.getElementById('rs-main');
        const meta  = document.getElementById('rs-meta');

        if (!data.available) {
            main.textContent = data.reason || 'No delivery in progress.';
            meta.textContent = '';
            strip.style.display = 'flex';
            routeView.vehicleId = id;
            return;
        }

        routeView.vehicleId = id;

        // Destination marker (orange — matches the panel's tracking-code accent)
        const destIcon = L.divIcon({
            className: '',
            html: `<div style="width:16px;height:16px;border-radius:50%;background:#ff6b35;border:3px solid #fff;box-shadow:0 0 8px #ff6b3599;"></div>`,
            iconSize: [16, 16], iconAnchor: [8, 8],
        });
        routeView.layers.push(
            L.marker([data.destination.lat, data.destination.lng], { icon: destIcon })
                .addTo(map)
                .bindPopup(`<b>${data.tracking_code}</b><br>${data.destination.address}`)
        );

        // Road geometry when OSRM answered; dashed straight line as the fallback.
        const vm = markers[id];
        if (data.geometry && data.geometry.length > 1) {
            routeView.layers.push(L.polyline(data.geometry, { color: '#ffffff', weight: 7, opacity: 0.9, lineJoin: 'round', lineCap: 'round', smoothFactor: 0.5 }).addTo(map));
            routeView.layers.push(L.polyline(data.geometry, { color: '#2563eb', weight: 4, opacity: 0.85, lineJoin: 'round', lineCap: 'round', smoothFactor: 0.5 }).addTo(map));
        } else if (vm) {
            routeView.layers.push(L.polyline(
                [vm.getLatLng(), [data.destination.lat, data.destination.lng]],
                { color: '#ff6b35', weight: 3, opacity: 0.7, dashArray: '8 8' }
            ).addTo(map));
        }

        main.textContent = `${data.tracking_code} · ${data.client_name}`;
        meta.textContent = (data.distance_km != null
                ? `${data.distance_km} km · ~${data.eta_minutes} min by road`
                : 'road route unavailable — straight line shown')
            + (data.expected_at ? ` · due ${data.expected_at}` : '');
        strip.style.display = 'flex';

        // Keep the line/ETA fresh while selected (server caches for 15s).
        routeView.timer = setInterval(() => {
            if (routeView.vehicleId === id && !historyMode) showVehicleRoute(id);
        }, 20000);
    } catch (e) {
        console.error('Route fetch error:', e);
    }
}

function clearVehicleRoute() {
    if (routeView.timer) { clearInterval(routeView.timer); routeView.timer = null; }
    routeView.layers.forEach(l => map.removeLayer(l));
    routeView.layers = [];
    routeView.vehicleId = null;
    document.getElementById('route-strip').style.display = 'none';
}

// ── Trip History ──────────────────────────────────────────────────────────
let historyMode    = false;
let historyLayers  = [];
let livePollTimer  = null;

function speedColor(kmh) {
    if (kmh > 110) return '#ef4444';
    if (kmh >= 60) return '#f59e0b';
    return '#22c55e';
}

function clearHistoryLayers() {
    teardownPlayback(); // cancel the RAF loop before its marker disappears
    historyLayers.forEach(l => map.removeLayer(l));
    historyLayers = [];
}

// ── Trip playback ─────────────────────────────────────────────────────────
// Animates a marker along the loaded day's points, time-proportionally:
// position is lerped between the two GPS fixes bracketing the current
// trip-time, so pacing mirrors how the vehicle actually moved. Idle gaps
// (parked / offline) are capped at 60s of trip-time so the marker never
// crawls through a 3-hour lunch stop.
const playback = {
    points: [], times: [], totalMs: 0,
    t: 0, cursor: 0, playing: false, speed: 60,
    raf: null, lastFrame: null, marker: null,
};

function initPlayback(points) {
    if (points.length < 2) return; // a single fix — nothing to animate

    const IDLE_CAP_MS = 60000;
    const times = [0];
    for (let i = 1; i < points.length; i++) {
        const dt = new Date(points[i].recorded_at) - new Date(points[i - 1].recorded_at);
        times.push(times[i - 1] + Math.min(Math.max(dt, 0), IDLE_CAP_MS));
    }

    playback.points  = points;
    playback.times   = times;
    playback.totalMs = times[times.length - 1];
    playback.t       = 0;
    playback.cursor  = 0;
    playback.playing = false;
    playback.speed   = parseInt(document.getElementById('pb-rate').value, 10) || 60;

    playback.marker = L.marker([points[0].latitude, points[0].longitude], {
        icon: L.divIcon({
            className: '',
            html: `<div style="width:18px;height:18px;border-radius:50%;background:#ff6b35;border:3px solid #fff;box-shadow:0 0 10px #ff6b3599;"></div>`,
            iconSize: [18, 18], iconAnchor: [9, 9],
        }),
        zIndexOffset: 1000, // ride above the start/end dots
    }).addTo(map);
    historyLayers.push(playback.marker); // cleaned up with the rest of the trip

    document.getElementById('pb-scrub').value = 0;
    setPlayIcon(false);
    renderPlaybackFrame();
    document.getElementById('playback-bar').style.display = 'flex';
}

function teardownPlayback() {
    if (playback.raf) { cancelAnimationFrame(playback.raf); playback.raf = null; }
    playback.playing = false;
    playback.marker  = null; // the map layer itself is removed via historyLayers
    playback.points  = [];
    playback.times   = [];
    document.getElementById('playback-bar').style.display = 'none';
    setPlayIcon(false);
}

function setPlayIcon(playing) {
    document.getElementById('pb-icon-play').style.display  = playing ? 'none' : 'block';
    document.getElementById('pb-icon-pause').style.display = playing ? 'block' : 'none';
    document.getElementById('pb-play').title = playing ? 'Pause' : 'Play';
}

function togglePlay() {
    if (!playback.marker) return;

    if (playback.playing) {
        playback.playing = false;
        if (playback.raf) { cancelAnimationFrame(playback.raf); playback.raf = null; }
    } else {
        if (playback.t >= playback.totalMs) { playback.t = 0; playback.cursor = 0; } // play again from the end
        playback.playing   = true;
        playback.lastFrame = null;
        playback.raf = requestAnimationFrame(playbackFrame);
    }
    setPlayIcon(playback.playing);
}

function restartPlayback() {
    if (!playback.marker) return;
    playback.t = 0;
    playback.cursor = 0;
    renderPlaybackFrame(); // if playing, the loop continues from the start
}

function scrubTo(sliderValue) {
    if (!playback.marker) return;
    playback.t = (sliderValue / 1000) * playback.totalMs;
    renderPlaybackFrame(true); // true: don't fight the thumb the user is dragging
}

function setPlaybackSpeed(v) {
    playback.speed = parseInt(v, 10) || 60;
}

function playbackFrame(now) {
    if (!playback.playing) return;

    if (playback.lastFrame !== null) {
        playback.t += (now - playback.lastFrame) * playback.speed;
    }
    playback.lastFrame = now;

    if (playback.t >= playback.totalMs) {
        playback.t = playback.totalMs;
        renderPlaybackFrame();
        playback.playing = false;
        playback.raf = null;
        setPlayIcon(false);
        return;
    }

    renderPlaybackFrame();
    playback.raf = requestAnimationFrame(playbackFrame);
}

function renderPlaybackFrame(skipSlider = false) {
    const { points, times } = playback;
    if (!playback.marker || points.length < 2) return;

    // Advance the cursor to the segment bracketing t (O(1) amortized while
    // playing; restarts from 0 after a backwards scrub).
    let i = playback.cursor;
    if (times[i] > playback.t) i = 0;
    while (i < times.length - 2 && times[i + 1] <= playback.t) i++;
    playback.cursor = i;

    const a = points[i], b = points[i + 1];
    const span = times[i + 1] - times[i];
    const f = span > 0 ? Math.min(Math.max((playback.t - times[i]) / span, 0), 1) : 1;
    const lat = Number(a.latitude) + (Number(b.latitude) - Number(a.latitude)) * f;
    const lng = Number(a.longitude) + (Number(b.longitude) - Number(a.longitude)) * f;

    playback.marker.setLatLng([lat, lng]);
    map.panInside([lat, lng], { padding: [40, 40] });

    // Readouts: wall-clock interpolated between the real fix timestamps;
    // speed (and its color) from the segment's end fix, like the polyline.
    const ta = new Date(a.recorded_at).getTime();
    const tb = new Date(b.recorded_at).getTime();
    document.getElementById('pb-clock').textContent =
        new Date(ta + (tb - ta) * f).toLocaleTimeString('en-GB');

    const kmh = b.speed_kmh ?? 0;
    const speedEl = document.getElementById('pb-speed');
    speedEl.textContent = Number(kmh).toFixed(0) + ' km/h';
    speedEl.style.color = speedColor(kmh);

    if (!skipSlider) {
        document.getElementById('pb-scrub').value = playback.totalMs > 0
            ? Math.round((playback.t / playback.totalMs) * 1000)
            : 0;
    }
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

        enterHistoryMode();
        clearHistoryLayers();

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

        const bounds = points.map(p => [p.latitude, p.longitude]);
        map.fitBounds(bounds, { padding: [40, 40] });

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
        document.getElementById('history-summary').style.display = 'flex';

        const vSel = document.getElementById('history-vehicle');
        document.getElementById('hs-replay').textContent =
            vSel.options[vSel.selectedIndex].text + ' \u00b7 ' + date;

        initPlayback(points);

    } catch(e) {
        console.error('Trip history error:', e);
        alert('Failed to load trip history.');
    }
}

function enterHistoryMode() {
    if (historyMode) return;
    historyMode = true;

    clearVehicleRoute(); // live-mode overlay — never mixes with history layers
    if (livePollTimer) { clearInterval(livePollTimer); livePollTimer = null; }

    Object.values(markers).forEach(m => map.removeLayer(m));

    document.getElementById('fleet-panel').style.display = 'none';
    document.getElementById('live-pill').style.display   = 'none';
}

function exitHistoryMode() {
    // Idempotent: safe to call when already live (segmented control).
    if (livePollTimer) { clearInterval(livePollTimer); livePollTimer = null; }

    const wasHistory = historyMode;
    historyMode = false;

    if (wasHistory) {
        clearHistoryLayers();
        Object.values(markers).forEach(m => m.addTo(map));
        applyOnlineFilter();

        document.getElementById('fleet-panel').style.display = 'flex';
        document.getElementById('live-pill').style.display   = 'flex';
        document.getElementById('history-summary').style.display = 'none';
        document.getElementById('history-vehicle').value = '';
    }

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
