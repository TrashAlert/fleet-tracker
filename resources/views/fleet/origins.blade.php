@extends('layouts.app')
@section('title', 'Warehouse Locations')

@section('content')

{{-- ── Header ──────────────────────────────────────────────────────────── --}}
<div class="wh-head">
    <div>
        <div class="wh-title">Warehouse Locations</div>
        <div class="wh-subtitle">Preset pickup points used when creating shipments</div>
    </div>
    <span style="flex:1"></span>
    <div class="chip"><span class="chip-val mono">{{ $origins->count() }}</span><span class="chip-label">total</span></div>
    <div class="chip"><span class="fdot fdot-on"></span><span class="chip-val mono">{{ $origins->where('is_active', true)->count() }}</span><span class="chip-label">active</span></div>
    <button onclick="openModal()" class="chip chip-accent" type="button" style="cursor:pointer;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span class="chip-label" style="color:var(--accent); font-weight:600;">Add Warehouse</span>
    </button>
</div>

{{-- ── Map overview ────────────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <span class="card-title">Location Overview</span>
        <span style="font-size:11px; color:var(--subtle);">active warehouses only &middot; click a row below to locate</span>
    </div>
    <div class="wh-map-wrap">
        <div id="originsMap" style="position:absolute; inset:0;"></div>
    </div>
</div>

{{-- ── Warehouses table ────────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">All Warehouses</span>
        <span style="font-size:11px; color:var(--subtle);">{{ $origins->count() }} total</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Coordinates</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th style="width:90px; text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($origins as $origin)
                <tr class="wh-row" onclick="focusWarehouse({{ $origin->id }})" @if($origin->notes) title="{{ $origin->notes }}" @endif>
                    <td style="font-weight:600;">{{ $origin->name }}</td>
                    <td style="font-size:12px; color:var(--subtle); max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        {{ $origin->address }}
                    </td>
                    <td class="mono" style="font-size:11px; color:var(--subtle);">
                        {{ number_format($origin->latitude, 6) }}, {{ number_format($origin->longitude, 6) }}
                    </td>
                    <td style="font-size:12px;">
                        {{ $origin->contact_name ?? '—' }}
                        @if($origin->contact_phone)
                            <div style="font-size:10px; color:var(--subtle);">{{ $origin->contact_phone }}</div>
                        @endif
                    </td>
                    <td>
                        @if($origin->is_active)
                            <span class="pill pill-online">Active</span>
                        @else
                            <span class="pill pill-offline">Inactive</span>
                        @endif
                    </td>
                    <td style="text-align:center;" onclick="event.stopPropagation()">
                        <div style="display:flex; gap:6px; justify-content:center;">
                            <button class="wh-icon-btn" title="Edit" aria-label="Edit {{ $origin->name }}"
                                onclick="openEditModal({{ $origin->id }}, {{ json_encode($origin->only('name','address','latitude','longitude','contact_name','contact_phone','notes','is_active')) }})">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="wh-icon-btn wh-icon-danger" title="Delete" aria-label="Delete {{ $origin->name }}"
                                onclick="deleteOrigin({{ $origin->id }}, '{{ $origin->name }}')">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; padding:52px; color:var(--subtle);">
                        No warehouse locations yet. Add one above.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── Add/Edit Modal ──────────────────────────────────────────────────── --}}
<div id="originModal" class="wh-modal-backdrop">
    <div class="wh-modal">

        <div class="wh-modal-head">
            <span id="modalTitle" style="font-family:var(--font-display); font-weight:700; font-size:15px;">Add Warehouse</span>
            <button onclick="closeModal()" class="wh-modal-close" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div style="padding:22px;">
            <div id="modalError" class="wh-error" style="display:none;"></div>

            {{-- Name & address --}}
            <div style="margin-bottom:14px;">
                <label class="wh-label">Warehouse Name</label>
                <input id="o_name" type="text" class="wh-input" placeholder="e.g. Main Warehouse, KL Hub">
            </div>
            <div style="margin-bottom:14px;">
                <label class="wh-label">Full Address</label>
                <input id="o_address" type="text" class="wh-input" placeholder="Street, City, State, Postcode">
            </div>

            {{-- Coordinates — map + manual toggle --}}
            <div style="margin-bottom:14px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                    <label class="wh-label" style="margin-bottom:0;">Coordinates</label>
                    <div class="seg">
                        <button type="button" id="oTabMap" class="seg-btn active" onclick="switchOriginTab('map')">Pin on Map</button>
                        <button type="button" id="oTabManual" class="seg-btn" onclick="switchOriginTab('manual')">Manual Entry</button>
                    </div>
                </div>

                {{-- Map picker --}}
                <div id="oCoordTabMap">
                    <div style="position:relative; z-index:0; height:220px; border-radius:8px; overflow:hidden; border:1px solid var(--border); margin-bottom:8px;">
                        <div id="originPickerMap" style="position:absolute; inset:0;"></div>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <div id="oCoordPreview" style="flex:1; font-family:var(--font-mono); font-size:11px; color:var(--subtle); background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:8px 12px;">
                            Click the map to pin this warehouse
                        </div>
                        <button type="button" onclick="clearOriginPin()" style="background:var(--muted); border:none; border-radius:6px; padding:7px 10px; color:var(--subtle); font-size:11px; cursor:pointer;">Clear</button>
                    </div>
                </div>

                {{-- Manual entry --}}
                <div id="oCoordTabManual" style="display:none;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div>
                            <label class="wh-label">Latitude</label>
                            <input id="o_lat_manual" type="number" step="any" class="wh-input" placeholder="e.g. 3.1390" oninput="syncOriginManual()">
                        </div>
                        <div>
                            <label class="wh-label">Longitude</label>
                            <input id="o_lng_manual" type="number" step="any" class="wh-input" placeholder="e.g. 101.6869" oninput="syncOriginManual()">
                        </div>
                    </div>
                    <div style="font-size:10px; color:var(--subtle); margin-top:6px;">Right-click any location in Google Maps to copy its coordinates.</div>
                </div>

                <input type="hidden" id="o_lat">
                <input type="hidden" id="o_lng">
            </div>

            {{-- Contact --}}
            <div class="wh-section-label">Contact (Optional)</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label class="wh-label">Contact Name</label>
                    <input id="o_contact_name" type="text" class="wh-input" placeholder="Person in charge">
                </div>
                <div>
                    <label class="wh-label">Contact Phone</label>
                    <input id="o_contact_phone" type="tel" class="wh-input" placeholder="+60 1X-XXXXXXX">
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label class="wh-label">Notes</label>
                <textarea id="o_notes" rows="2" class="wh-input" style="resize:vertical;" placeholder="Loading bay info, access instructions..."></textarea>
            </div>

            {{-- Active toggle --}}
            <div style="margin-bottom:24px; display:flex; align-items:center; gap:10px;">
                <input type="checkbox" id="o_is_active" checked style="accent-color:var(--accent); width:15px; height:15px;">
                <label for="o_is_active" style="font-size:12px; color:var(--text); cursor:pointer;">Active — available for selection when creating shipments</label>
            </div>

            <div style="display:flex; gap:10px;">
                <button onclick="closeModal()" class="btn btn-ghost" style="flex:1;">Cancel</button>
                <button onclick="submitOrigin()" class="btn btn-primary" style="flex:2;" id="submitBtn">Add Warehouse</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
/* ── Same map treatment as every other page: OSM tiles + dark filter,
      removed automatically in light mode ── */
.leaflet-tile-pane    { filter: brightness(0.65) saturate(0.7) hue-rotate(185deg); }
.leaflet-container    { background: #0a0b0e; }
html[data-theme="light"] .leaflet-tile-pane { filter: none; }
html[data-theme="light"] .leaflet-container { background: #dce3e8; }
.wh-popup        { font-family: var(--font-mono); font-size: 12px; }
.wh-popup strong { color: var(--accent); }

/* ── Header ── */
.wh-head { display:flex; align-items:center; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.wh-title { font-family:var(--font-display); font-weight:700; font-size:18px; }
.wh-subtitle { font-size:11px; color:var(--subtle); margin-top:3px; }

/* Chips + segmented control (same vocabulary as the dashboard) */
.chip {
    display:flex; align-items:center; gap:8px;
    border:1px solid var(--border); border-radius:8px;
    background:var(--surface); padding:8px 12px;
    font-family:var(--font-display); text-decoration:none; color:var(--text);
}
.chip-val   { font-size:15px; font-weight:500; }
.chip-label { font-size:11px; color:var(--subtle); }
.chip-accent { border-color:rgba(0,229,255,0.4); background:none; }
html[data-theme="light"] .chip-accent { border-color:rgba(0,119,182,0.4); }
.chip-accent svg { stroke:var(--accent); }
.fdot { width:7px; height:7px; border-radius:50%; display:inline-block; background:var(--success); }
.fdot-on { background:var(--success); }

.seg { display:flex; border:1px solid var(--border); border-radius:6px; overflow:hidden; }
.seg-btn {
    background:var(--muted); border:none; cursor:pointer;
    padding:4px 12px; font-size:10px; font-family:var(--font-mono); color:var(--subtle);
}
.seg-btn.active { background:rgba(0,229,255,0.12); color:var(--accent); font-weight:600; }
html[data-theme="light"] .seg-btn.active { background:rgba(0,119,182,0.10); }

/* ── Overview map: contained stacking context so the mobile drawer,
      backdrop and sticky topbar always paint above the map ── */
.wh-map-wrap { position:relative; z-index:0; height:320px; border-radius:0 0 10px 10px; overflow:hidden; }

/* ── Table ── */
.wh-row { cursor:pointer; }
.wh-row:hover td { background:rgba(0,229,255,0.03); }
html[data-theme="light"] .wh-row:hover td { background:rgba(0,119,182,0.04); }

.wh-icon-btn {
    background:var(--muted); border:none; border-radius:6px;
    width:28px; height:28px; cursor:pointer; color:var(--text);
    display:inline-flex; align-items:center; justify-content:center;
}
.wh-icon-btn:hover { color:var(--accent); }
.wh-icon-danger { background:rgba(239,68,68,0.1); color:var(--danger); }
.wh-icon-danger:hover { color:var(--danger); background:rgba(239,68,68,0.2); }

/* ── Modal ── */
.wh-modal-backdrop {
    display:none; position:fixed; inset:0; z-index:1000;
    background:rgba(0,0,0,.7); backdrop-filter:blur(4px);
    align-items:center; justify-content:center;
}
.wh-modal {
    background:var(--surface); border:1px solid var(--border);
    border-radius:12px; width:min(600px,95vw); max-height:92vh; overflow-y:auto;
}
.wh-modal-head {
    padding:18px 22px; border-bottom:1px solid var(--border);
    display:flex; justify-content:space-between; align-items:center;
    position:sticky; top:0; background:var(--surface); z-index:1;
}
.wh-modal-close {
    background:none; border:none; color:var(--subtle); cursor:pointer;
    display:flex; align-items:center; padding:2px;
}
.wh-modal-close:hover { color:var(--text); }
.wh-error {
    background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25);
    border-radius:8px; padding:10px 14px; font-size:12px; color:var(--danger);
    margin-bottom:16px;
}
.wh-label {
    display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase;
    color:var(--subtle); margin-bottom:6px;
}
.wh-input {
    width:100%; background:var(--bg); border:1px solid var(--border);
    border-radius:8px; padding:10px 13px; font-family:var(--font-mono);
    font-size:13px; color:var(--text); outline:none;
}
.wh-input:focus { border-color:var(--accent); }
.wh-section-label {
    font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--accent);
    margin-bottom:12px; padding-bottom:6px; border-bottom:1px solid var(--border); margin-top:18px;
}
</style>
@endpush

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const OSM_TILE = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

// Theme-aware marker colours resolved at load
const ACCENT   = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#00e5ff';
const IS_LIGHT = document.documentElement.getAttribute('data-theme') === 'light';

let editingId     = null;
let originMode    = 'map';
let oPickerMap    = null;
let oPickerMarker = null;
let overviewMap   = null;
const whMarkers   = {};   // origin id -> marker (active only, mirrors overview)

function whIcon(size = 22) {
    const ring = IS_LIGHT ? '#ffffff' : '#0c0d0f';
    const glyph = IS_LIGHT ? '#ffffff' : '#03272c';
    return L.divIcon({
        className: '',
        html: `<div style="width:${size}px;height:${size}px;border-radius:6px;background:${ACCENT};
                border:2px solid ${ring};box-shadow:0 0 8px ${ACCENT}66;
                display:flex;align-items:center;justify-content:center;">
                 <svg width="${size-10}" height="${size-10}" viewBox="0 0 24 24" fill="none" stroke="${glyph}" stroke-width="2.5">
                   <path d="M3 21V8l9-5 9 5v13"/><path d="M9 21v-8h6v8"/>
                 </svg>
               </div>`,
        iconSize: [size, size],
        iconAnchor: [size/2, size/2],
    });
}

// ── Overview map ──────────────────────────────────────────────────────────
(function initOverviewMap() {
    overviewMap = L.map('originsMap', { zoomControl: true, attributionControl: false });
    L.tileLayer(OSM_TILE, { maxZoom: 19 }).addTo(overviewMap);
    overviewMap.setView([3.1390, 101.6869], 7);

    const origins = @json($origins->where('is_active', true)->values());
    const bounds  = [];

    origins.forEach(o => {
        whMarkers[o.id] = L.marker([o.latitude, o.longitude], { icon: whIcon() })
            .addTo(overviewMap)
            .bindPopup(`<div class="wh-popup"><strong>${o.name}</strong><br><span style="font-size:11px;">${o.address}</span></div>`);
        bounds.push([o.latitude, o.longitude]);
    });

    if (bounds.length > 0) {
        overviewMap.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
    }
})();

// Table row → locate on the overview map (active warehouses only)
function focusWarehouse(id) {
    const m = whMarkers[id];
    if (!m) return;   // inactive warehouses are not plotted
    overviewMap.flyTo(m.getLatLng(), Math.max(overviewMap.getZoom(), 14), { duration: 0.6 });
    m.openPopup();
}

// ── Tab switch ────────────────────────────────────────────────────────────
function switchOriginTab(mode) {
    originMode = mode;
    const isMap = mode === 'map';

    document.getElementById('oCoordTabMap').style.display    = isMap ? 'block' : 'none';
    document.getElementById('oCoordTabManual').style.display = isMap ? 'none'  : 'block';
    document.getElementById('oTabMap').classList.toggle('active', isMap);
    document.getElementById('oTabManual').classList.toggle('active', !isMap);

    if (isMap) {
        document.getElementById('o_lat_manual').value = '';
        document.getElementById('o_lng_manual').value = '';
        setTimeout(() => oPickerMap && oPickerMap.invalidateSize(), 100);
    } else {
        if (oPickerMarker) {
            const ll = oPickerMarker.getLatLng();
            document.getElementById('o_lat_manual').value = ll.lat.toFixed(7);
            document.getElementById('o_lng_manual').value = ll.lng.toFixed(7);
        }
    }
}

function initOriginPickerMap(lat, lng) {
    if (!oPickerMap) {
        oPickerMap = L.map('originPickerMap', { zoomControl: true, attributionControl: false });
        L.tileLayer(OSM_TILE, { maxZoom: 19 }).addTo(oPickerMap);
        oPickerMap.setView([lat || 3.1390, lng || 101.6869], 12);

        oPickerMap.on('click', function(e) {
            setOriginPin(e.latlng.lat, e.latlng.lng);
        });
    } else {
        oPickerMap.setView([lat || 3.1390, lng || 101.6869], 12);
        oPickerMap.invalidateSize();
    }

    if (lat && lng) setOriginPin(lat, lng);
}

function setOriginPin(lat, lng) {
    if (oPickerMarker) oPickerMap.removeLayer(oPickerMarker);
    oPickerMarker = L.marker([lat, lng], { icon: whIcon(24) }).addTo(oPickerMap);
    document.getElementById('o_lat').value = lat.toFixed(7);
    document.getElementById('o_lng').value = lng.toFixed(7);
    document.getElementById('oCoordPreview').innerHTML =
        `<span style="color:var(--accent);">pinned</span> &nbsp; ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
}

function clearOriginPin() {
    if (oPickerMarker) { oPickerMap.removeLayer(oPickerMarker); oPickerMarker = null; }
    document.getElementById('o_lat').value = '';
    document.getElementById('o_lng').value = '';
    document.getElementById('oCoordPreview').innerHTML = 'Click the map to pin this warehouse';
}

function syncOriginManual() {
    document.getElementById('o_lat').value = document.getElementById('o_lat_manual').value;
    document.getElementById('o_lng').value = document.getElementById('o_lng_manual').value;
}

// ── Modal open/close ──────────────────────────────────────────────────────
function openModal() {
    editingId = null;
    document.getElementById('modalTitle').textContent  = 'Add Warehouse';
    document.getElementById('submitBtn').textContent   = 'Add Warehouse';
    document.getElementById('modalError').style.display = 'none';
    document.getElementById('o_name').value         = '';
    document.getElementById('o_address').value      = '';
    document.getElementById('o_contact_name').value = '';
    document.getElementById('o_contact_phone').value= '';
    document.getElementById('o_notes').value        = '';
    document.getElementById('o_is_active').checked  = true;
    document.getElementById('o_lat_manual').value   = '';
    document.getElementById('o_lng_manual').value   = '';
    clearOriginPin();
    switchOriginTab('map');
    document.getElementById('originModal').style.display = 'flex';
    setTimeout(() => initOriginPickerMap(null, null), 150);
}

function openEditModal(id, data) {
    editingId = id;
    document.getElementById('modalTitle').textContent  = 'Edit Warehouse';
    document.getElementById('submitBtn').textContent   = 'Save Changes';
    document.getElementById('modalError').style.display = 'none';
    document.getElementById('o_name').value         = data.name;
    document.getElementById('o_address').value      = data.address;
    document.getElementById('o_contact_name').value = data.contact_name ?? '';
    document.getElementById('o_contact_phone').value= data.contact_phone ?? '';
    document.getElementById('o_notes').value        = data.notes ?? '';
    document.getElementById('o_is_active').checked  = data.is_active == 1;
    document.getElementById('o_lat').value          = data.latitude;
    document.getElementById('o_lng').value          = data.longitude;
    document.getElementById('o_lat_manual').value   = '';
    document.getElementById('o_lng_manual').value   = '';
    switchOriginTab('map');
    document.getElementById('originModal').style.display = 'flex';
    setTimeout(() => initOriginPickerMap(data.latitude, data.longitude), 150);
}

function closeModal() {
    document.getElementById('originModal').style.display = 'none';
}

// ── Submit ────────────────────────────────────────────────────────────────
async function submitOrigin() {
    const errDiv = document.getElementById('modalError');
    errDiv.style.display = 'none';

    const lat = document.getElementById('o_lat').value;
    const lng = document.getElementById('o_lng').value;

    if (!lat || !lng) {
        errDiv.textContent = originMode === 'map'
            ? 'Please click the map to pin this warehouse.'
            : 'Please enter both Latitude and Longitude.';
        errDiv.style.display = 'block';
        return;
    }

    const btn = document.getElementById('submitBtn');
    btn.textContent = 'Saving...';
    btn.disabled = true;

    const body = {
        name:          document.getElementById('o_name').value,
        address:       document.getElementById('o_address').value,
        latitude:      lat,
        longitude:     lng,
        contact_name:  document.getElementById('o_contact_name').value || null,
        contact_phone: document.getElementById('o_contact_phone').value || null,
        notes:         document.getElementById('o_notes').value || null,
        is_active:     document.getElementById('o_is_active').checked ? 1 : 0,
    };

    const url    = editingId ? `/fleet/origins/${editingId}` : '/fleet/origins';
    const method = editingId ? 'PUT' : 'POST';

    try {
        const res  = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(body),
        });
        const json = await res.json();

        if (!res.ok) {
            const msg = json.errors
                ? Object.values(json.errors).flat().join(' ')
                : (json.message || 'An error occurred.');
            errDiv.textContent = msg;
            errDiv.style.display = 'block';
            return;
        }

        closeModal();
        location.reload();
    } catch (e) {
        errDiv.textContent = 'Request failed. Please try again.';
        errDiv.style.display = 'block';
    } finally {
        btn.textContent = editingId ? 'Save Changes' : 'Add Warehouse';
        btn.disabled = false;
    }
}

async function deleteOrigin(id, name) {
    if (!confirm(`Delete warehouse "${name}"? This cannot be undone.`)) return;
    const res  = await fetch(`/fleet/origins/${id}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
    });
    const json = await res.json();
    if (json.ok) location.reload();
}

// Close on backdrop + Escape
document.getElementById('originModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
@endpush
