@extends('layouts.app')
@section('title', 'Shipments')

@section('content')

{{-- ── Header ─────────────────────────────────────────────────────────── --}}
<div class="sh-head">
    <div>
        <div class="sh-title">Shipments</div>
        <div class="sh-subtitle">
            {{ $shipments->total() }} total
            @if(auth()->user()->isDriver()) — showing your vehicle only @endif
        </div>
    </div>
    <span style="flex:1"></span>
    @if(!auth()->user()->isDriver())
    <button onclick="openCreateModal()" class="chip chip-accent" type="button" style="cursor:pointer;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span class="chip-label" style="color:var(--accent); font-weight:600;">New Shipment</span>
    </button>
    @endif
</div>

{{-- ── Status Pipeline Bar (click to filter; click again to clear) ─────── --}}
@php
    $pipeline = [
        'pending' => [
            'label' => 'Pending', 'color' => 'var(--subtle)',
            'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        ],
        'in_transit' => [
            'label' => 'In Transit', 'color' => 'var(--accent)',
            'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
        ],
        'delayed' => [
            'label' => 'Delayed', 'color' => 'var(--warning)',
            'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        ],
        'delivered' => [
            'label' => 'Delivered', 'color' => 'var(--success)',
            'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        ],
        'cancelled' => [
            'label' => 'Cancelled', 'color' => 'var(--danger)',
            'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        ],
    ];
    $qsWithout = fn ($key) => '?' . http_build_query(array_filter(request()->except($key, 'page')));
@endphp
<div class="sh-pipeline">
    @foreach($pipeline as $key => $meta)
    @php
        $count    = $statusCounts[$key] ?? 0;
        $isActive = request('status') === $key;
        $href     = $isActive
            ? route('fleet.shipments') . $qsWithout('status')
            : '?' . http_build_query(array_merge(request()->except('page'), ['status' => $key]));
    @endphp
    <a href="{{ $href }}" class="sh-pipe-card {{ $isActive ? 'active' : '' }}" style="--sc: {{ $meta['color'] }};"
       title="{{ $isActive ? 'Clear this filter' : 'Filter: ' . $meta['label'] }}">
        <div class="sh-pipe-top">
            <span class="sh-pipe-icon">{!! $meta['icon'] !!}</span>
            <span class="sh-pipe-label">{{ $meta['label'] }}</span>
            @if($isActive)
                <span class="sh-pipe-clear">
                    <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Clear
                </span>
            @endif
        </div>
        <div class="sh-pipe-count {{ $count === 0 ? 'zero' : '' }}">{{ number_format($count) }}</div>
    </a>
    @endforeach
</div>

{{-- ── Filter Bar ───────────────────────────────────────────────────────── --}}
<form method="GET" class="sh-filters">
    @if(request('status'))
        <input type="hidden" name="status" value="{{ request('status') }}">
    @endif
    @if(!auth()->user()->isDriver())
    <select name="vehicle_id" class="filter-select" onchange="this.form.submit()">
        <option value="">All Vehicles</option>
        @foreach($vehicles as $v)
            <option value="{{ $v->id }}" @selected(request('vehicle_id') == $v->id)>
                {{ $v->plate_number }} — {{ $v->name }}
            </option>
        @endforeach
    </select>
    @endif
    <input type="date" name="date_from" class="filter-input" value="{{ request('date_from') }}" onchange="this.form.submit()">
    <input type="date" name="date_to"   class="filter-input" value="{{ request('date_to') }}"   onchange="this.form.submit()">
    <input type="search" id="pageSearch" class="filter-input" placeholder="Filter this page…" oninput="filterRows()" style="min-width:150px;">
    @if(request()->anyFilled(['status','vehicle_id','date_from','date_to']))
    <a href="{{ route('fleet.shipments') }}" class="btn btn-ghost sh-clear">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Clear
    </a>
    @endif
    <span style="margin-left:auto; font-size:11px; color:var(--subtle);">
        Showing {{ $shipments->firstItem() }}–{{ $shipments->lastItem() }} of {{ $shipments->total() }}
    </span>
</form>

{{-- ── Shipments Table ──────────────────────────────────────────────────── --}}
<div class="card">
    <div style="overflow-x:auto;">
        <table class="data-table" id="shipTable">
            <thead>
                <tr>
                    <th>Tracking Code</th>
                    <th>Client</th>
                    <th>Vehicle</th>
                    <th>Destination</th>
                    <th>Expected By</th>
                    <th>Status</th>
                    <th style="text-align:center; width:110px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($shipments as $shipment)
                @php
                    $statusStyle = match($shipment->status) {
                        'pending'    => ['pill-class' => 'pill-offline',   'label' => 'Pending'],
                        'in_transit' => ['pill-class' => 'pill-transit',   'label' => 'In Transit'],
                        'delayed'    => ['pill-class' => 'pill-delayed',   'label' => 'Delayed'],
                        'delivered'  => ['pill-class' => 'pill-delivered', 'label' => 'Delivered'],
                        'cancelled'  => ['pill-class' => 'pill-offline',   'label' => 'Cancelled'],
                        default      => ['pill-class' => '',               'label' => $shipment->status],
                    };
                    $isOverdue = $shipment->status === 'in_transit'
                        && $shipment->expected_delivery_at
                        && $shipment->expected_delivery_at->isPast();
                @endphp
                <tr class="sh-row" onclick="openDrawer({{ $shipment->id }})">
                    <td>
                        <span class="mono" style="color:var(--accent); font-size:12px;">{{ $shipment->tracking_code }}</span>
                    </td>
                    <td>
                        <div style="font-weight:600; font-size:12px;">{{ $shipment->client_name }}</div>
                        <div style="font-size:10px; color:var(--subtle);">{{ $shipment->client_email }}</div>
                    </td>
                    <td style="font-size:12px;">
                        {{ $shipment->vehicle?->plate_number ?? '—' }}
                        <div style="font-size:10px; color:var(--subtle);">{{ $shipment->vehicle?->driver_name }}</div>
                    </td>
                    <td style="font-size:11px; color:var(--subtle); max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        {{ $shipment->destination_address }}
                    </td>
                    <td>
                        <span style="font-size:12px; {{ $isOverdue ? 'color:var(--danger);' : '' }}">
                            {{ $shipment->expected_delivery_at?->format('d M Y, H:i') ?? '—' }}
                        </span>
                        @if($isOverdue)
                            <div style="font-size:9px; color:var(--danger);">OVERDUE</div>
                        @endif
                    </td>
                    <td>
                        <span class="pill {{ $statusStyle['pill-class'] }}">{{ $statusStyle['label'] }}</span>
                    </td>
                    <td style="text-align:center;" onclick="event.stopPropagation()">
                        <div style="display:flex; gap:5px; justify-content:center;">
                            <button class="sh-icon-btn" onclick="copyTrackingLink('{{ $shipment->tracking_code }}')" title="Copy tracking link" aria-label="Copy tracking link">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                            </button>
                            <button class="sh-icon-btn" onclick="openDrawer({{ $shipment->id }})" title="View detail" aria-label="View detail">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:52px; color:var(--subtle);">
                        No shipments found.
                        @if(!auth()->user()->isDriver())
                            <br><br>
                            <button onclick="openCreateModal()" class="btn btn-primary" style="margin-top:8px;">Create First Shipment</button>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($shipments->hasPages())
    <div style="padding:14px 18px; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
        <span style="font-size:11px; color:var(--subtle);">Page {{ $shipments->currentPage() }} of {{ $shipments->lastPage() }}</span>
        <div style="display:flex; gap:6px;">
            @if($shipments->onFirstPage())
                <span class="btn btn-ghost sh-page-btn" style="opacity:.35; cursor:default;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg> Prev
                </span>
            @else
                <a href="{{ $shipments->previousPageUrl() }}" class="btn btn-ghost sh-page-btn">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg> Prev
                </a>
            @endif
            @if($shipments->hasMorePages())
                <a href="{{ $shipments->nextPageUrl() }}" class="btn btn-ghost sh-page-btn">
                    Next <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            @else
                <span class="btn btn-ghost sh-page-btn" style="opacity:.35; cursor:default;">
                    Next <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
            @endif
        </div>
    </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     DETAIL DRAWER
═══════════════════════════════════════════════════════════════════════ --}}
<div id="drawer">
    {{-- Drawer Header --}}
    <div style="padding:18px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
        <div>
            <div id="drawerCode" style="font-family:var(--font-display); font-weight:700; font-size:15px; color:var(--accent);"></div>
            <div id="drawerCreated" style="font-size:10px; color:var(--subtle); margin-top:2px;"></div>
        </div>
        <button onclick="closeDrawer()" class="sh-close" aria-label="Close">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    {{-- Drawer Body --}}
    <div style="flex:1; overflow-y:auto; padding:18px 20px;">

        {{-- Status + actions --}}
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
            <span id="drawerStatus" class="pill"></span>
            @if(!auth()->user()->isDriver())
            <div style="display:flex; gap:6px;" id="statusActions">
                <select id="statusOverride" style="background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:5px 9px; font-family:var(--font-mono); font-size:11px; color:var(--text); outline:none;">
                    <option value="">Change status…</option>
                    <option value="pending">Pending</option>
                    <option value="in_transit">In Transit</option>
                    <option value="delayed">Delayed</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <button onclick="applyStatusOverride()" class="btn btn-ghost" style="padding:5px 10px; font-size:11px;">Apply</button>
            </div>
            @endif
        </div>

        {{-- Mini map (z-index contained so the mobile nav drawer covers it) --}}
        <div style="position:relative; z-index:0; height:180px; border-radius:8px; overflow:hidden; margin-bottom:18px; border:1px solid var(--border); background:var(--bg);">
            <div id="drawerMap" style="position:absolute; inset:0;"></div>
        </div>

        {{-- Client info --}}
        <div style="margin-bottom:18px;">
            <div class="sh-section-label">Client</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div class="info-block" id="dClientName"></div>
                <div class="info-block" id="dClientEmail"></div>
                <div class="info-block" id="dClientPhone"></div>
            </div>
        </div>

        {{-- Route --}}
        <div style="margin-bottom:18px;">
            <div class="sh-section-label">Route</div>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <div style="display:flex; gap:10px; align-items:flex-start;">
                    <div style="width:8px; height:8px; border-radius:50%; background:var(--success); flex-shrink:0; margin-top:4px;"></div>
                    <div style="font-size:12px;" id="dOrigin"></div>
                </div>
                <div style="width:1px; height:16px; background:var(--border); margin-left:3px;"></div>
                <div style="display:flex; gap:10px; align-items:flex-start;">
                    <div style="width:8px; height:8px; border-radius:50%; background:var(--danger); flex-shrink:0; margin-top:4px;"></div>
                    <div style="font-size:12px;" id="dDestination"></div>
                </div>
            </div>
        </div>

        {{-- Delivery instructions (only shown when present) --}}
        <div style="margin-bottom:18px; display:none;" id="dNotesWrap">
            <div class="sh-section-label">Delivery Instructions</div>
            <div id="dNotes" style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:12px; font-size:12px; white-space:pre-wrap;"></div>
        </div>

        {{-- Vehicle --}}
        <div style="margin-bottom:18px;">
            <div class="sh-section-label">Vehicle</div>
            <div id="dVehicle" style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:12px;"></div>
        </div>

        {{-- Timing --}}
        <div style="margin-bottom:18px;">
            <div class="sh-section-label">Timing</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div class="info-block" id="dExpected"></div>
                <div class="info-block" id="dDelivered"></div>
            </div>
        </div>

        {{-- Proof of Delivery --}}
        <div id="dProofSection" style="margin-bottom:18px; display:none;">
            <div class="sh-section-label">Proof of Delivery</div>
            <a id="dProofLink" href="#" target="_blank" rel="noopener"
                style="display:block; border:1px solid var(--border); border-radius:8px; overflow:hidden; background:var(--bg);">
                <img id="dProofImg" src="" alt="Proof of delivery photo"
                    style="display:block; width:100%; max-height:220px; object-fit:cover;">
            </a>
            <div id="dProofError" style="display:none; border:1px solid rgba(239,68,68,0.25); border-radius:8px; padding:12px; font-size:11px; color:var(--danger); background:rgba(239,68,68,0.05);">
                Photo could not be loaded. Check that <span class="mono">php artisan storage:link</span> has been run and that the web server can read <span class="mono">/storage</span> files.
            </div>
        </div>

        {{-- Recent Alerts --}}
        <div id="dAlertsSection" style="margin-bottom:18px; display:none;">
            <div class="sh-section-label">Recent Alerts</div>
            <div id="dAlerts"></div>
        </div>

        {{-- Tracking link + QR --}}
        <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:12px;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                <div style="min-width:0;">
                    <div style="font-size:10px; color:var(--subtle); margin-bottom:4px;">Public Tracking Link</div>
                    <div id="dTrackLink" class="mono" style="font-size:11px; color:var(--accent); word-break:break-all;"></div>
                </div>
                <div style="display:flex; gap:6px; flex-shrink:0;">
                    <button id="copyBtn" onclick="copyDrawerLink()" class="sh-icon-btn" title="Copy link" aria-label="Copy tracking link" style="width:auto; padding:0 10px; gap:6px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                        Copy
                    </button>
                    <a id="dTrackOpen" href="#" target="_blank" rel="noopener" class="sh-icon-btn" title="Open tracking page" aria-label="Open tracking page">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Drawer backdrop --}}
<div id="drawerBackdrop" onclick="closeDrawer()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:199;"></div>

{{-- ══════════════════════════════════════════════════════════════════════
     CREATE SHIPMENT MODAL
═══════════════════════════════════════════════════════════════════════ --}}
@if(!auth()->user()->isDriver())
<div id="createModal" style="display:none; position:fixed; inset:0; z-index:300; background:rgba(0,0,0,.7); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; width:min(580px,95vw); max-height:90vh; overflow-y:auto;">

        <div style="padding:18px 22px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; background:var(--surface); z-index:1;">
            <span style="font-family:var(--font-display); font-weight:700; font-size:15px;">New Shipment</span>
            <button onclick="closeCreateModal()" class="sh-close" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div style="padding:22px;">
            <div id="createError" style="display:none; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); border-radius:8px; padding:10px 14px; font-size:12px; color:var(--danger); margin-bottom:16px;"></div>

            {{-- Shown when prefilled from a forwarding request --}}
            <div id="createTicketBanner" style="display:none; background:rgba(0,229,255,0.06); border:1px solid rgba(0,229,255,0.25); border-radius:8px; padding:10px 14px; font-size:12px; color:var(--accent); margin-bottom:16px;"></div>

            {{-- Section: Vehicle --}}
            <div class="sh-form-section">Vehicle</div>
            <div style="margin-bottom:14px;">
                <select id="c_vehicle_id" class="sh-input">
                    <option value="">— Select vehicle (least busy first) —</option>
                    @foreach($vehicles as $v)
                        @php $isFull = $v->active_shipments_count >= $maxActive; @endphp
                        <option value="{{ $v->id }}" @disabled($isFull)>
                            {{ $v->plate_number }} — {{ $v->name }}
                            ({{ $v->active_shipments_count }} active{{ $isFull ? ' — FULL' : '' }})
                        </option>
                    @endforeach
                </select>
                <div style="font-size:10px; color:var(--subtle); margin-top:5px;">
                    Sorted by workload. Vehicles at {{ $maxActive }} active deliveries cannot accept more.
                </div>
            </div>

            {{-- Section: Client --}}
            <div class="sh-form-section" style="margin-top:18px;">Client Information</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                @foreach([
                    ['id'=>'c_client_name',  'label'=>'Client Name',  'type'=>'text',  'placeholder'=>'Company or person'],
                    ['id'=>'c_client_email', 'label'=>'Client Email', 'type'=>'email', 'placeholder'=>'client@example.com'],
                    ['id'=>'c_client_phone', 'label'=>'Phone',        'type'=>'tel',   'placeholder'=>'+60 1X-XXXXXXX'],
                ] as $f)
                <div>
                    <label class="sh-label">{{ $f['label'] }}</label>
                    <input id="{{ $f['id'] }}" type="{{ $f['type'] }}" placeholder="{{ $f['placeholder'] }}" class="sh-input">
                </div>
                @endforeach
            </div>

            {{-- Section: Route --}}
            <div class="sh-form-section" style="margin-top:18px;">Route</div>

            {{-- Origin: preset selector or manual --}}
            <div style="margin-bottom:14px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label class="sh-label" style="margin-bottom:0;">Origin / Pickup</label>
                    <button type="button" onclick="toggleOriginMode()" id="originModeToggle"
                        style="font-size:10px; font-family:var(--font-mono); background:none; border:none; color:var(--accent); cursor:pointer; text-decoration:underline;">
                        Type manually instead
                    </button>
                </div>

                {{-- Preset selector --}}
                <div id="originPresetWrap">
                    <select id="c_origin_preset" onchange="applyOriginPreset()" class="sh-input">
                        <option value="">-- Select a preset warehouse --</option>
                    </select>
                    <div style="font-size:10px; color:var(--subtle); margin-top:5px;" id="originPresetDetail"></div>
                </div>

                {{-- Manual input (hidden by default) --}}
                <div id="originManualWrap" style="display:none;">
                    <input id="c_origin" type="text" placeholder="Warehouse / pickup location" class="sh-input">
                </div>

                <input type="hidden" id="c_origin_address">
            </div>
            <div style="margin-bottom:14px; position:relative;">
                <label class="sh-label">Destination Address</label>
                <input id="c_destination_address" type="text" placeholder="Search an address…" class="sh-input"
                       autocomplete="off" oninput="onAddressInput()">
                <div id="addrHint" style="font-size:10px; color:var(--subtle); margin-top:5px;">
                    Type to search — picking a result drops the map pin. Clicking the map fills the address back.
                </div>
                {{-- Autocomplete suggestions (Nominatim) --}}
                <div id="addrSuggest" style="display:none; position:absolute; left:0; right:0; top:100%; z-index:20;
                    background:var(--surface); border:1px solid var(--border); border-radius:8px; margin-top:4px;
                    max-height:220px; overflow-y:auto; box-shadow:0 8px 24px rgba(0,0,0,.35);"></div>
            </div>

            {{-- Delivery instructions — the geocoded address can't capture unit/floor/gate --}}
            <div style="margin-bottom:14px;">
                <label class="sh-label">Delivery Instructions <span style="text-transform:none; letter-spacing:0; color:var(--subtle);">(optional)</span></label>
                <textarea id="c_delivery_notes" rows="2" class="sh-input" placeholder="Unit / floor / gate code, landmark, or notes for the driver"></textarea>
            </div>

            {{-- Destination Coordinates — toggle between map pin and manual --}}
            <div style="margin-bottom:6px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                    <label class="sh-label" style="margin-bottom:0;">Destination Coordinates</label>
                    <div class="seg">
                        <button type="button" id="tabMap" class="seg-btn active" onclick="switchCoordTab('map')">Pin on Map</button>
                        <button type="button" id="tabManual" class="seg-btn" onclick="switchCoordTab('manual')">Manual</button>
                    </div>
                </div>

                {{-- Map picker (z-index contained) --}}
                <div id="coordTabMap">
                    <div style="position:relative; z-index:0; height:200px; border-radius:8px; overflow:hidden; border:1px solid var(--border); background:var(--bg); margin-bottom:8px;">
                        <div id="createPickerMap" style="position:absolute; inset:0;"></div>
                    </div>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <div id="coordPreview" style="flex:1; font-family:var(--font-mono); font-size:11px; color:var(--subtle); background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:8px 12px;">
                            Click the map to pin destination
                        </div>
                        <button type="button" onclick="clearMapPin()" style="background:var(--muted); border:none; border-radius:6px; padding:7px 10px; color:var(--subtle); font-size:11px; cursor:pointer;">Clear</button>
                    </div>
                </div>

                {{-- Manual input --}}
                <div id="coordTabManual" style="display:none;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div>
                            <label class="sh-label">Latitude</label>
                            <input id="c_dest_lat_manual" type="number" step="any" placeholder="e.g. 3.1390" oninput="syncManualToHidden()" class="sh-input">
                        </div>
                        <div>
                            <label class="sh-label">Longitude</label>
                            <input id="c_dest_lng_manual" type="number" step="any" placeholder="e.g. 101.6869" oninput="syncManualToHidden()" class="sh-input">
                        </div>
                    </div>
                    <div style="font-size:10px; color:var(--subtle); margin-top:6px;">
                        Tip: You can get coordinates from Google Maps — right-click a location and copy the numbers.
                    </div>
                </div>

                {{-- Hidden fields always submitted --}}
                <input type="hidden" id="c_dest_lat">
                <input type="hidden" id="c_dest_lng">
            </div>

            <div style="margin-bottom:22px; margin-top:14px;">
                <label class="sh-label">Expected Delivery</label>
                <input id="c_expected_at" type="datetime-local" class="sh-input">
            </div>

            <div style="display:flex; gap:10px;">
                <button onclick="closeCreateModal()" class="btn btn-ghost" style="flex:1;">Cancel</button>
                <button onclick="submitShipment()" class="btn btn-primary" style="flex:2;" id="createBtn">Create Shipment</button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Toast notification --}}
<div id="toast" style="
    display:none; position:fixed; bottom:24px; right:24px; z-index:500;
    background:var(--surface); border:1px solid var(--border); border-radius:8px;
    padding:12px 18px; font-size:12px; color:var(--text);
    box-shadow:0 8px 24px rgba(0,0,0,.4); min-width:240px;
"></div>

@endsection

@push('styles')
<style>
/* ── Same map treatment as the dashboard — class-scoped with a light-mode
      override (the old id-scoped rules out-specificised the light override,
      leaving these maps dark-filtered in light mode) ── */
.leaflet-tile-pane    { filter: brightness(0.65) saturate(0.7) hue-rotate(185deg); }
.leaflet-container    { background: #0a0b0e; }
html[data-theme="light"] .leaflet-tile-pane { filter: none; }
html[data-theme="light"] .leaflet-container { background: #dce3e8; }

/* ── Header ── */
.sh-head { display:flex; align-items:center; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.sh-title { font-family:var(--font-display); font-weight:700; font-size:18px; }
.sh-subtitle { font-size:11px; color:var(--subtle); margin-top:3px; }

.chip {
    display:flex; align-items:center; gap:8px;
    border:1px solid var(--border); border-radius:8px;
    background:var(--surface); padding:8px 12px;
    font-family:var(--font-display); text-decoration:none; color:var(--text);
}
.chip-label { font-size:11px; color:var(--subtle); }
.chip-accent { border-color:rgba(0,229,255,0.4); background:none; }
html[data-theme="light"] .chip-accent { border-color:rgba(0,119,182,0.4); }
.chip-accent svg { stroke:var(--accent); }

.seg { display:flex; border:1px solid var(--border); border-radius:6px; overflow:hidden; }
.seg-btn {
    background:var(--muted); border:none; cursor:pointer;
    padding:4px 12px; font-size:10px; font-family:var(--font-mono); color:var(--subtle);
    transition:all .15s;
}
.seg-btn.active { background:rgba(0,229,255,0.12); color:var(--accent); font-weight:600; }
html[data-theme="light"] .seg-btn.active { background:rgba(0,119,182,0.10); }

/* ── Pipeline ── */
.sh-pipeline { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; margin-bottom:20px; }
@media (max-width: 760px) { .sh-pipeline { grid-template-columns:repeat(2,1fr); } }
.sh-pipe-card {
    --sc: var(--accent);
    position:relative; display:block; text-decoration:none;
    background:var(--surface); border:1px solid var(--border); border-radius:12px;
    padding:14px 16px 16px;
    transition:border-color .15s, transform .15s, box-shadow .15s, background .15s;
}
.sh-pipe-card:hover {
    border-color: color-mix(in srgb, var(--sc) 55%, var(--border));
    transform: translateY(-1px);
    box-shadow: 0 6px 16px color-mix(in srgb, var(--sc) 12%, transparent);
}
.sh-pipe-card.active {
    background: color-mix(in srgb, var(--sc) 7%, var(--surface));
    border-color: var(--sc);
}
.sh-pipe-top { display:flex; align-items:center; gap:8px; margin-bottom:12px; }
.sh-pipe-icon {
    width:26px; height:26px; border-radius:8px; flex-shrink:0;
    display:inline-flex; align-items:center; justify-content:center;
    background: color-mix(in srgb, var(--sc) 13%, transparent);
    color: var(--sc);
}
.sh-pipe-icon svg { width:14px; height:14px; }
.sh-pipe-label {
    font-size:10px; letter-spacing:.12em; text-transform:uppercase;
    font-weight:600; color:var(--subtle);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.sh-pipe-clear {
    margin-left:auto; flex-shrink:0;
    display:inline-flex; align-items:center; gap:4px;
    font-size:9px; letter-spacing:.08em; text-transform:uppercase; font-weight:700;
    color:var(--sc); background: color-mix(in srgb, var(--sc) 14%, transparent);
    border-radius:5px; padding:3px 7px;
}
.sh-pipe-count {
    font-family:var(--font-display); font-size:30px; font-weight:800;
    line-height:1; color:var(--sc); margin-bottom:2px;
    font-variant-numeric: tabular-nums;
}
.sh-pipe-count.zero { color:var(--subtle); opacity:.55; }

/* ── Filters / table ── */
.sh-filters { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:16px; }
.filter-select, .filter-input {
    background: var(--surface); border: 1px solid var(--border); border-radius: 6px;
    color: var(--text); font-family: var(--font-mono); font-size: 11px;
    padding: 6px 10px; outline: none;
}
.filter-select:focus, .filter-input:focus { border-color: var(--accent); }
.filter-select option { background: var(--surface); }
.sh-clear { padding:6px 12px; font-size:11px; display:inline-flex; align-items:center; gap:5px; }
.sh-page-btn { padding:4px 10px; display:inline-flex; align-items:center; gap:5px; }

.sh-row { cursor:pointer; }
.sh-row:hover td { background:rgba(0,229,255,0.03); }
html[data-theme="light"] .sh-row:hover td { background:rgba(0,119,182,0.04); }

.sh-icon-btn {
    background:var(--muted); border:none; border-radius:6px;
    width:28px; height:28px; cursor:pointer; color:var(--text);
    display:inline-flex; align-items:center; justify-content:center;
    font-size:11px; text-decoration:none;
}
.sh-icon-btn:hover { color:var(--accent); }

.sh-close {
    background:none; border:none; color:var(--subtle); cursor:pointer;
    display:flex; align-items:center; padding:2px;
}
.sh-close:hover { color:var(--text); }

/* ── Drawer: responsive width, transform-based slide ── */
#drawer {
    position:fixed; top:0; right:0; width:min(500px, 100vw); height:100vh;
    background:var(--surface); border-left:1px solid var(--border);
    z-index:200; display:flex; flex-direction:column;
    transform:translateX(105%);
    transition:transform .25s cubic-bezier(.4,0,.2,1);
    box-shadow:-8px 0 32px rgba(0,0,0,.4);
}
#drawer.open { transform:translateX(0); }

.sh-section-label {
    font-size:10px; letter-spacing:.1em; text-transform:uppercase;
    color:var(--subtle); margin-bottom:10px;
}
.sh-form-section {
    font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--accent);
    margin-bottom:12px; padding-bottom:6px; border-bottom:1px solid var(--border);
}
.sh-label {
    display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase;
    color:var(--subtle); margin-bottom:6px;
}
.sh-input {
    width:100%; background:var(--bg); border:1px solid var(--border);
    border-radius:8px; padding:10px 13px; font-family:var(--font-mono);
    font-size:13px; color:var(--text); outline:none;
}
.sh-input:focus { border-color:var(--accent); }

.info-block {
    background: var(--bg); border: 1px solid var(--border); border-radius: 6px;
    padding: 10px 12px; font-size: 12px;
}
.info-block .label {
    font-size: 9px; letter-spacing: .1em; text-transform: uppercase;
    color: var(--subtle); margin-bottom: 4px;
}

.toast-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:6px; vertical-align:middle; }
</style>
@endpush

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// Public tracking links use the tunnel domain — clients cannot open LAN URLs.
// Override with TRACKING_BASE_URL / config('fleet.tracking_base_url') if set.
const TRACK_BASE = @json(rtrim(config('fleet.tracking_base_url', 'https://fleet-tracker.xyz'), '/'));

let currentShipmentId = null;
let drawerMap = null;

// ── Toast ─────────────────────────────────────────────────────────────────
function showToast(msg, color = 'var(--success)') {
    const t = document.getElementById('toast');
    t.style.display = 'block';
    t.style.borderColor = color;
    t.innerHTML = `<span class="toast-dot" style="background:${color};"></span>${msg}`;
    setTimeout(() => { t.style.display = 'none'; }, 3000);
}

// ── Copy helpers ──────────────────────────────────────────────────────────
function trackUrl(code) { return `${TRACK_BASE}/track?code=${code}`; }

function copyTrackingLink(code) {
    navigator.clipboard.writeText(trackUrl(code));
    showToast('Tracking link copied!');
}
function copyDrawerLink() {
    const link = document.getElementById('dTrackLink').textContent;
    navigator.clipboard.writeText(link);
    showToast('Tracking link copied!');
}

// ── Drawer ────────────────────────────────────────────────────────────────
function openDrawer(id) {
    currentShipmentId = id;
    document.getElementById('drawer').classList.add('open');
    document.getElementById('drawerBackdrop').style.display = 'block';
    loadDrawer(id);
}
function closeDrawer() {
    document.getElementById('drawer').classList.remove('open');
    document.getElementById('drawerBackdrop').style.display = 'none';
    currentShipmentId = null;
}

async function loadDrawer(id) {
    const res  = await fetch(`/fleet/api/shipments/${id}`, { headers: { 'Accept': 'application/json' } });
    const s    = await res.json();

    // Header
    document.getElementById('drawerCode').textContent    = s.tracking_code;
    document.getElementById('drawerCreated').textContent = 'Created ' + s.created_at;

    // Status pill
    const pill = document.getElementById('drawerStatus');
    const pillMap = {
        pending:    { class: 'pill-offline',   label: 'Pending' },
        in_transit: { class: 'pill-transit',   label: 'In Transit' },
        delayed:    { class: 'pill-delayed',   label: 'Delayed' },
        delivered:  { class: 'pill-delivered', label: 'Delivered' },
        cancelled:  { class: 'pill-offline',   label: 'Cancelled' },
    };
    const pm = pillMap[s.status] ?? { class: '', label: s.status };
    pill.className = `pill ${pm.class}`;
    pill.textContent = pm.label;

    const sel = document.getElementById('statusOverride');
    if (sel) sel.value = '';

    // Client
    infoBlock('dClientName',  'Client',  s.client_name);
    infoBlock('dClientEmail', 'Email',   s.client_email);
    infoBlock('dClientPhone', 'Phone',   s.client_phone ?? '—');

    // Route
    document.getElementById('dOrigin').textContent      = s.origin_address;
    document.getElementById('dDestination').textContent = s.destination_address;

    // Delivery instructions (hide the block entirely when there are none)
    const notesWrap = document.getElementById('dNotesWrap');
    if (s.delivery_notes) {
        document.getElementById('dNotes').textContent = s.delivery_notes;
        notesWrap.style.display = 'block';
    } else {
        notesWrap.style.display = 'none';
    }

    // Timing
    infoBlock('dExpected',  'Expected By', s.expected_delivery_at ?? '—');
    infoBlock('dDelivered', 'Delivered At', s.actual_delivery_at ?? '—');

    // Proof of delivery photo (shown only if the driver captured one)
    const proofSection = document.getElementById('dProofSection');
    if (s.delivery_photo) {
        const img = document.getElementById('dProofImg');
        const lnk = document.getElementById('dProofLink');
        const err = document.getElementById('dProofError');
        err.style.display = 'none';
        lnk.style.display = 'block';
        img.onerror = () => { lnk.style.display = 'none'; err.style.display = 'block'; };
        img.onload  = () => { err.style.display = 'none'; lnk.style.display = 'block'; };
        img.src  = s.delivery_photo;
        lnk.href = s.delivery_photo;
        proofSection.style.display = '';
    } else {
        proofSection.style.display = 'none';
    }

    // Vehicle
    const vDiv = document.getElementById('dVehicle');
    if (s.vehicle) {
        vDiv.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-weight:600; font-size:13px;">${s.vehicle.plate} — ${s.vehicle.name}</div>
                    <div style="font-size:11px; color:var(--subtle); margin-top:2px;">Driver: ${s.vehicle.driver ?? '—'}</div>
                </div>
                <span class="pill ${s.vehicle.is_offline ? 'pill-offline' : 'pill-online'}">${s.vehicle.is_offline ? 'Offline' : 'Online'}</span>
            </div>
            ${s.vehicle.speed_kmh !== null ? `<div style="margin-top:8px; font-size:11px; color:var(--subtle);">
                Speed: <span style="color:var(--text);">${parseFloat(s.vehicle.speed_kmh).toFixed(1)} km/h</span>
                &nbsp;&middot;&nbsp; Updated: <span style="color:var(--text);">${s.vehicle.recorded_at ?? '—'}</span>
            </div>` : ''}
        `;
    } else {
        vDiv.innerHTML = `<span style="color:var(--subtle); font-size:12px;">No vehicle assigned</span>`;
    }

    // Tracking link + open
    const link = trackUrl(s.tracking_code);
    document.getElementById('dTrackLink').textContent = link;
    document.getElementById('dTrackOpen').href        = link;

    // Alerts
    const alertsSection = document.getElementById('dAlertsSection');
    const alertsDiv     = document.getElementById('dAlerts');
    if (s.alerts && s.alerts.length > 0) {
        alertsSection.style.display = 'block';
        alertsDiv.innerHTML = s.alerts.map(a => `
            <div style="display:flex; gap:10px; padding:9px 0; border-bottom:1px solid var(--border); align-items:flex-start;">
                <div style="flex-shrink:0; margin-top:1px;">${alertIcon(a.type)}</div>
                <div>
                    <div style="font-size:11px;">${a.message}</div>
                    <div style="font-size:10px; color:var(--subtle); margin-top:2px;">${a.triggered_at}</div>
                </div>
            </div>
        `).join('');
    } else {
        alertsSection.style.display = 'none';
    }

    // Mini map
    initDrawerMap(s);
}

function initDrawerMap(s) {
    if (!drawerMap) {
        drawerMap = L.map('drawerMap', { zoomControl: true, attributionControl: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(drawerMap);
    }
    drawerMap.eachLayer(l => { if (l instanceof L.Marker) drawerMap.removeLayer(l); });

    const destIcon = L.divIcon({
        className: '',
        html: `<div style="width:12px;height:12px;border-radius:50%;background:#ef4444;border:2px solid #7f1d1d;box-shadow:0 0 6px #ef444488;"></div>`,
        iconSize: [12, 12], iconAnchor: [6, 6],
    });
    const vehIcon = L.divIcon({
        className: '',
        html: `<div style="width:14px;height:14px;border-radius:50%;background:#00e5ff;border:2px solid #00454f;box-shadow:0 0 8px #00e5ff88;"></div>`,
        iconSize: [14, 14], iconAnchor: [7, 7],
    });

    const points = [];

    if (s.destination_lat && s.destination_lng) {
        L.marker([s.destination_lat, s.destination_lng], { icon: destIcon })
            .addTo(drawerMap)
            .bindPopup(`<b>Destination</b><br>${s.destination_address}`);
        points.push([s.destination_lat, s.destination_lng]);
    }

    if (s.vehicle?.latitude && s.vehicle?.longitude) {
        L.marker([s.vehicle.latitude, s.vehicle.longitude], { icon: vehIcon })
            .addTo(drawerMap)
            .bindPopup(`<b>${s.vehicle.plate}</b><br>${s.vehicle.speed_kmh?.toFixed(1)} km/h`);
        points.push([s.vehicle.latitude, s.vehicle.longitude]);
    }

    if (points.length > 0) {
        drawerMap.fitBounds(points.length === 1 ? [points[0], points[0]] : points, { padding: [30, 30], maxZoom: 14 });
    } else {
        drawerMap.setView([3.1390, 101.6869], 11);
    }

    setTimeout(() => drawerMap.invalidateSize(), 300);
}

function alertIcon(type) {
    const svgs = {
        overspeed: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
        delay:     `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`,
        offline:   `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--subtle)" stroke-width="2.5"><path d="M1 6s4-2 11-2 11 2 11 2"/><path d="M5 10s2.5-1 7-1 7 1 7 1"/><path d="M9 14s1.5-.5 3-.5 3 .5 3 .5"/><line x1="12" y1="18" x2="12" y2="18.5" stroke-width="3"/></svg>`,
        geofence:  `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>`,
    };
    return svgs[type] ?? svgs.overspeed;
}

function infoBlock(id, label, value) {
    document.getElementById(id).innerHTML = `
        <div class="label">${label}</div>
        <div>${value}</div>
    `;
}

// ── Status override ───────────────────────────────────────────────────────
async function applyStatusOverride() {
    const status = document.getElementById('statusOverride').value;
    if (!status || !currentShipmentId) return;

    const res  = await fetch(`/fleet/api/shipments/${currentShipmentId}/status`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ status }),
    });
    const json = await res.json();
    if (json.ok) {
        showToast(`Status updated to "${status}"`);
        loadDrawer(currentShipmentId);
        setTimeout(() => location.reload(), 1500);
    }
}

// ── Client-side page filter (current page only — server filters above) ────
function filterRows() {
    const q = document.getElementById('pageSearch').value.trim().toLowerCase();
    document.querySelectorAll('#shipTable tbody tr.sh-row').forEach(tr => {
        tr.style.display = !q || tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ── Origin preset loader ──────────────────────────────────────────────────
let originPresets  = [];
let useOriginManual = false;

async function loadOriginPresets() {
    try {
        const res  = await fetch('/fleet/api/origins', { headers: { 'Accept': 'application/json' } });
        originPresets = await res.json();
        const sel  = document.getElementById('c_origin_preset');
        sel.innerHTML = '<option value="">-- Select a preset warehouse --</option>';
        originPresets.forEach(o => {
            const opt = document.createElement('option');
            opt.value = o.id;
            opt.textContent = o.name + ' — ' + o.address;
            opt.dataset.address = o.address;
            sel.appendChild(opt);
        });
    } catch(e) {
        console.warn('Could not load warehouse presets', e);
    }
}

function applyOriginPreset() {
    const sel    = document.getElementById('c_origin_preset');
    const opt    = sel.options[sel.selectedIndex];
    const detail = document.getElementById('originPresetDetail');
    const hidden = document.getElementById('c_origin_address');

    if (sel.value && opt.dataset.address) {
        hidden.value  = opt.dataset.address;
        detail.textContent = opt.dataset.address;
        detail.style.color = 'var(--text)';
    } else {
        hidden.value  = '';
        detail.textContent = '';
    }
}

function toggleOriginMode() {
    useOriginManual = !useOriginManual;
    document.getElementById('originPresetWrap').style.display = useOriginManual ? 'none'  : 'block';
    document.getElementById('originManualWrap').style.display = useOriginManual ? 'block' : 'none';
    document.getElementById('originModeToggle').textContent   = useOriginManual
        ? 'Use preset instead'
        : 'Type manually instead';

    if (useOriginManual) {
        document.getElementById('c_origin_address').value = '';
        document.getElementById('c_origin_preset').value  = '';
        document.getElementById('originPresetDetail').textContent = '';
    } else {
        document.getElementById('c_origin').value = '';
    }
}

// ── Coordinate tab toggle ─────────────────────────────────────────────────
let coordMode    = 'map';
let pickerMap    = null;
let pickerMarker = null;

function switchCoordTab(mode) {
    coordMode = mode;
    const isMap = mode === 'map';

    document.getElementById('coordTabMap').style.display    = isMap ? 'block' : 'none';
    document.getElementById('coordTabManual').style.display = isMap ? 'none'  : 'block';
    document.getElementById('tabMap').classList.toggle('active', isMap);
    document.getElementById('tabManual').classList.toggle('active', !isMap);

    // Clear the other mode's values when switching
    if (isMap) {
        document.getElementById('c_dest_lat_manual').value = '';
        document.getElementById('c_dest_lng_manual').value = '';
        document.getElementById('c_dest_lat').value = pickerMarker ? pickerMarker.getLatLng().lat : '';
        document.getElementById('c_dest_lng').value = pickerMarker ? pickerMarker.getLatLng().lng : '';
        setTimeout(() => pickerMap && pickerMap.invalidateSize(), 100);
    } else {
        // Carry over map pin values to manual fields if a pin exists
        if (pickerMarker) {
            const ll = pickerMarker.getLatLng();
            document.getElementById('c_dest_lat_manual').value = ll.lat.toFixed(7);
            document.getElementById('c_dest_lng_manual').value = ll.lng.toFixed(7);
        }
    }
}

function initPickerMap() {
    if (pickerMap) { pickerMap.invalidateSize(); return; }

    pickerMap = L.map('createPickerMap', { zoomControl: true, attributionControl: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(pickerMap);
    pickerMap.setView([2.1896, 102.2501], 10);

    pickerMap.on('click', function(e) {
        const { lat, lng } = e.latlng;
        onMapPinned(lat, lng);
    });
}

function setMapPin(lat, lng) {
    const pinIcon = L.divIcon({
        className: '',
        html: `<div style="width:14px;height:14px;border-radius:50%;background:#00e5ff;border:2px solid #00454f;box-shadow:0 0 8px #00e5ff88;"></div>`,
        iconSize: [14, 14],
        iconAnchor: [7, 7],
    });
    if (pickerMarker) pickerMap.removeLayer(pickerMarker);
    pickerMarker = L.marker([lat, lng], { icon: pinIcon }).addTo(pickerMap);

    document.getElementById('c_dest_lat').value = lat.toFixed(7);
    document.getElementById('c_dest_lng').value = lng.toFixed(7);
    document.getElementById('coordPreview').innerHTML =
        `<span style="color:var(--accent);">pinned</span> &nbsp;<b>${lat.toFixed(6)}</b>, <b>${lng.toFixed(6)}</b>`;
}

function clearMapPin() {
    if (pickerMarker) { pickerMap.removeLayer(pickerMarker); pickerMarker = null; }
    document.getElementById('c_dest_lat').value = '';
    document.getElementById('c_dest_lng').value = '';
    document.getElementById('coordPreview').innerHTML = 'Click the map to pin destination';
}

function syncManualToHidden() {
    const lat = document.getElementById('c_dest_lat_manual').value;
    const lng = document.getElementById('c_dest_lng_manual').value;
    document.getElementById('c_dest_lat').value = lat;
    document.getElementById('c_dest_lng').value = lng;

    // Two-way sync: reverse-geocode valid manual coords into the address field.
    if (lat !== '' && lng !== '' && !isNaN(lat) && !isNaN(lng)) {
        clearTimeout(reverseDebounce);
        reverseDebounce = setTimeout(() => reverseGeocode(parseFloat(lat), parseFloat(lng)), 500);
    }
}

// ── Address geocoding (self-hosted Nominatim, two-way synced with the pin) ─
let addrDebounce    = null;
let reverseDebounce = null;
let lastAddrResults = [];

function onAddressInput() {
    const q = document.getElementById('c_destination_address').value.trim();
    clearTimeout(addrDebounce);
    if (q.length < 3) { hideAddrSuggest(); return; }
    addrDebounce = setTimeout(() => geocodeSearch(q), 250);
}

async function geocodeSearch(q) {
    try {
        const res  = await fetch('/fleet/api/geocode?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (!res.ok) { hideAddrSuggest(); return; }
        if (!data.available) {
            setAddrHint('Address search is unavailable — click the map or use Manual to set coordinates.', true);
            hideAddrSuggest();
            return;
        }
        lastAddrResults = data.results || [];
        renderAddrSuggest(lastAddrResults);
    } catch (e) {
        hideAddrSuggest();
    }
}

function renderAddrSuggest(results) {
    const box = document.getElementById('addrSuggest');
    if (!results.length) { hideAddrSuggest(); return; }
    box.innerHTML = results.map((r, i) =>
        `<div onclick="selectAddr(${i})" style="padding:9px 12px; cursor:pointer; font-size:12px; border-bottom:1px solid var(--border);"
              onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='transparent'">
            ${escapeHtml(r.label)}
        </div>`).join('');
    box.style.display = 'block';
}

function selectAddr(i) {
    const r = lastAddrResults[i];
    if (!r) return;
    document.getElementById('c_destination_address').value = r.label;
    hideAddrSuggest();
    setAddrHint('Pinned from address. Click the map to fine-tune.', false);
    // Show the pin on the map and drop it (address → pin). No reverse call here —
    // we already have the canonical label from the search result.
    switchCoordTab('map');
    setMapPin(r.lat, r.lng);
    if (pickerMap) {
        pickerMap.setView([r.lat, r.lng], 16);
        setTimeout(() => pickerMap.invalidateSize(), 60);
    }
}

// Pin → address: set the pin, then reverse-geocode to fill the address field.
function onMapPinned(lat, lng) {
    setMapPin(lat, lng);
    hideAddrSuggest();
    clearTimeout(reverseDebounce);
    reverseDebounce = setTimeout(() => reverseGeocode(lat, lng), 150);
}

async function reverseGeocode(lat, lng) {
    try {
        const res  = await fetch(`/fleet/api/geocode/reverse?lat=${lat}&lng=${lng}`, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (res.ok && data.available && data.address) {
            document.getElementById('c_destination_address').value = data.address;
            setAddrHint('Address filled from the map pin.', false);
        }
    } catch (e) { /* soft dependency — leave the address untouched */ }
}

function hideAddrSuggest() {
    const box = document.getElementById('addrSuggest');
    box.style.display = 'none';
    box.innerHTML = '';
}

function setAddrHint(text, warn) {
    const h = document.getElementById('addrHint');
    h.textContent = text;
    h.style.color = warn ? 'var(--warning)' : 'var(--subtle)';
}

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// Close the suggestions when clicking outside the address field/dropdown.
document.addEventListener('click', e => {
    if (!e.target.closest('#addrSuggest') && e.target.id !== 'c_destination_address') {
        hideAddrSuggest();
    }
});

// ── Prefill from a customer shipment request (?from_ticket=ID) ────────────
let createTicketId = null;

async function prefillFromTicket(id) {
    try {
        const res = await fetch(`/fleet/api/tickets/${id}`, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) { showToast('Could not load the shipment request.', 'var(--danger)'); return; }
        const t = await res.json();

        if (t.status !== 'pending') {
            showToast('That shipment request has already been reviewed.', 'var(--warning)');
            return;
        }

        openCreateModal();
        createTicketId = t.id; // after openCreateModal(), which resets it

        const banner = document.getElementById('createTicketBanner');
        banner.textContent = `Prefilled from shipment request ${t.request_code}. ` +
            `Pick the pickup warehouse and pin the destination — creating this shipment approves the request.`;
        banner.style.display = 'block';

        // The requester is the shipment's client. Origin is deliberately left
        // alone — the manager picks the warehouse preset as usual.
        document.getElementById('c_client_name').value  = t.client_name || '';
        document.getElementById('c_client_email').value = t.client_email || '';
        document.getElementById('c_client_phone').value = t.client_phone || '';

        document.getElementById('c_destination_address').value = t.destination_address || '';
        document.getElementById('c_delivery_notes').value      = t.delivery_notes || '';
        if (t.requested_delivery_at) {
            document.getElementById('c_expected_at').value = t.requested_delivery_at;
        }

        // Kick the geocoder so destination candidates appear immediately —
        // the manager still has to pick one (or pin) to set coordinates.
        onAddressInput();
    } catch (e) {
        showToast('Could not load the shipment request.', 'var(--danger)');
    }
}

{
    const fromTicket = new URLSearchParams(window.location.search).get('from_ticket');
    if (fromTicket) {
        document.addEventListener('DOMContentLoaded', () => prefillFromTicket(fromTicket));
    }
}

// ── Create shipment modal ─────────────────────────────────────────────────
function openCreateModal() {
    // Reset form
    coordMode = 'map';
    useOriginManual = false;
    createTicketId  = null;
    switchCoordTab('map');
    document.getElementById('createModal').style.display  = 'flex';
    document.getElementById('createError').style.display  = 'none';
    document.getElementById('createTicketBanner').style.display = 'none';
    document.getElementById('c_vehicle_id').value          = '';
    document.getElementById('c_client_name').value         = '';
    document.getElementById('c_client_email').value        = '';
    document.getElementById('c_client_phone').value        = '';
    document.getElementById('c_origin').value              = '';
    document.getElementById('c_origin_address').value      = '';
    document.getElementById('c_origin_preset').value       = '';
    document.getElementById('originPresetDetail').textContent = '';
    document.getElementById('originPresetWrap').style.display = 'block';
    document.getElementById('originManualWrap').style.display = 'none';
    document.getElementById('originModeToggle').textContent   = 'Type manually instead';
    document.getElementById('c_destination_address').value = '';
    document.getElementById('c_delivery_notes').value      = '';
    document.getElementById('c_expected_at').value         = '';
    hideAddrSuggest();
    setAddrHint('Type to search — picking a result drops the map pin. Clicking the map fills the address back.', false);
    clearMapPin();
    loadOriginPresets();
    // Init map after modal is visible
    setTimeout(initPickerMap, 150);
}
function closeCreateModal() {
    const m = document.getElementById('createModal');
    if (!m) return;   // drivers: modal is not rendered
    m.style.display = 'none';
    clearMapPin();
}

async function submitShipment() {
    const errDiv = document.getElementById('createError');
    const btn    = document.getElementById('createBtn');
    errDiv.style.display = 'none';

    // ── Client-side validation ───────────────────────────────────────────
    const lat    = document.getElementById('c_dest_lat').value;
    const lng    = document.getElementById('c_dest_lng').value;
    const origin = document.getElementById('c_origin_address').value
                || document.getElementById('c_origin').value;
    const rawDt  = document.getElementById('c_expected_at').value;

    if (!document.getElementById('c_vehicle_id').value) {
        return showErr(errDiv, 'Please select a vehicle.');
    }
    if (!document.getElementById('c_client_name').value.trim()) {
        return showErr(errDiv, 'Client name is required.');
    }
    if (!document.getElementById('c_client_email').value.trim()) {
        return showErr(errDiv, 'Client email is required.');
    }
    if (!origin.trim()) {
        return showErr(errDiv, 'Please select or enter an origin address.');
    }
    if (!document.getElementById('c_destination_address').value.trim()) {
        return showErr(errDiv, 'Destination address is required.');
    }
    if (!lat || !lng) {
        return showErr(errDiv, coordMode === 'map'
            ? 'Please click the map to pin a destination.'
            : 'Please enter both Latitude and Longitude.');
    }
    if (!rawDt) {
        return showErr(errDiv, 'Expected delivery date and time is required.');
    }

    // Fix datetime-local → Laravel format (2025-05-20T14:30 → 2025-05-20 14:30:00)
    const expectedAt = rawDt.replace('T', ' ') + ':00';

    const body = {
        vehicle_id:           document.getElementById('c_vehicle_id').value,
        client_name:          document.getElementById('c_client_name').value.trim(),
        client_email:         document.getElementById('c_client_email').value.trim(),
        client_phone:         document.getElementById('c_client_phone').value.trim() || null,
        origin_address:       origin.trim(),
        destination_address:  document.getElementById('c_destination_address').value.trim(),
        delivery_notes:       document.getElementById('c_delivery_notes').value.trim() || null,
        ticket_id:            createTicketId,
        destination_lat:      parseFloat(lat),
        destination_lng:      parseFloat(lng),
        expected_delivery_at: expectedAt,
    };

    // ── Submit ───────────────────────────────────────────────────────────
    btn.textContent = 'Creating...';
    btn.disabled    = true;

    try {
        const res  = await fetch('/fleet/api/shipments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept':       'application/json',
            },
            body: JSON.stringify(body),
        });

        let json = {};
        try { json = await res.json(); } catch(e) {}

        if (!res.ok) {
            const msg = json.errors
                ? Object.values(json.errors).flat().join(' ')
                : (json.message || `Server error (${res.status}). Check all fields.`);
            return showErr(errDiv, msg);
        }

        closeCreateModal();
        showToast('Shipment ' + json.tracking_code + ' created!');
        setTimeout(() => location.reload(), 1200);

    } catch (networkErr) {
        showErr(errDiv, 'Network error — could not reach server. Please try again.');
        console.error('submitShipment error:', networkErr);
    } finally {
        btn.textContent = 'Create Shipment';
        btn.disabled    = false;
    }
}

function showErr(div, msg) {
    div.textContent     = msg;
    div.style.display   = 'block';
    div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── Keyboard shortcuts + backdrop close ───────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeDrawer(); closeCreateModal(); }
});

// Clicking outside the create modal closes it (drawer backdrop already does)
const _createModal = document.getElementById('createModal');
if (_createModal) {
    _createModal.addEventListener('click', e => {
        if (e.target === _createModal) closeCreateModal();
    });
}
</script>
@endpush
