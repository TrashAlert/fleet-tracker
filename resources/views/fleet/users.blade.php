@extends('layouts.app')
@section('title', 'User Management')

@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <div style="font-family:var(--font-display); font-weight:700; font-size:18px;">User Management</div>
        <div style="font-size:11px; color:var(--subtle); margin-top:3px;">Admin-only — create and manage system accounts</div>
    </div>
    <button onclick="openModal()" class="btn btn-primary">+ Add User</button>
</div>

{{-- Users Table --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">All Users</span>
        <span style="font-size:11px; color:var(--subtle);">{{ $users->count() }} accounts</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Linked Vehicle</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th style="width:100px; text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td style="font-weight:600;">
                        {{ $user->name }}
                        @if($user->id === auth()->id())
                            <span style="font-size:9px; color:var(--accent); margin-left:6px;">YOU</span>
                        @endif
                    </td>
                    <td class="mono" style="color:var(--subtle);">{{ $user->email }}</td>
                    <td>
                        <span style="
                            display:inline-block; padding:2px 9px; border-radius:4px;
                            font-size:10px; font-weight:600; letter-spacing:.08em; text-transform:uppercase;
                            color:{{ $user->getRoleBadgeColor() }};
                            background:{{ match($user->role) {
                                'admin'   => 'rgba(239,68,68,0.1)',
                                'manager' => 'rgba(0,229,255,0.08)',
                                'driver'  => 'rgba(34,197,94,0.1)',
                                default   => 'var(--muted)'
                            } }};
                        ">{{ $user->role }}</span>
                    </td>
                    <td style="font-size:12px; color:var(--subtle);">
                        {{ $user->vehicle?->plate_number ?? '—' }}
                    </td>
                    <td>
                        @if($user->is_active)
                            <span class="pill pill-online">Active</span>
                        @else
                            <span class="pill pill-offline">Inactive</span>
                        @endif
                    </td>
                    <td class="mono" style="font-size:11px; color:var(--subtle);">
                        {{ $user->last_login_at?->diffForHumans() ?? 'Never' }}
                    </td>
                    <td style="text-align:center;">
                        <div style="display:flex; gap:6px; justify-content:center;">
                            <button
                                onclick="openEditModal({{ $user->id }}, {{ json_encode($user->only('name','email','role','vehicle_id','is_active')) }})"
                                style="background:var(--muted);border:none;border-radius:4px;padding:4px 9px;cursor:pointer;color:var(--text);font-size:11px;"
                                title="Edit">✏️
                            </button>
                            <button
                                onclick="openPasswordModal({{ $user->id }}, '{{ $user->name }}')"
                                style="background:var(--muted);border:none;border-radius:4px;padding:4px 9px;cursor:pointer;color:var(--subtle);font-size:11px;"
                                title="Reset Password">🔑
                            </button>
                            @if($user->id !== auth()->id())
                            <button
                                onclick="deleteUser({{ $user->id }}, '{{ $user->name }}')"
                                style="background:rgba(239,68,68,0.1);border:none;border-radius:4px;padding:4px 9px;cursor:pointer;color:var(--danger);font-size:11px;"
                                title="Delete">🗑
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:48px; color:var(--subtle);">
                        No users found. Add one above.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── Add/Edit User Modal ── --}}
<div id="userModal" style="display:none; position:fixed; inset:0; z-index:1000; background:rgba(0,0,0,.7); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; width:min(480px,95vw); max-height:90vh; overflow-y:auto;">

        <div style="padding:18px 22px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
            <span id="modalTitle" style="font-family:var(--font-display); font-weight:700; font-size:15px;">Add User</span>
            <button onclick="closeModal()" style="background:none;border:none;color:var(--subtle);cursor:pointer;font-size:22px;line-height:1;">×</button>
        </div>

        <div style="padding:22px;">
            <div id="modalError" style="display:none; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); border-radius:8px; padding:10px 14px; font-size:12px; color:var(--danger); margin-bottom:16px;"></div>

            {{-- Name --}}
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Full Name</label>
                <input id="f_name" type="text" placeholder="John Doe" style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
            </div>

            {{-- Email --}}
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Email Address</label>
                <input id="f_email" type="email" placeholder="user@fleettrack.local" style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
            </div>

            {{-- Password (create only) --}}
            <div id="passwordField" style="margin-bottom:16px;">
                <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Password</label>
                <input id="f_password" type="password" placeholder="Min 8 chars, uppercase + number" style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
                <div style="font-size:10px; color:var(--subtle); margin-top:4px;">Min 8 characters — must include uppercase and a number.</div>
            </div>

            {{-- Role --}}
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Role</label>
                <select id="f_role" onchange="toggleVehicleField()" style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
                    <option value="driver">Driver</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            {{-- Vehicle (driver only) --}}
            <div id="vehicleField" style="margin-bottom:16px;">
                <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Linked Vehicle <span style="color:var(--muted)">(drivers only)</span></label>
                <select id="f_vehicle_id" style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
                    <option value="">— No vehicle —</option>
                    @foreach($vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->plate_number }} — {{ $v->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Active --}}
            <div style="margin-bottom:24px; display:flex; align-items:center; gap:10px;">
                <input type="checkbox" id="f_is_active" checked style="accent-color:var(--accent); width:15px; height:15px;">
                <label for="f_is_active" style="font-size:12px; color:var(--text); cursor:pointer;">Account Active</label>
            </div>

            <div style="display:flex; gap:10px;">
                <button onclick="closeModal()" class="btn btn-ghost" style="flex:1;">Cancel</button>
                <button onclick="submitUser()" class="btn btn-primary" style="flex:2;" id="submitBtn">Create User</button>
            </div>
        </div>
    </div>
</div>

{{-- ── Reset Password Modal ── --}}
<div id="pwModal" style="display:none; position:fixed; inset:0; z-index:1000; background:rgba(0,0,0,.7); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; width:min(400px,95vw);">
        <div style="padding:18px 22px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
            <span style="font-family:var(--font-display); font-weight:700; font-size:15px;">Reset Password</span>
            <button onclick="closePwModal()" style="background:none;border:none;color:var(--subtle);cursor:pointer;font-size:22px;line-height:1;">×</button>
        </div>
        <div style="padding:22px;">
            <div id="pwModalError" style="display:none; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); border-radius:8px; padding:10px 14px; font-size:12px; color:var(--danger); margin-bottom:16px;"></div>
            <p style="font-size:12px; color:var(--subtle); margin-bottom:18px;">Setting new password for: <span id="pwUserName" style="color:var(--text); font-weight:600;"></span></p>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">New Password</label>
                <input id="pw_new" type="password" placeholder="Min 8 chars, uppercase + number" style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
            </div>
            <div style="margin-bottom:22px;">
                <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Confirm Password</label>
                <input id="pw_confirm" type="password" placeholder="Repeat password" style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
            </div>
            <div style="display:flex; gap:10px;">
                <button onclick="closePwModal()" class="btn btn-ghost" style="flex:1;">Cancel</button>
                <button onclick="submitPassword()" class="btn btn-primary" style="flex:2;">Reset Password</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
let editingId   = null;
let pwUserId    = null;

// ── Modal helpers ──────────────────────────────────────────────────────────

function openModal() {
    editingId = null;
    document.getElementById('modalTitle').textContent  = 'Add User';
    document.getElementById('submitBtn').textContent   = 'Create User';
    document.getElementById('passwordField').style.display = 'block';
    document.getElementById('f_name').value       = '';
    document.getElementById('f_email').value      = '';
    document.getElementById('f_password').value   = '';
    document.getElementById('f_role').value       = 'driver';
    document.getElementById('f_vehicle_id').value = '';
    document.getElementById('f_is_active').checked = true;
    document.getElementById('modalError').style.display = 'none';
    toggleVehicleField();
    document.getElementById('userModal').style.display = 'flex';
}

function openEditModal(id, data) {
    editingId = id;
    document.getElementById('modalTitle').textContent  = 'Edit User';
    document.getElementById('submitBtn').textContent   = 'Save Changes';
    document.getElementById('passwordField').style.display = 'none';
    document.getElementById('f_name').value       = data.name;
    document.getElementById('f_email').value      = data.email;
    document.getElementById('f_role').value       = data.role;
    document.getElementById('f_vehicle_id').value = data.vehicle_id ?? '';
    document.getElementById('f_is_active').checked = data.is_active == 1;
    document.getElementById('modalError').style.display = 'none';
    toggleVehicleField();
    document.getElementById('userModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('userModal').style.display = 'none';
}

function openPasswordModal(id, name) {
    pwUserId = id;
    document.getElementById('pwUserName').textContent = name;
    document.getElementById('pw_new').value     = '';
    document.getElementById('pw_confirm').value = '';
    document.getElementById('pwModalError').style.display = 'none';
    document.getElementById('pwModal').style.display = 'flex';
}

function closePwModal() {
    document.getElementById('pwModal').style.display = 'none';
}

function toggleVehicleField() {
    const role = document.getElementById('f_role').value;
    document.getElementById('vehicleField').style.display = role === 'driver' ? 'block' : 'none';
}

// ── Submit ─────────────────────────────────────────────────────────────────

async function submitUser() {
    const body = {
        name:       document.getElementById('f_name').value,
        email:      document.getElementById('f_email').value,
        role:       document.getElementById('f_role').value,
        vehicle_id: document.getElementById('f_vehicle_id').value || null,
        is_active:  document.getElementById('f_is_active').checked ? 1 : 0,
    };

    if (!editingId) {
        body.password = document.getElementById('f_password').value;
    }

    const url    = editingId ? `/fleet/users/${editingId}` : '/fleet/users';
    const method = editingId ? 'PUT' : 'POST';

    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body),
    });

    const json = await res.json();

    if (!res.ok) {
        const errDiv = document.getElementById('modalError');
        const msg = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || 'An error occurred.');
        errDiv.textContent = msg;
        errDiv.style.display = 'block';
        return;
    }

    closeModal();
    location.reload();
}

async function submitPassword() {
    const newPw  = document.getElementById('pw_new').value;
    const confirm = document.getElementById('pw_confirm').value;
    const errDiv  = document.getElementById('pwModalError');

    if (newPw !== confirm) {
        errDiv.textContent = 'Passwords do not match.';
        errDiv.style.display = 'block';
        return;
    }

    const res = await fetch(`/fleet/users/${pwUserId}/password`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ password: newPw, password_confirmation: confirm }),
    });

    const json = await res.json();

    if (!res.ok) {
        const msg = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || 'Error.');
        errDiv.textContent = msg;
        errDiv.style.display = 'block';
        return;
    }

    closePwModal();
    alert('Password reset successfully.');
}

async function deleteUser(id, name) {
    if (!confirm(`Delete user "${name}"? This cannot be undone.`)) return;

    const res = await fetch(`/fleet/users/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF },
    });

    const json = await res.json();
    if (json.error) { alert(json.error); return; }
    location.reload();
}

// Close modals on backdrop click
document.getElementById('userModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });
document.getElementById('pwModal').addEventListener('click',   e => { if (e.target === e.currentTarget) closePwModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal(); closePwModal(); } });
</script>
@endpush
