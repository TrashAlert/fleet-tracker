@extends('layouts.app')
@section('title', 'Shipment Requests')

@section('content')

{{-- ── Header ─────────────────────────────────────────────────────────── --}}
<div class="tk-head">
    <div>
        <div class="tk-title">Shipment Requests</div>
        <div class="tk-subtitle">
            {{ $tickets->total() }} shown — {{ $statusCounts['pending'] ?? 0 }} pending review
        </div>
    </div>
</div>

{{-- ── Filters: status chips + code search ───────────────────────────── --}}
@php
    $chips = [
        'pending'  => ['label' => 'Pending',  'color' => 'var(--warning)'],
        'approved' => ['label' => 'Approved', 'color' => 'var(--success)'],
        'denied'   => ['label' => 'Denied',   'color' => 'var(--danger)'],
    ];
    $qs = function (array $merge) {
        $params = array_filter(array_merge(request()->except('page'), $merge));
        return route('fleet.tickets') . ($params ? '?' . http_build_query($params) : '');
    };
@endphp
<div class="tk-chips">
    <a href="{{ $qs(['status' => null]) }}" class="tk-chip {{ !request('status') ? 'active' : '' }}">
        All <span class="tk-chip-count">{{ array_sum($statusCounts) }}</span>
    </a>
    @foreach($chips as $key => $meta)
    <a href="{{ $qs(['status' => $key]) }}" class="tk-chip {{ request('status') === $key ? 'active' : '' }}" style="--chip-color: {{ $meta['color'] }};">
        {{ $meta['label'] }} <span class="tk-chip-count">{{ $statusCounts[$key] ?? 0 }}</span>
    </a>
    @endforeach

    {{-- Code lookup — the customer reads their request code to staff --}}
    <form method="GET" style="display:flex; gap:8px; margin-left:auto;">
        @if(request('status'))
            <input type="hidden" name="status" value="{{ request('status') }}">
        @endif
        <input type="search" name="q" class="tk-search" placeholder="Request code or name…"
               value="{{ request('q') }}" maxlength="60" spellcheck="false">
        <button type="submit" class="tk-chip" style="cursor:pointer;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Search
        </button>
        @if(request('q'))
        <a href="{{ $qs(['q' => null]) }}" class="tk-chip">Clear</a>
        @endif
    </form>
</div>

{{-- ── Requests table ────────────────────────────────────────────────── --}}
<div class="card">
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Submitted</th>
                    <th>Request Code</th>
                    <th>Customer</th>
                    <th>Destination</th>
                    <th>Preferred Date</th>
                    <th>Status</th>
                    <th style="text-align:center; width:190px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                @php
                    $pill = match($ticket->status) {
                        'pending'  => ['class' => 'pill-delayed',   'label' => 'Pending'],
                        'approved' => ['class' => 'pill-delivered', 'label' => 'Approved'],
                        'denied'   => ['class' => 'pill-offline',   'label' => 'Denied'],
                        default    => ['class' => '',               'label' => $ticket->status],
                    };
                @endphp
                <tr id="ticket-row-{{ $ticket->id }}">
                    <td style="font-size:12px;">
                        {{ $ticket->created_at->format('d M Y, H:i') }}
                    </td>
                    <td>
                        <span class="mono" style="color:var(--accent); font-size:14px; font-weight:700; letter-spacing:.08em;">{{ $ticket->request_code }}</span>
                    </td>
                    <td>
                        <div style="font-weight:600; font-size:12px;">{{ $ticket->client_name }}</div>
                        <div style="font-size:10px; color:var(--subtle);">{{ $ticket->client_email }}{{ $ticket->client_phone ? ' · ' . $ticket->client_phone : '' }}</div>
                    </td>
                    <td style="font-size:11px; color:var(--subtle); max-width:200px;">
                        <div style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $ticket->destination_address }}">
                            {{ $ticket->destination_address }}
                        </div>
                        @if($ticket->delivery_notes)
                        <div style="font-size:10px; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $ticket->delivery_notes }}">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            {{ $ticket->delivery_notes }}
                        </div>
                        @endif
                    </td>
                    <td style="font-size:12px;">{{ $ticket->requested_delivery_at?->format('d M Y, H:i') ?? '—' }}</td>
                    <td>
                        <span class="pill {{ $pill['class'] }}" id="ticket-pill-{{ $ticket->id }}">{{ $pill['label'] }}</span>
                        @if($ticket->status === 'approved' && $ticket->createdShipment)
                        <div style="font-size:10px; color:var(--subtle); margin-top:3px;">
                            → <span class="mono" style="color:var(--accent);">{{ $ticket->createdShipment->tracking_code }}</span>
                        </div>
                        @elseif($ticket->status !== 'pending' && $ticket->reviewer)
                        <div style="font-size:10px; color:var(--subtle); margin-top:3px;">by {{ $ticket->reviewer->name }}</div>
                        @endif
                    </td>
                    <td style="text-align:center;">
                        @if($ticket->status === 'pending')
                        <div style="display:inline-flex; gap:8px;" id="ticket-actions-{{ $ticket->id }}">
                            <a href="{{ route('fleet.shipments') }}?from_ticket={{ $ticket->id }}" class="btn btn-primary" style="padding:6px 12px; font-size:11px;">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px;"><polyline points="20 6 9 17 4 12"/></svg>
                                Approve &amp; Create
                            </a>
                            <button onclick="denyTicket({{ $ticket->id }})" class="btn btn-ghost" style="padding:6px 12px; font-size:11px;" id="denyBtn-{{ $ticket->id }}">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                Deny
                            </button>
                        </div>
                        @else
                        <span style="font-size:11px; color:var(--subtle);">Reviewed {{ $ticket->reviewed_at?->format('d M, H:i') }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px; color:var(--subtle); font-size:12px;">
                        @if(request('q'))
                            No request matches "{{ request('q') }}".
                        @else
                            No shipment requests{{ request('status') ? ' with this status' : ' yet' }}.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($tickets->hasPages())
<div style="margin-top:16px;">{{ $tickets->links() }}</div>
@endif

{{-- Toast --}}
<div id="toast" style="
    display:none; position:fixed; bottom:24px; right:24px; z-index:500;
    background:var(--surface); border:1px solid var(--border); border-radius:8px;
    padding:12px 18px; font-size:12px; color:var(--text);
    box-shadow:0 8px 24px rgba(0,0,0,.4); min-width:240px;
"></div>

@endsection

@push('styles')
<style>
.tk-head { display:flex; align-items:center; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.tk-title { font-family:var(--font-display); font-weight:700; font-size:18px; }
.tk-subtitle { font-size:11px; color:var(--subtle); margin-top:3px; }

.tk-chips { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:16px; }
.tk-chip {
    display:inline-flex; align-items:center; gap:6px;
    border:1px solid var(--border); border-radius:8px;
    background:var(--surface); padding:7px 12px;
    font-family:var(--font-mono); font-size:11px; color:var(--subtle);
    text-decoration:none; transition:all .15s;
}
.tk-chip:hover { border-color:var(--chip-color, var(--accent)); }
.tk-chip.active { border-color:var(--chip-color, var(--accent)); color:var(--text); }
.tk-chip-count { font-weight:700; color:var(--chip-color, var(--accent)); }
.tk-search {
    background:var(--surface); border:1px solid var(--border); border-radius:8px;
    color:var(--text); font-family:var(--font-mono); font-size:11px;
    padding:7px 12px; outline:none; min-width:190px; text-transform:uppercase;
}
.tk-search::placeholder { text-transform:none; }
.tk-search:focus { border-color:var(--accent); }
</style>
@endpush

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.borderColor = ok ? 'var(--success)' : 'var(--danger)';
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3500);
}

async function denyTicket(id) {
    if (!confirm('Deny this shipment request? The customer will not be emailed.')) return;

    const btn = document.getElementById(`denyBtn-${id}`);
    btn.disabled = true;

    try {
        const res  = await fetch(`/fleet/api/tickets/${id}/deny`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        });
        const data = await res.json();

        if (!res.ok) {
            showToast(data.error || 'Could not deny this request.', false);
            return;
        }

        // Update the row in place
        const pill = document.getElementById(`ticket-pill-${id}`);
        pill.textContent = 'Denied';
        pill.className = 'pill pill-offline';
        document.getElementById(`ticket-actions-${id}`).outerHTML =
            '<span style="font-size:11px; color:var(--subtle);">Reviewed just now</span>';
        showToast('Request denied.');
    } catch (e) {
        showToast('Something went wrong. Please try again.', false);
    } finally {
        btn.disabled = false;
    }
}
</script>
@endpush
