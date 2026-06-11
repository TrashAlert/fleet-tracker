@extends('layouts.app')
@section('title', 'Vehicles')

@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <h1 style="font-family:var(--font-display); font-size:20px; font-weight:800;">Vehicles</h1>
        <p style="color:var(--subtle); font-size:11px; margin-top:4px;">Manage registered fleet devices</p>
    </div>
    <button class="btn btn-primary" onclick="openAdd()">+ Add Vehicle</button>
</div>

@if(session('success'))
<div style="background:#16301e; color:#22c55e; padding:12px 18px; border-radius:8px; font-size:12px; margin-bottom:16px; border:1px solid #1e4a2e;">
    {{ session('success') }}
</div>
@endif

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Vehicle Name</th>
                <th>Plate Number</th>
                <th>Driver</th>
                <th>Device ID (MQTT)</th>
                <th>GPS Points</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($vehicles as $v)
            <tr id="vrow-{{ $v->id }}" style="{{ !$v->is_active ? 'opacity:0.5;' : '' }}">
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
                <td class="mono" style="color:var(--subtle); font-size:11px;">{{ $v->mqtt_client_id }}</td>
                <td class="mono">{{ number_format($v->telemetry_count) }}</td>
                <td>
                    <span class="pill {{ $v->is_active ? 'pill-online' : 'pill-offline' }}">
                        {{ $v->is_active ? 'active' : 'inactive' }}
                    </span>
                </td>
                <td style="text-align:right;">
                    <div style="display:flex; gap:6px; justify-content:flex-end;">
                        {{-- Edit --}}
                        <button onclick='openEdit(@json($v))' class="action-btn" title="Edit">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>

                        {{-- Toggle active/inactive --}}
                        <button onclick="toggleActive({{ $v->id }}, {{ $v->is_active ? 'false' : 'true' }})"
                            class="action-btn {{ $v->is_active ? 'action-warning' : 'action-success' }}"
                            title="{{ $v->is_active ? 'Deactivate' : 'Activate' }}">
                            @if($v->is_active)
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                            @else
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                            @endif
                        </button>

                        {{-- Delete --}}
                        <button onclick="confirmDelete({{ $v->id }}, '{{ $v->name }}')"
                            class="action-btn action-danger" title="Delete">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center; color:var(--subtle); padding:40px;">
                    No vehicles registered yet.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:14px 18px; border-top:1px solid var(--border); color:var(--subtle); font-size:11px;">
        {{ $vehicles->links() }}
    </div>
</div>

{{-- ── Add Modal ── --}}
<div id="addModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:100; align-items:center; justify-content:center;">
    <div class="card" style="width:460px; max-width:95vw;">
        <div class="card-header">
            <span class="card-title">Register New Vehicle</span>
            <button onclick="closeAdd()" style="background:none;border:none;color:var(--subtle);cursor:pointer;font-size:22px;">×</button>
        </div>
        <div class="card-body">
            <form action="{{ route('fleet.vehicles.store') }}" method="POST">
                @csrf
                <div style="display:flex; flex-direction:column; gap:14px;">
                    <div>
                        <label class="flabel">Vehicle Name</label>
                        <input name="name" required placeholder="e.g. Truck Alpha-01" class="finput">
                    </div>
                    <div>
                        <label class="flabel">Plate Number</label>
                        <input name="plate_number" required placeholder="e.g. WXY 1234" class="finput">
                    </div>
                    <div>
                        <label class="flabel">Device MQTT Client ID</label>
                        <input name="mqtt_client_id" required placeholder="e.g. esp32_vehicle_01" class="finput">
                        <p style="font-size:10px; color:var(--subtle); margin-top:4px;">Must match the ID in your ESP32 firmware</p>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div>
                            <label class="flabel">Driver Name</label>
                            <select name="driver_user_id" class="finput" style="background:var(--bg); color:var(--text);">
                                <option value="">— No driver assigned —</option>
                                @foreach($availableDrivers as $d)
                                <option value="{{ $d->id }}"
                                    {{ $d->vehicle_id ? '(assigned to another vehicle)' : '' }}>
                                    {{ $d->name }}{{ $d->vehicle_id ? ' *' : '' }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="flabel">Driver Phone</label>
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
<div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:100; align-items:center; justify-content:center;">
    <div class="card" style="width:460px; max-width:95vw;">
        <div class="card-header">
            <span class="card-title">Edit Vehicle</span>
            <button onclick="closeEdit()" style="background:none;border:none;color:var(--subtle);cursor:pointer;font-size:22px;">×</button>
        </div>
        <div class="card-body">
            <div id="editMsg" style="display:none; padding:10px 14px; border-radius:6px; font-size:12px; margin-bottom:14px;"></div>
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
                        <label class="flabel">Driver Name</label>
                        <select id="e-driver-user-id" class="finput" style="background:var(--bg); color:var(--text);">
                            <option value="">— No driver assigned —</option>
                            @foreach($availableDrivers as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="flabel">Driver Phone</label>
                        <input id="e-phone" class="finput" placeholder="Optional">
                    </div>
                </div>
                <button type="button" class="btn btn-primary" onclick="submitEdit()" style="margin-top:4px;">Save Changes</button>
            </div>
        </div>
    </div>
</div>

{{-- ── Delete Confirm Modal ── --}}
<div id="deleteModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:100; align-items:center; justify-content:center;">
    <div class="card" style="width:400px; max-width:95vw;">
        <div class="card-header">
            <span class="card-title" style="color:var(--danger);">Delete Vehicle</span>
            <button onclick="closeDelete()" style="background:none;border:none;color:var(--subtle);cursor:pointer;font-size:22px;">×</button>
        </div>
        <div class="card-body">
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
    .action-btn:hover        { background: var(--muted); color: var(--text); }
    .action-btn.action-warning:hover { background: #2a2010; color: var(--warning); border-color: var(--warning); }
    .action-btn.action-danger:hover  { background: #2a1010; color: var(--danger);  border-color: var(--danger); }
    .action-btn.action-success:hover { background: #16301e; color: var(--success); border-color: var(--success); }
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
    const payload = {
        name:           document.getElementById('e-name').value,
        plate_number:   document.getElementById('e-plate').value,
        mqtt_client_id: document.getElementById('e-mqtt').value,
        driver_user_id: document.getElementById('e-driver-user-id').value || null,
    };

    try {
        const res  = await fetch(`/fleet/vehicles/${currentEditId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (res.ok) {
            msg.style.display = 'block';
            msg.style.background = '#16301e';
            msg.style.color = '#22c55e';
            msg.textContent = 'Vehicle updated successfully.'; location.reload();
            setTimeout(() => location.reload(), 1200);
        } else {
            msg.style.display = 'block';
            msg.style.background = '#2a1010';
            msg.style.color = '#ef4444';
            msg.textContent = JSON.stringify(data.errors ?? data.message);
        }
    } catch(e) {
        msg.style.display = 'block';
        msg.style.background = '#2a1010';
        msg.style.color = '#ef4444';
        msg.textContent = 'Network error.';
    }
}

// ── Toggle active/inactive ────────────────────
async function toggleActive(id, newState) {
    const res = await fetch(`/fleet/vehicles/${id}/toggle`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
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
        headers: { 'X-CSRF-TOKEN': CSRF },
    });
    if (res.ok) location.reload();
}

// Close modals on backdrop click
['addModal','editModal','deleteModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});
</script>
@endpush
