@extends('layouts.app')
@section('title', 'Vehicles')

@section('content')

{{-- ── Header ─────────────────────────────────────────────────────────── --}}
<div class="vh-head">
    <div>
        <div class="vh-title">Vehicles</div>
        <div class="vh-subtitle">Manage registered fleet devices</div>
    </div>
    <span style="flex:1"></span>
    <div class="chip"><span class="chip-val mono">{{ $vehicles->total() }}</span><span class="chip-label">total</span></div>
    <div class="chip"><span class="fdot" style="background:var(--success);"></span><span class="chip-val mono" id="chip-online">—</span><span class="chip-label">online now</span></div>
    <button onclick="openAdd()" class="chip chip-accent" type="button" style="cursor:pointer;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span class="chip-label" style="color:var(--accent); font-weight:600;">Add Vehicle</span>
    </button>
</div>

{{-- ── Flash / validation feedback ─────────────────────────────────────── --}}
@if(session('success'))
<div class="vh-flash vh-flash-ok">{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="vh-flash vh-flash-err">
    <strong>Could not save vehicle:</strong> {{ implode(' ', $errors->all()) }}
</div>
@endif

{{-- ── Filter ──────────────────────────────────────────────────────────── --}}
<div style="display:flex; align-items:center; gap:8px; margin-bottom:14px;">
    <input type="search" id="pageSearch" class="finput" placeholder="Filter this page — name, plate, driver, device…" oninput="filterRows()" style="max-width:320px;">
</div>

{{-- ── Vehicles table ──────────────────────────────────────────────────── --}}
<div class="card">
    <div style="overflow-x:auto;">
        <table class="data-table" id="vehTable">
            <thead>
                <tr>
                    <th>Vehicle Name</th>
                    <th>Plate Number</th>
                    <th>Driver</th>
                    <th>Device ID (MQTT)</th>
                    <th>GPS Signal</th>
                    <th>GPS Points</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vehicles as $v)
                <tr id="vrow-{{ $v->id }}" class="vh-row" style="{{ !$v->is_active ? 'opacity:0.5;' : '' }}">
                    <td class="text-accent" style="font-weight:500;">{{ $v->name }}</td>
                    <td class="mono">{{ $v->plate_number }}</td>
                    <td>
                        @if($v->driver)
                            <div style="font-size:12px;">{{ $v->driver->name }}</div>
                            @if($v->driver->phone)
                            <div style="font-size:10px; color:var(--subtle);">{{ $v->driver->phone }}</div>
                            @endif
                        @else
                            <span class="text-subtle">—</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span class="mono" style="color:var(--subtle); font-size:11px;">{{ $v->mqtt_client_id }}</span>
                            <button class="vh-copy" onclick="copyDeviceId('{{ $v->mqtt_client_id }}')" title="Copy device ID" aria-label="Copy device ID {{ $v->mqtt_client_id }}">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                            </button>
                        </div>
                    </td>
                    <td>
                        {{-- Live GPS state — updated by the /api/live poll --}}
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span class="fdot" id="live-dot-{{ $v->id }}" style="background:var(--subtle);"></span>
                            <span style="font-size:11px; color:var(--subtle);" id="live-text-{{ $v->id }}">—</span>
                        </div>
                        <div class="mono" style="font-size:10px; color:var(--subtle); margin-top:2px;" id="live-seen-{{ $v->id }}"></div>
                    </td>
                    <td class="mono">{{ number_format($v->telemetry_count) }}</td>
                    <td>
                        <span class="pill {{ $v->is_active ? 'pill-online' : 'pill-offline' }}">
                            {{ $v->is_active ? 'active' : 'inactive' }}
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:flex; gap:6px; justify-content:flex-end;">
                            {{-- Edit --}}
                            <button onclick='openEdit(@json($v))' class="action-btn" title="Edit" aria-label="Edit {{ $v->name }}">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>

                            {{-- Toggle active/inactive --}}
                            <button onclick="toggleActive({{ $v->id }}, {{ $v->is_active ? 'false' : 'true' }})"
                                class="action-btn {{ $v->is_active ? 'action-warning' : 'action-success' }}"
                                title="{{ $v->is_active ? 'Deactivate' : 'Activate' }}"
                                aria-label="{{ $v->is_active ? 'Deactivate' : 'Activate' }} {{ $v->name }}">
                                @if($v->is_active)
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                @else
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                @endif
                            </button>

                            {{-- Delete --}}
                            <button onclick="confirmDelete({{ $v->id }}, '{{ $v->name }}')"
                                class="action-btn action-danger" title="Delete" aria-label="Delete {{ $v->name }}">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center; color:var(--subtle); padding:40px;">
                        No vehicles registered yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($vehicles->hasPages())
    <div style="padding:14px 18px; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
        <span style="font-size:11px; color:var(--subtle);">
            Showing {{ $vehicles->firstItem() }}–{{ $vehicles->lastItem() }} of {{ $vehicles->total() }}
        </span>
        <div style="display:flex; gap:6px;">
            @if($vehicles->onFirstPage())
                <span class="btn btn-ghost vh-page-btn" style="opacity:.35; cursor:default;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg> Prev
                </span>
            @else
                <a href="{{ $vehicles->previousPageUrl() }}" class="btn btn-ghost vh-page-btn">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg> Prev
                </a>
            @endif
            @if($vehicles->hasMorePages())
                <a href="{{ $vehicles->nextPageUrl() }}" class="btn btn-ghost vh-page-btn">
                    Next <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            @else
                <span class="btn btn-ghost vh-page-btn" style="opacity:.35; cursor:default;">
                    Next <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
            @endif
        </div>
    </div>
    @endif
</div>

{{-- ── Add Modal ── --}}
<div id="addModal" class="vh-modal-backdrop">
    <div class="vh-modal">
        <div class="vh-modal-head">
            <span style="font-family:var(--font-display); font-weight:700; font-size:15px;">Register New Vehicle</span>
            <button onclick="closeAdd()" class="vh-close" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div style="padding:22px;">
            <form action="{{ route('fleet.vehicles.store') }}" method="POST">
                @csrf
                <div style="display:flex; flex-direction:column; gap:14px;">
                    <div>
                        <label class="flabel">Vehicle Name</label>
                        <input name="name" required placeholder="e.g. Truck Alpha-01" class="finput" value="{{ old('name') }}">
                    </div>
                    <div>
                        <label class="flabel">Plate Number</label>
                        <input name="plate_number" required placeholder="e.g. WXY 1234" class="finput" value="{{ old('plate_number') }}">
                    </div>
                    <div>
                        <label class="flabel">Device MQTT Client ID</label>
                        <input name="mqtt_client_id" required placeholder="e.g. esp32_vehicle_01" class="finput" value="{{ old('mqtt_client_id') }}">
                        <p style="font-size:10px; color:var(--subtle); margin-top:4px;">Must match the ID in your ESP32 firmware</p>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div>
                            <label class="flabel">Driver</label>
                            <select name="driver_user_id" class="finput" style="background:var(--bg); color:var(--text);">
                                <option value="">— No driver assigned —</option>
                                @foreach($availableDrivers as $d)
                                <option value="{{ $d->id }}" @selected(old('driver_user_id') == $d->id)
                                    @if($d->vehicle_id) title="Already assigned to another vehicle" @endif>
                                    {{ $d->name }}{{ $d->vehicle_id ? ' *' : '' }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="flabel">Driver Contact</label>
                            <div style="font-size:10px; color:var(--subtle); padding:6px 0;">
                                Driver name and contact are pulled from their user account.<br>
                                * Already assigned to another vehicle.
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:4px;">Register Vehicle</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Edit Modal ── --}}
<div id="editModal" class="vh-modal-backdrop">
    <div class="vh-modal">
        <div class="vh-modal-head">
            <span style="font-family:var(--font-display); font-weight:700; font-size:15px;">Edit Vehicle</span>
            <button onclick="closeEdit()" class="vh-close" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div style="padding:22px;">
            <div id="editMsg" class="vh-msg" style="display:none;"></div>
            <div style="display:flex; flex-direction:column; gap:14px;">
                <div>
                    <label class="flabel">Vehicle Name</label>
                    <input id="e-name" class="finput" placeholder="Vehicle name">
                </div>
                <div>
                    <label class="flabel">Plate Number</label>
                    <input id="e-plate" class="finput" placeholder="Plate number">
                </div>
                <div>
                    <label class="flabel">Device MQTT Client ID</label>
                    <input id="e-mqtt" class="finput" placeholder="MQTT client ID">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label class="flabel">Driver</label>
                        <select id="e-driver-user-id" class="finput" style="background:var(--bg); color:var(--text);">
                            <option value="">— No driver assigned —</option>
                            @foreach($availableDrivers as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="flabel">Driver Contact</label>
                        <div style="font-size:10px; color:var(--subtle); padding:6px 0;">
                            Pulled from the driver's user account — edit it on the Users page.
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-primary" onclick="submitEdit()" style="margin-top:4px;" id="editBtn">Save Changes</button>
            </div>
        </div>
    </div>
</div>

{{-- ── Delete Confirm Modal ── --}}
<div id="deleteModal" class="vh-modal-backdrop">
    <div class="vh-modal" style="width:min(400px,95vw);">
        <div class="vh-modal-head">
            <span style="font-family:var(--font-display); font-weight:700; font-size:15px; color:var(--danger);">Delete Vehicle</span>
            <button onclick="closeDelete()" class="vh-close" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div style="padding:22px;">
            <p style="font-size:13px; margin-bottom:6px;">Are you sure you want to delete:</p>
            <p id="delete-name" style="font-size:15px; font-weight:700; color:var(--danger); margin-bottom:16px;"></p>
            <p style="font-size:11px; color:var(--subtle); margin-bottom:20px;">
                This will permanently remove the vehicle and all its GPS telemetry history. This cannot be undone.
            </p>
            <div style="display:flex; gap:10px;">
                <button onclick="closeDelete()" class="btn btn-ghost" style="flex:1; justify-content:center;">Cancel</button>
                <button onclick="submitDelete()" style="flex:1; justify-content:center; background:#dc2626; color:#fff; border:none; padding:8px 14px; border-radius:6px; font-family:var(--font-mono); font-size:12px; cursor:pointer;">
                    Delete Permanently
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    /* ── Header + chips ── */
    .vh-head { display:flex; align-items:center; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
    .vh-title { font-family:var(--font-display); font-weight:800; font-size:18px; }
    .vh-subtitle { font-size:11px; color:var(--subtle); margin-top:3px; }

    .chip {
        display:flex; align-items:center; gap:8px;
        border:1px solid var(--border); border-radius:8px;
        background:var(--surface); padding:8px 12px;
        font-family:var(--font-display); color:var(--text);
    }
    .chip-val   { font-size:15px; font-weight:500; }
    .chip-label { font-size:11px; color:var(--subtle); }
    .chip-accent { border-color:rgba(0,229,255,0.4); background:none; }
    html[data-theme="light"] .chip-accent { border-color:rgba(0,119,182,0.4); }
    .chip-accent svg { stroke:var(--accent); }
    .fdot { width:7px; height:7px; border-radius:50%; display:inline-block; flex-shrink:0; }

    /* ── Flash boxes (CSS-var based → correct in both themes) ── */
    .vh-flash { padding:12px 18px; border-radius:8px; font-size:12px; margin-bottom:16px; }
    .vh-flash-ok  { background:rgba(34,197,94,0.08);  border:1px solid rgba(34,197,94,0.3);  color:var(--success); }
    .vh-flash-err { background:rgba(239,68,68,0.08);  border:1px solid rgba(239,68,68,0.3);  color:var(--danger); }

    .vh-msg { padding:10px 14px; border-radius:6px; font-size:12px; margin-bottom:14px; }
    .vh-msg-ok  { background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.3); color:var(--success); }
    .vh-msg-err { background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.3); color:var(--danger); }

    /* ── Copy device id ── */
    .vh-copy {
        background:none; border:none; color:var(--subtle); cursor:pointer;
        display:inline-flex; align-items:center; padding:2px;
    }
    .vh-copy:hover { color:var(--accent); }

    .vh-page-btn { padding:4px 10px; display:inline-flex; align-items:center; gap:5px; }

    /* ── Modals ── */
    .vh-modal-backdrop {
        display:none; position:fixed; inset:0; z-index:1000;
        background:rgba(0,0,0,0.7); backdrop-filter:blur(4px);
        align-items:center; justify-content:center;
    }
    .vh-modal {
        background:var(--surface); border:1px solid var(--border);
        border-radius:12px; width:min(460px,95vw); max-height:92vh; overflow-y:auto;
    }
    .vh-modal-head {
        padding:18px 22px; border-bottom:1px solid var(--border);
        display:flex; justify-content:space-between; align-items:center;
    }
    .vh-close {
        background:none; border:none; color:var(--subtle); cursor:pointer;
        display:flex; align-items:center; padding:2px;
    }
    .vh-close:hover { color:var(--text); }

    /* ── Form fields (names preserved) ── */
    .flabel {
        display: block;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: var(--subtle);
        margin-bottom: 5px;
    }
    .finput {
        width: 100%;
        background: var(--bg);
        border: 1px solid var(--border);
        color: var(--text);
        padding: 9px 12px;
        border-radius: 6px;
        font-family: var(--font-mono);
        font-size: 12px;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.15s;
    }
    .finput:focus { border-color: var(--accent); }

    /* ── Action buttons (theme-safe hovers) ── */
    .action-btn {
        width: 30px; height: 30px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: transparent;
        color: var(--subtle);
        cursor: pointer;
        transition: all 0.15s;
    }
    .action-btn:hover { background: var(--muted); color: var(--text); }
    .action-btn.action-warning:hover { background: rgba(245,158,11,0.12); color: var(--warning); border-color: var(--warning); }
    .action-btn.action-danger:hover  { background: rgba(239,68,68,0.12);  color: var(--danger);  border-color: var(--danger); }
    .action-btn.action-success:hover { background: rgba(34,197,94,0.12);  color: var(--success); border-color: var(--success); }
</style>
@endpush

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name=csrf-token]').content;
let currentEditId   = null;
let currentDeleteId = null;

// ── Add modal ─────────────────────────────────
function openAdd()  { document.getElementById('addModal').style.display  = 'flex'; }
function closeAdd() { document.getElementById('addModal').style.display  = 'none'; }

// If the add form failed server-side validation, reopen the modal so the
// person sees the error and their repopulated values (was silently lost before).
@if($errors->any())
openAdd();
@endif

// ── Copy device id (for firmware + broker credential setup) ──────────────
function copyDeviceId(id) {
    navigator.clipboard.writeText(id);
}

// ── Page filter (client-side, current page only) ─────────────────────────
function filterRows() {
    const q = document.getElementById('pageSearch').value.trim().toLowerCase();
    document.querySelectorAll('#vehTable tbody tr.vh-row').forEach(tr => {
        tr.style.display = !q || tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ── Live GPS signal (reuses the fleet live API; 10s) ──────────────────────
async function fetchLiveSignal() {
    try {
        const res  = await fetch('{{ route("fleet.api.live") }}', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        let online = 0;

        data.forEach(v => {
            const dot  = document.getElementById(`live-dot-${v.id}`);
            const text = document.getElementById(`live-text-${v.id}`);
            const seen = document.getElementById(`live-seen-${v.id}`);
            if (!dot) return;  // vehicle not on this page

            if (!v.latitude) {
                dot.style.background = 'var(--subtle)';
                text.textContent = 'no data';
                seen.textContent = '';
                return;
            }
            if (v.is_offline) {
                dot.style.background = 'var(--danger)';
                text.textContent = 'offline';
                text.style.color = 'var(--danger)';
            } else {
                online++;
                dot.style.background = 'var(--success)';
                text.textContent = (v.speed_kmh ?? 0).toFixed(1) + ' km/h';
                text.style.color = 'var(--text)';
            }
            seen.textContent = v.recorded_at ? timeAgo(v.recorded_at) : '';
        });

        document.getElementById('chip-online').textContent = online;
    } catch(e) { console.error('Live signal error:', e); }
}

function timeAgo(iso) {
    const diff = Math.floor((Date.now() - new Date(iso)) / 1000);
    if (diff < 60)   return diff + 's ago';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    return Math.floor(diff / 3600) + 'h ago';
}

// ── Edit modal ────────────────────────────────
function openEdit(v) {
    currentEditId = v.id;
    document.getElementById('e-name').value   = v.name;
    document.getElementById('e-plate').value  = v.plate_number;
    document.getElementById('e-mqtt').value   = v.mqtt_client_id;
    // Set the driver dropdown to the currently linked driver user id
    const driverSel = document.getElementById('e-driver-user-id');
    if (driverSel) driverSel.value = v.driver_user_id ?? '';
    document.getElementById('editMsg').style.display = 'none';
    document.getElementById('editModal').style.display = 'flex';
}
function closeEdit() { document.getElementById('editModal').style.display = 'none'; }

async function submitEdit() {
    const msg = document.getElementById('editMsg');
    const btn = document.getElementById('editBtn');
    const payload = {
        name:           document.getElementById('e-name').value,
        plate_number:   document.getElementById('e-plate').value,
        mqtt_client_id: document.getElementById('e-mqtt').value,
        driver_user_id: document.getElementById('e-driver-user-id').value || null,
    };

    btn.textContent = 'Saving...';
    btn.disabled = true;

    try {
        const res  = await fetch(`/fleet/vehicles/${currentEditId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (res.ok) {
            msg.className = 'vh-msg vh-msg-ok';
            msg.style.display = 'block';
            msg.textContent = 'Vehicle updated — refreshing…';
            setTimeout(() => location.reload(), 900);
        } else {
            msg.className = 'vh-msg vh-msg-err';
            msg.style.display = 'block';
            msg.textContent = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : (data.message || 'Could not update vehicle.');
        }
    } catch(e) {
        msg.className = 'vh-msg vh-msg-err';
        msg.style.display = 'block';
        msg.textContent = 'Network error — please try again.';
    } finally {
        btn.textContent = 'Save Changes';
        btn.disabled = false;
    }
}

// ── Toggle active/inactive ────────────────────
async function toggleActive(id, newState) {
    const res = await fetch(`/fleet/vehicles/${id}/toggle`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ is_active: newState }),
    });
    if (res.ok) location.reload();
}

// ── Delete modal ──────────────────────────────
function confirmDelete(id, name) {
    currentDeleteId = id;
    document.getElementById('delete-name').textContent = name;
    document.getElementById('deleteModal').style.display = 'flex';
}
function closeDelete() { document.getElementById('deleteModal').style.display = 'none'; }

async function submitDelete() {
    const res = await fetch(`/fleet/vehicles/${currentDeleteId}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
    });
    if (res.ok) location.reload();
}

// Close modals on backdrop click + Escape
['addModal','editModal','deleteModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeAdd(); closeEdit(); closeDelete(); }
});

// ── Init ──────────────────────────────────────
fetchLiveSignal();
setInterval(fetchLiveSignal, 10000);
</script>
@endpush
