@extends('layouts.app')
@section('title', 'Shipments')

@section('content')

{{-- ── Header ─────────────────────────────────────────────────────────── --}}
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <div style="font-family:var(--font-display); font-weight:700; font-size:18px;">Shipments</div>
        <div style="font-size:11px; color:var(--subtle); margin-top:3px;">
            {{ $shipments->total() }} total
            @if(auth()->user()->isDriver()) — showing your vehicle only @endif
        </div>
    </div>
    @if(!auth()->user()->isDriver())
    <button onclick="openCreateModal()" class="btn btn-primary">+ New Shipment</button>
    @endif
</div>

{{-- ── Status Pipeline Bar ─────────────────────────────────────────────── --}}
@php
    $pipeline = [
        'pending'   => ['label' => 'Pending',    'color' => 'var(--subtle)',  'bg' => 'var(--muted)'],
        'in_transit'=> ['label' => 'In Transit',  'color' => 'var(--accent)',  'bg' => 'rgba(0,229,255,0.1)'],
        'delayed'   => ['label' => 'Delayed',     'color' => 'var(--warning)', 'bg' => 'rgba(245,158,11,0.1)'],
        'delivered' => ['label' => 'Delivered',   'color' => 'var(--success)', 'bg' => 'rgba(34,197,94,0.1)'],
        'cancelled' => ['label' => 'Cancelled',   'color' => 'var(--danger)',  'bg' => 'rgba(239,68,68,0.1)'],
    ];
    $total = max(1, array_sum($statusCounts));
@endphp
<div style="display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-bottom:20px;">
    @foreach($pipeline as $key => $meta)
    @php $count = $statusCounts[$key] ?? 0; @endphp
    <a href="?status={{ $key }}" style="
        background:{{ request('status') === $key ? $meta['bg'] : 'var(--surface)' }};
        border:1px solid {{ request('status') === $key ? $meta['color'] : 'var(--border)' }};
        border-radius:10px; padding:14px 16px; text-decoration:none;
        transition:all .15s; display:block;
    ">
        <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:{{ $meta['color'] }}; margin-bottom:6px;">
            {{ $meta['label'] }}
        </div>
        <div style="font-family:var(--font-display); font-size:26px; font-weight:800; color:{{ $meta['color'] }};">
            {{ $count }}
        </div>
        {{-- Progress bar --}}
        <div style="margin-top:10px; height:3px; background:var(--muted); border-radius:2px;">
            <div style="height:3px; background:{{ $meta['color'] }}; border-radius:2px; width:{{ $total > 0 ? round(($count/$total)*100) : 0 }}%;"></div>
        </div>
    </a>
    @endforeach
</div>

{{-- ── Filter Bar ───────────────────────────────────────────────────────── --}}
<form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
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
    @if(request()->anyFilled(['status','vehicle_id','date_from','date_to']))
    <a href="{{ route('fleet.shipments') }}" class="btn btn-ghost" style="padding:6px 12px; font-size:11px;">✕ Clear</a>
    @endif
    <span style="margin-left:auto; font-size:11px; color:var(--subtle);">
        Showing {{ $shipments->firstItem() }}–{{ $shipments->lastItem() }} of {{ $shipments->total() }}
    </span>
</form>

{{-- ── Shipments Table ──────────────────────────────────────────────────── --}}
<div class="card">
    <div style="overflow-x:auto;">
        <table class="data-table">
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
                <tr style="cursor:pointer;" onclick="openDrawer({{ $shipment->id }})">
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
                            {{-- Copy tracking link --}}
                            <button
                                onclick="copyTrackingLink('{{ $shipment->tracking_code }}')"
                                title="Copy tracking link"
                                style="background:var(--muted);border:none;border-radius:4px;padding:4px 8px;cursor:pointer;color:var(--subtle);font-size:11px;">
                                🔗
                            </button>
                            {{-- Detail drawer --}}
                            <button
                                onclick="openDrawer({{ $shipment->id }})"
                                title="View detail"
                                style="background:var(--muted);border:none;border-radius:4px;padding:4px 8px;cursor:pointer;color:var(--subtle);font-size:11px;">
                                →
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
                            <button onclick="openCreateModal()" class="btn btn-primary" style="margin-top:8px;">+ Create First Shipment</button>
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
                <span class="btn btn-ghost" style="padding:4px 10px; opacity:.35; cursor:default;">← Prev</span>
            @else
                <a href="{{ $shipments->previousPageUrl() }}" class="btn btn-ghost" style="padding:4px 10px;">← Prev</a>
            @endif
            @if($shipments->hasMorePages())
                <a href="{{ $shipments->nextPageUrl() }}" class="btn btn-ghost" style="padding:4px 10px;">Next →</a>
            @else
                <span class="btn btn-ghost" style="padding:4px 10px; opacity:.35; cursor:default;">Next →</span>
            @endif
        </div>
    </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     DETAIL DRAWER
═══════════════════════════════════════════════════════════════════════ --}}
<div id="drawer" style="
    position:fixed; top:0; right:-520px; width:500px; height:100vh;
    background:var(--surface); border-left:1px solid var(--border);
    z-index:200; display:flex; flex-direction:column;
    transition:right .25s cubic-bezier(.4,0,.2,1);
    box-shadow:-8px 0 32px rgba(0,0,0,.4);
">
    {{-- Drawer Header --}}
    <div style="padding:18px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
        <div>
            <div id="drawerCode" style="font-family:var(--font-display); font-weight:700; font-size:15px; color:var(--accent);"></div>
            <div id="drawerCreated" style="font-size:10px; color:var(--subtle); margin-top:2px;"></div>
        </div>
        <button onclick="closeDrawer()" style="background:none;border:none;color:var(--subtle);cursor:pointer;font-size:22px;line-height:1;">×</button>
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

        {{-- Mini map --}}
        <div id="drawerMap" style="height:180px; border-radius:8px; overflow:hidden; margin-bottom:18px; border:1px solid var(--border); background:var(--bg);"></div>

        {{-- Client info --}}
        <div style="margin-bottom:18px;">
            <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--subtle); margin-bottom:10px;">Client</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div class="info-block" id="dClientName"></div>
                <div class="info-block" id="dClientEmail"></div>
                <div class="info-block" id="dClientPhone"></div>
            </div>
        </div>

        {{-- Route --}}
        <div style="margin-bottom:18px;">
            <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--subtle); margin-bottom:10px;">Route</div>
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

        {{-- Vehicle --}}
        <div style="margin-bottom:18px;">
            <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--subtle); margin-bottom:10px;">Vehicle</div>
            <div id="dVehicle" style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:12px;"></div>
        </div>

        {{-- Timing --}}
        <div style="margin-bottom:18px;">
            <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--subtle); margin-bottom:10px;">Timing</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div class="info-block" id="dExpected"></div>
                <div class="info-block" id="dDelivered"></div>
            </div>
        </div>

        {{-- Proof of Delivery --}}
        <div id="dProofSection" style="margin-bottom:18px; display:none;">
            <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--subtle); margin-bottom:10px;">Proof of Delivery</div>
            <a id="dProofLink" href="#" target="_blank" rel="noopener"
                style="display:block; border:1px solid var(--border); border-radius:8px; overflow:hidden; background:var(--bg);">
                <img id="dProofImg" src="" alt="Proof of delivery photo"
                    style="display:block; width:100%; max-height:220px; object-fit:cover;">
            </a>
        </div>

        {{-- Recent Alerts --}}
        <div id="dAlertsSection" style="margin-bottom:18px; display:none;">
            <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--subtle); margin-bottom:10px;">Recent Alerts</div>
            <div id="dAlerts"></div>
        </div>

        {{-- Tracking link --}}
        <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:12px; display:flex; align-items:center; justify-content:space-between; gap:10px;">
            <div>
                <div style="font-size:10px; color:var(--subtle); margin-bottom:4px;">Public Tracking Link</div>
                <div id="dTrackLink" class="mono" style="font-size:11px; color:var(--accent); word-break:break-all;"></div>
            </div>
            <button id="copyBtn" onclick="copyDrawerLink()" style="background:var(--muted);border:none;border-radius:6px;padding:7px 10px;cursor:pointer;color:var(--text);font-size:11px;white-space:nowrap;">🔗 Copy</button>
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
            <button onclick="closeCreateModal()" style="background:none;border:none;color:var(--subtle);cursor:pointer;font-size:22px;line-height:1;">×</button>
        </div>

        <div style="padding:22px;">
            <div id="createError" style="display:none; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); border-radius:8px; padding:10px 14px; font-size:12px; color:var(--danger); margin-bottom:16px;"></div>

            {{-- Section: Vehicle --}}
            <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--accent); margin-bottom:12px; padding-bottom:6px; border-bottom:1px solid var(--border);">Vehicle</div>
            <div style="margin-bottom:14px;">
                <select id="c_vehicle_id" style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
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
            <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--accent); margin-bottom:12px; padding-bottom:6px; border-bottom:1px solid var(--border); margin-top:18px;">Client Information</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                @foreach([
                    ['id'=>'c_client_name',  'label'=>'Client Name',  'type'=>'text',  'placeholder'=>'Company or person'],
                    ['id'=>'c_client_email', 'label'=>'Client Email', 'type'=>'email', 'placeholder'=>'client@example.com'],
                    ['id'=>'c_client_phone', 'label'=>'Phone',        'type'=>'tel',   'placeholder'=>'+60 1X-XXXXXXX'],
                ] as $f)
                <div>
                    <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">{{ $f['label'] }}</label>
                    <input id="{{ $f['id'] }}" type="{{ $f['type'] }}" placeholder="{{ $f['placeholder'] }}"
                        style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
                </div>
                @endforeach
            </div>

            {{-- Section: Route --}}
            <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--accent); margin-bottom:12px; padding-bottom:6px; border-bottom:1px solid var(--border); margin-top:18px;">Route</div>

            {{-- Origin: preset selector or manual --}}
            <div style="margin-bottom:14px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label style="font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle);">Origin / Pickup</label>
                    <button type="button" onclick="toggleOriginMode()" id="originModeToggle"
                        style="font-size:10px; font-family:var(--font-mono); background:none; border:none; color:var(--accent); cursor:pointer; text-decoration:underline;">
                        Type manually instead
                    </button>
                </div>

                {{-- Preset selector --}}
                <div id="originPresetWrap">
                    <select id="c_origin_preset" onchange="applyOriginPreset()"
                        style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
                        <option value="">-- Select a preset origin --</option>
                    </select>
                    <div style="font-size:10px; color:var(--subtle); margin-top:5px;" id="originPresetDetail"></div>
                </div>

                {{-- Manual input (hidden by default) --}}
                <div id="originManualWrap" style="display:none;">
                    <input id="c_origin" type="text" placeholder="Warehouse / pickup location"
                        style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
                </div>

                <input type="hidden" id="c_origin_address">
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Destination Address</label>
                <input id="c_destination_address" type="text" placeholder="Delivery address"
                    style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
            </div>

            {{-- Destination Coordinates — toggle between map pin and manual --}}
            <div style="margin-bottom:6px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                    <label style="font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle);">Destination Coordinates</label>
                    <div style="display:flex; gap:0; border:1px solid var(--border); border-radius:6px; overflow:hidden;">
                        <button type="button" id="tabMap" onclick="switchCoordTab('map')"
                            style="padding:4px 12px; font-size:10px; font-family:var(--font-mono); border:none; cursor:pointer; transition:all .15s; background:var(--accent); color:#000;">
                            Pin on Map
                        </button>
                        <button type="button" id="tabManual" onclick="switchCoordTab('manual')"
                            style="padding:4px 12px; font-size:10px; font-family:var(--font-mono); border:none; cursor:pointer; transition:all .15s; background:var(--muted); color:var(--subtle);">
                            Manual
                        </button>
                    </div>
                </div>

                {{-- Map picker --}}
                <div id="coordTabMap">
                    <div id="createPickerMap" style="height:200px; border-radius:8px; overflow:hidden; border:1px solid var(--border); background:var(--bg); margin-bottom:8px;"></div>
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
                            <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Latitude</label>
                            <input id="c_dest_lat_manual" type="number" step="any" placeholder="e.g. 3.1390"
                                oninput="syncManualToHidden()"
                                style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Longitude</label>
                            <input id="c_dest_lng_manual" type="number" step="any" placeholder="e.g. 101.6869"
                                oninput="syncManualToHidden()"
                                style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
                        </div>
                    </div>
                    <div style="font-size:10px; color:var(--subtle); margin-top:6px;">
                        💡 Tip: You can get coordinates from Google Maps — right-click a location and copy the numbers.
                    </div>
                </div>

                {{-- Hidden fields always submitted --}}
                <input type="hidden" id="c_dest_lat">
                <input type="hidden" id="c_dest_lng">
            </div>

            <div style="margin-bottom:22px; margin-top:14px;">
                <label style="display:block; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--subtle); margin-bottom:6px;">Expected Delivery</label>
                <input id="c_expected_at" type="datetime-local"
                    style="width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 13px; font-family:var(--font-mono); font-size:13px; color:var(--text); outline:none;">
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
/* Match dashboard map style — OSM tiles + dark CSS filter */
#createPickerMap .leaflet-tile-pane,
#drawerMap .leaflet-tile-pane {
    filter: brightness(0.65) saturate(0.7) hue-rotate(185deg);
}
#createPickerMap .leaflet-container,
#drawerMap .leaflet-container {
    background: #0a0b0e;
}

.filter-select, .filter-input {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    font-family: var(--font-mono);
    font-size: 11px;
    padding: 6px 10px;
    outline: none;
}
.filter-select:focus, .filter-input:focus { border-color: var(--accent); }
.filter-select option { background: var(--surface); }

.info-block {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 10px 12px;
    font-size: 12px;
}
.info-block .label {
    font-size: 9px;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--subtle);
    margin-bottom: 4px;
}
</style>
@endpush

@push('scripts')
<script>
const CSRF      = document.querySelector('meta[name="csrf-token"]').content;
const BASE_URL  = window.location.origin;
let currentShipmentId = null;
let drawerMap = null;
let drawerMarkerVehicle = null;
let drawerMarkerDest    = null;

// ── Toast ─────────────────────────────────────────────────────────────────
function showToast(msg, color = 'var(--success)') {
    const t = document.getElementById('toast');
    t.style.display = 'block';
    t.style.borderColor = color;
    t.innerHTML = `<span style="color:${color};">●</span>&nbsp; ${msg}`;
    setTimeout(() => { t.style.display = 'none'; }, 3000);
}

// ── Copy helpers ──────────────────────────────────────────────────────────
function copyTrackingLink(code) {
    navigator.clipboard.writeText(`${BASE_URL}/track?code=${code}`);
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
    document.getElementById('drawer').style.right = '0';
    document.getElementById('drawerBackdrop').style.display = 'block';
    loadDrawer(id);
}
function closeDrawer() {
    document.getElementById('drawer').style.right = '-520px';
    document.getElementById('drawerBackdrop').style.display = 'none';
    currentShipmentId = null;
}

async function loadDrawer(id) {
    const res  = await fetch(`/fleet/api/shipments/${id}`);
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

    // Timing
    infoBlock('dExpected',  'Expected By', s.expected_delivery_at ?? '—');
    infoBlock('dDelivered', 'Delivered At', s.actual_delivery_at ?? '—');

    // Proof of delivery photo (shown only if the driver captured one)
    const proofSection = document.getElementById('dProofSection');
    if (s.delivery_photo) {
        document.getElementById('dProofImg').src   = s.delivery_photo;
        document.getElementById('dProofLink').href = s.delivery_photo;
        proofSection.style.display = '';
    } else {
        proofSection.style.display = 'none';
    }

    // Vehicle
    const vDiv = document.getElementById('dVehicle');
    if (s.vehicle) {
        const offlineColor = s.vehicle.is_offline ? 'var(--danger)' : 'var(--success)';
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
                &nbsp;·&nbsp; Updated: <span style="color:var(--text);">${s.vehicle.recorded_at ?? '—'}</span>
            </div>` : ''}
        `;
    } else {
        vDiv.innerHTML = `<span style="color:var(--subtle); font-size:12px;">No vehicle assigned</span>`;
    }

    // Tracking link
    document.getElementById('dTrackLink').textContent = `${BASE_URL}/track?code=${s.tracking_code}`;

    // Alerts
    const alertsSection = document.getElementById('dAlertsSection');
    const alertsDiv     = document.getElementById('dAlerts');
    if (s.alerts && s.alerts.length > 0) {
        alertsSection.style.display = 'block';
        alertsDiv.innerHTML = s.alerts.map(a => `
            <div style="display:flex; gap:10px; padding:9px 0; border-bottom:1px solid var(--border);">
                <div style="font-size:13px;">${alertIcon(a.type)}</div>
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
    return { overspeed: '🚨', delay: '⏰', offline: '📡', geofence: '📍' }[type] ?? '⚠️';
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
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ status }),
    });
    const json = await res.json();
    if (json.ok) {
        showToast(`Status updated to "${status}"`);
        loadDrawer(currentShipmentId);
        setTimeout(() => location.reload(), 1500);
    }
}

// ── Origin preset loader ──────────────────────────────────────────────────
let originPresets  = [];
let useOriginManual = false;

async function loadOriginPresets() {
    try {
        const res  = await fetch('/fleet/api/origins');
        originPresets = await res.json();
        const sel  = document.getElementById('c_origin_preset');
        sel.innerHTML = '<option value="">-- Select a preset origin --</option>';
        originPresets.forEach(o => {
            const opt = document.createElement('option');
            opt.value = o.id;
            opt.textContent = o.name + ' — ' + o.address;
            opt.dataset.address = o.address;
            sel.appendChild(opt);
        });
    } catch(e) {
        console.warn('Could not load origin presets', e);
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

    document.getElementById('tabMap').style.background    = isMap ? 'var(--accent)' : 'var(--muted)';
    document.getElementById('tabMap').style.color         = isMap ? '#000'          : 'var(--subtle)';
    document.getElementById('tabManual').style.background = isMap ? 'var(--muted)' : 'var(--accent)';
    document.getElementById('tabManual').style.color      = isMap ? 'var(--subtle)' : '#000';

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
    pickerMap.setView([3.1390, 101.6869], 11);

    pickerMap.on('click', function(e) {
        const { lat, lng } = e.latlng;
        setMapPin(lat, lng);
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
        `<span style="color:var(--accent);">📍</span> &nbsp;<b>${lat.toFixed(6)}</b>, <b>${lng.toFixed(6)}</b>`;
}

function clearMapPin() {
    if (pickerMarker) { pickerMap.removeLayer(pickerMarker); pickerMarker = null; }
    document.getElementById('c_dest_lat').value = '';
    document.getElementById('c_dest_lng').value = '';
    document.getElementById('coordPreview').innerHTML = 'Click the map to pin destination';
}

function syncManualToHidden() {
    document.getElementById('c_dest_lat').value = document.getElementById('c_dest_lat_manual').value;
    document.getElementById('c_dest_lng').value = document.getElementById('c_dest_lng_manual').value;
}

// ── Create shipment modal ─────────────────────────────────────────────────
function openCreateModal() {
    // Reset form
    coordMode = 'map';
    useOriginManual = false;
    switchCoordTab('map');
    document.getElementById('createModal').style.display  = 'flex';
    document.getElementById('createError').style.display  = 'none';
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
    document.getElementById('c_expected_at').value         = '';
    clearMapPin();
    loadOriginPresets();
    // Init map after modal is visible
    setTimeout(initPickerMap, 150);
}
function closeCreateModal() {
    document.getElementById('createModal').style.display = 'none';
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

// ── Keyboard shortcuts ────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeDrawer(); closeCreateModal(); }
});
</script>
@endpush
