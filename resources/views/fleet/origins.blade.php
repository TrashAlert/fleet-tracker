@extends('layouts.app')
@section('title', 'Origin Locations')

@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <div style="font-family:var(--font-display); font-weight:700; font-size:18px;">Origin Locations</div>
        <div style="font-size:11px; color:var(--subtle); margin-top:3px;">
            Preset pickup points used when creating shipments
        </div>
    </div>
    <button onclick="openModal()" class="btn btn-primary">+ Add Origin</button>
</div>

{{-- Map overview --}}
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <span class="card-title">Location Overview</span>
        <span style="font-size:11px; color:var(--subtle);">{{ $origins->where('is_active', true)->count() }} active</span>
    </div>
    <div id="originsMap" style="height:280px; border-radius:0 0 10px 10px;"></div>
</div>

{{-- Origins Table --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">All Origins</span>
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
                <tr>
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
                    <td style="text-align:center;">
                        <div style="display:flex; gap:6px; justify-content:center;">
                            <button
                                onclick="openEditModal({{ $origin->id }}, {{ json_encode($origin->only('name','address','latitude','longitude','contact_name','contact_phone','notes','is_active')) }})"
                                style="background:var(--muted);border:none;border-radius:4px;padding:4px 9px;cursor:pointer;color:var(--text);font-size:11px;">
                                Edit
                            </button>
                            <button
                                onclick="deleteOrigin({{ $origin->id }}, '{{ $origin->name }}')"
                                style="background:rgba(239,68,68,0.1);border:none;border-radius:4px;padding:4px 9px;cursor:pointer;color:var(--danger);font-size:11px;">
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; padding:52px; color:var(--subtle);">
                        No origin locations yet. Add one above.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── Add/Edit Modal ──────────────────────────────────────────────────── --}}
<div id="originModal" style="display:none; position:fixed; inset:0; z-index:1000; background:rgba(0,0,0,.7); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; width:min(600px,95vw); max-height:92vh; overflow-y:auto;">

        <div style="padding:18px 22px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; background:var(--surface); z-index:1;">
            <span id="modalTitle" style="font-family:var(--font-display); font-weight:700; font-size:15px;">Add Origin</span>
            <button onclick="closeModal()" style="background:none;border:none;color:var(--subtle);cursor:pointer;font-size:22px;line-height:1;">x</button>
        </div>

        <div style="padding:22px;">
            <div id="modalError" style="display:none; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); border-radius:8px; padding:10px 14px; font-size:12px; color:var(--danger); margin-bottom:16px;"></div>

            {{-- Name & address --}}
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Location Name</label>
                <input id="o_name" type="text" placeholder="e.g. Main Warehouse, KL Hub"
                    style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Full Address</label>
                <input id="o_address" type="text" placeholder="Street, City, State, Postcode"
                    style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
            </div>

            {{-- Coordinates — map + manual toggle --}}
            <div style="margin-bottom:14px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                    <label style="font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle);">Coordinates</label>
                    <div style="display:flex; border:1px solid var(--border); border-radius:6px; overflow:hidden;">
                        <button type="button" id="oTabMap" onclick="switchOriginTab('map')"
                            style="padding:4px 12px; font-size:10px; font-family:var(--font-mono); border:none; cursor:pointer; background:var(--accent); color:#000;">
                            Pin on Map
                        </button>
                        <button type="button" id="oTabManual" onclick="switchOriginTab('manual')"
                            style="padding:4px 12px; font-size:10px; font-family:var(--font-mono); border:none; cursor:pointer; background:var(--muted); color:var(--subtle);">
                            Manual Entry
                        </button>
                    </div>
                </div>

                {{-- Map picker --}}
                <div id="oCoordTabMap">
                    <div id="originPickerMap" style="height:220px; border-radius:8px; overflow:hidden; border:1px solid var(--border); margin-bottom:8px;"></div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <div id="oCoordPreview" style="flex:1; font-family:var(--font-mono); font-size:11px; color:var(--subtle); background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:8px 12px;">
                            Click the map to pin this location
                        </div>
                        <button type="button" onclick="clearOriginPin()" style="background:var(--muted); border:none; border-radius:6px; padding:7px 10px; color:var(--subtle); font-size:11px; cursor:pointer;">Clear</button>
                    </div>
                </div>

                {{-- Manual entry --}}
                <div id="oCoordTabManual" style="display:none;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div>
                            <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Latitude</label>
                            <input id="o_lat_manual" type="number" step="any" placeholder="e.g. 3.1390"
                                oninput="syncOriginManual()"
                                style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Longitude</label>
                            <input id="o_lng_manual" type="number" step="any" placeholder="e.g. 101.6869"
                                oninput="syncOriginManual()"
                                style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
                        </div>
                    </div>
                    <div style="font-size:10px; color:var(--subtle); margin-top:6px;">Right-click any location in Google Maps to copy its coordinates.</div>
                </div>

                <input type="hidden" id="o_lat">
                <input type="hidden" id="o_lng">
            </div>

            {{-- Contact --}}
            <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--accent); margin-bottom:12px; padding-bottom:6px; border-bottom:1px solid var(--border); margin-top:18px;">Contact (Optional)</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Contact Name</label>
                    <input id="o_contact_name" type="text" placeholder="Person in charge"
                        style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
                </div>
                <div>
                    <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Contact Phone</label>
                    <input id="o_contact_phone" type="tel" placeholder="+60 1X-XXXXXXX"
                        style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Notes</label>
                <textarea id="o_notes" rows="2" placeholder="Loading bay info, access instructions..."
                    style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none; resize:vertical;"></textarea>
            </div>

            {{-- Active toggle --}}
            <div style="margin-bottom:24px; display:flex; align-items:center; gap:10px;">
                <input type="checkbox" id="o_is_active" checked style="accent-color:var(--accent); width:15px; height:15px;">
                <label for="o_is_active" style="font-size:12px; color:var(--text); cursor:pointer;">Active — available for selection when creating shipments</label>
            </div>

            <div style="display:flex; gap:10px;">
                <button onclick="closeModal()" class="btn btn-ghost" style="flex:1;">Cancel</button>
                <button onclick="submitOrigin()" class="btn btn-primary" style="flex:2;" id="submitBtn">Add Origin</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
/* Light map overrides so Leaflet controls show correctly on light tiles */
.leaflet-control-zoom a { background:#fff; color:#333; border-color:#ccc; }
.leaflet-control-zoom a:hover { background:#f4f4f4; }
</style>
@endpush

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const LIGHT_TILE = 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';

let editingId     = null;
let originMode    = 'map';
let oPickerMap    = null;
let oPickerMarker = null;
let overviewMap   = null;

// ── Overview map ──────────────────────────────────────────────────────────
(function initOverviewMap() {
    overviewMap = L.map('originsMap', { zoomControl: true, attributionControl: false });
    L.tileLayer(LIGHT_TILE).addTo(overviewMap);
    overviewMap.setView([3.1390, 101.6869], 7);

    const origins = @json($origins->where('is_active', true)->values());
    const bounds  = [];

    origins.forEach(o => {
        L.circleMarker([o.latitude, o.longitude], {
            radius: 8, color: '#0077cc', fillColor: '#0077cc', fillOpacity: 0.8, weight: 2
        }).addTo(overviewMap)
          .bindPopup(`<b>${o.name}</b><br><span style="font-size:11px;">${o.address}</span>`);
        bounds.push([o.latitude, o.longitude]);
    });

    if (bounds.length > 0) {
        overviewMap.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
    }
})();

// ── Tab switch ────────────────────────────────────────────────────────────
function switchOriginTab(mode) {
    originMode = mode;
    const isMap = mode === 'map';

    document.getElementById('oCoordTabMap').style.display    = isMap ? 'block' : 'none';
    document.getElementById('oCoordTabManual').style.display = isMap ? 'none'  : 'block';
    document.getElementById('oTabMap').style.background    = isMap ? 'var(--accent)' : 'var(--muted)';
    document.getElementById('oTabMap').style.color         = isMap ? '#000'          : 'var(--subtle)';
    document.getElementById('oTabManual').style.background = isMap ? 'var(--muted)' : 'var(--accent)';
    document.getElementById('oTabManual').style.color      = isMap ? 'var(--subtle)' : '#000';

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
        L.tileLayer(LIGHT_TILE).addTo(oPickerMap);
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
    oPickerMarker = L.circleMarker([lat, lng], {
        radius: 9, color: '#0077cc', fillColor: '#0077cc', fillOpacity: 0.9, weight: 2
    }).addTo(oPickerMap);
    document.getElementById('o_lat').value = lat.toFixed(7);
    document.getElementById('o_lng').value = lng.toFixed(7);
    document.getElementById('oCoordPreview').innerHTML =
        `<span style="color:var(--accent);">pinned</span> &nbsp; ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
}

function clearOriginPin() {
    if (oPickerMarker) { oPickerMap.removeLayer(oPickerMarker); oPickerMarker = null; }
    document.getElementById('o_lat').value = '';
    document.getElementById('o_lng').value = '';
    document.getElementById('oCoordPreview').innerHTML = 'Click the map to pin this location';
}

function syncOriginManual() {
    document.getElementById('o_lat').value = document.getElementById('o_lat_manual').value;
    document.getElementById('o_lng').value = document.getElementById('o_lng_manual').value;
}

// ── Modal open/close ──────────────────────────────────────────────────────
function openModal() {
    editingId = null;
    document.getElementById('modalTitle').textContent  = 'Add Origin';
    document.getElementById('submitBtn').textContent   = 'Add Origin';
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
    document.getElementById('modalTitle').textContent  = 'Edit Origin';
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
            ? 'Please click the map to pin this location.'
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

    const res  = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body),
    });
    const json = await res.json();

    btn.textContent = editingId ? 'Save Changes' : 'Add Origin';
    btn.disabled = false;

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
}

async function deleteOrigin(id, name) {
    if (!confirm(`Delete origin "${name}"? This cannot be undone.`)) return;
    const res  = await fetch(`/fleet/origins/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF },
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
