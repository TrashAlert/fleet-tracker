@extends('layouts.app')
@section('title', 'Activity Log')

@section('content')

@php
    // Action → category: a semantic colour + an inline-SVG icon (inner paths).
    // Colours are CSS vars (or a fixed violet used only as a tint), so badges
    // render correctly in BOTH themes — no more dark-only hex backgrounds.
    // First match wins, so order specific actions before generic ones.
    $categoryOf = function (string $action): array {
        return match (true) {
            str_contains($action, 'deleted')          => ['c' => 'var(--danger)',  'p' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>'],
            str_contains($action, 'failed')           => ['c' => 'var(--danger)',  'p' => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>'],
            str_contains($action, 'error') || str_contains($action, 'invalid') || str_contains($action, 'unknown') => ['c' => 'var(--danger)', 'p' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
            str_contains($action, 'overspeed')         => ['c' => 'var(--danger)',  'p' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>'],
            str_contains($action, 'created')           => ['c' => 'var(--success)', 'p' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>'],
            str_contains($action, 'delivered')         => ['c' => 'var(--accent)',  'p' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
            str_contains($action, 'started') || str_contains($action, 'near_destination') => ['c' => 'var(--accent)', 'p' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>'],
            str_contains($action, 'telemetry')         => ['c' => 'var(--accent)',  'p' => '<path d="M4.93 19.07a10 10 0 0 1 0-14.14"/><path d="M7.76 16.24a6 6 0 0 1 0-8.48"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M16.24 7.76a6 6 0 0 1 0 8.48"/><circle cx="12" cy="12" r="2"/>'],
            str_contains($action, 'delayed')           => ['c' => 'var(--warning)', 'p' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
            str_contains($action, 'returned') || str_contains($action, 'flag') || str_contains($action, 'left_radius') => ['c' => 'var(--warning)', 'p' => '<polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/>'],
            str_contains($action, 'updated') || str_contains($action, 'toggled') || str_contains($action, 'status') || str_contains($action, 'settings') => ['c' => 'var(--warning)', 'p' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'],
            str_contains($action, 'login') || str_contains($action, 'logout') => ['c' => '#8b5cf6', 'p' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>'],
            str_contains($action, 'read') || str_contains($action, 'marked') => ['c' => 'var(--subtle)', 'p' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'],
            default                                    => ['c' => 'var(--subtle)', 'p' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
        };
    };

    // Source badge palette (theme-safe: solid colour as text, tint as bg).
    $sourceOf = function (?string $type): array {
        return match ($type) {
            'mqtt'   => ['label' => 'MQTT', 'c' => 'var(--accent)'],
            'web'    => ['label' => 'WEB',  'c' => '#8b5cf6'],
            'system' => ['label' => 'SYS',  'c' => 'var(--subtle)'],
            default  => ['label' => strtoupper($type ?: '—'), 'c' => 'var(--text)'],
        };
    };

    // Currently-applied filters, as removable chips. vehicle_id shows its plate
    // (not the raw id); everything else shows its literal value.
    $vehiclePlate = request()->filled('vehicle_id')
        ? (optional($vehicles->firstWhere('id', (int) request('vehicle_id')))->plate_number ?? request('vehicle_id'))
        : null;
    $chips = [];
    if (request()->filled('search'))     $chips[] = ['label' => 'Search',  'value' => request('search'), 'key' => 'search'];
    if (request()->filled('vehicle_id')) $chips[] = ['label' => 'Vehicle', 'value' => $vehiclePlate,      'key' => 'vehicle_id'];
    if (request()->filled('subject'))    $chips[] = ['label' => 'Subject', 'value' => request('subject'), 'key' => 'subject'];
    if (request()->filled('action'))     $chips[] = ['label' => 'Action',  'value' => request('action'),  'key' => 'action'];
    if (request()->filled('causer'))     $chips[] = ['label' => 'Source',  'value' => request('causer'),  'key' => 'causer'];
    if (request()->filled('from'))       $chips[] = ['label' => 'From',    'value' => request('from'),    'key' => 'from'];
    if (request()->filled('to'))         $chips[] = ['label' => 'To',      'value' => request('to'),      'key' => 'to'];
@endphp

{{-- Filter bar --}}
<form method="GET" action="{{ route('fleet.activity-log') }}"
      style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; align-items:center;">

    {{-- Keyword search — plate, name, or alert wording --}}
    <div class="al-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" name="search" class="al-search-input" value="{{ request('search') }}"
               placeholder="Search plate, name, description…">
    </div>

    <select name="vehicle_id" class="filter-select" title="Filter by vehicle">
        <option value="">All Vehicles</option>
        @foreach($vehicles as $v)
            <option value="{{ $v->id }}" @selected((int) request('vehicle_id') === $v->id)>{{ $v->plate_number }} — {{ $v->name }}</option>
        @endforeach
    </select>

    <select name="subject" class="filter-select">
        <option value="">All Subjects</option>
        @foreach($subjectTypes as $type)
            <option value="{{ $type }}" @selected(request('subject') === $type)>{{ $type }}</option>
        @endforeach
    </select>

    <select name="action" class="filter-select">
        <option value="">All Actions</option>
        @foreach($actionTypes as $action)
            <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
        @endforeach
    </select>

    <select name="causer" class="filter-select">
        <option value="">All Sources</option>
        @foreach($causerTypes as $causer)
            <option value="{{ $causer }}" @selected(request('causer') === $causer)>{{ $causer }}</option>
        @endforeach
    </select>

    <input type="date" name="from" class="filter-input" value="{{ request('from') }}" title="From date">
    <input type="date" name="to"   class="filter-input" value="{{ request('to') }}"   title="To date">

    <button type="submit" class="btn btn-primary" style="padding:6px 14px;">Filter</button>
    <a href="{{ route('fleet.activity-log') }}" class="btn btn-ghost" style="padding:6px 12px;">Reset</a>

    <span style="margin-left:auto; font-size:11px; color:var(--subtle);">
        {{ number_format($logs->total()) }} entries
    </span>
</form>

{{-- Active-filter chips (each removes just its own param) --}}
@if(count($chips))
<div class="al-chips">
    <span class="al-chips-label">Filtering</span>
    @foreach($chips as $chip)
    <a class="al-chip" href="?{{ http_build_query(request()->except([$chip['key'], 'page'])) }}" title="Remove this filter">
        <span>{{ $chip['label'] }}: {{ $chip['value'] }}</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </a>
    @endforeach
    <a class="al-chip al-chip-clear" href="{{ route('fleet.activity-log') }}">Clear all</a>
</div>
@endif

{{-- Log table --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Activity Log</span>
        <span style="font-size:10px; color:var(--subtle);">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>
    </div>

    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:110px;">Time</th>
                    <th style="width:70px;">Source</th>
                    <th style="width:75px;">Subject</th>
                    <th style="width:120px;">Label</th>
                    <th style="width:210px;">Action</th>
                    <th>Description</th>
                    <th style="width:44px;text-align:center;">Diff</th>
                </tr>
            </thead>
            <tbody>
                @php $lastDate = null; @endphp
                @forelse($logs as $log)
                    @php
                        $day = $log->logged_at->toDateString();
                        $cat = $categoryOf($log->action);
                        $src = $sourceOf($log->causer_type);
                    @endphp

                    @if($day !== $lastDate)
                    <tr class="al-day"><td colspan="7">{{ $log->logged_at->format('l, d M Y') }}</td></tr>
                    @php $lastDate = $day; @endphp
                    @endif

                    <tr class="al-row" style="--al-c: {{ $cat['c'] }};">
                        {{-- Time (full datetime in tooltip) --}}
                        <td class="mono" style="color:var(--subtle); font-size:11px; white-space:nowrap;"
                            title="{{ $log->logged_at->format('Y-m-d H:i:s') }}">
                            {{ $log->logged_at->format('H:i:s') }}
                        </td>

                        {{-- Source badge --}}
                        <td>
                            <span class="al-badge" style="background:color-mix(in srgb, {{ $src['c'] }} 14%, transparent); color:{{ $src['c'] }};">{{ $src['label'] }}</span>
                        </td>

                        {{-- Subject --}}
                        <td style="font-size:11px; color:var(--subtle);">{{ $log->subject_type }}</td>

                        {{-- Label --}}
                        <td class="mono" style="font-size:11px; color:var(--text);">{{ $log->subject_label ?? '—' }}</td>

                        {{-- Action badge (icon + name) --}}
                        <td>
                            <span class="al-action" style="background:color-mix(in srgb, {{ $cat['c'] }} 13%, transparent); color:{{ $cat['c'] }};">
                                <span class="al-action-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $cat['p'] !!}</svg></span>
                                {{ $log->action }}
                            </span>
                        </td>

                        {{-- Description --}}
                        <td style="font-size:12px; color:var(--text);">{{ $log->description }}</td>

                        {{-- Diff button --}}
                        <td style="text-align:center;">
                            @if($log->old_values || $log->new_values)
                            <button class="al-diff-btn"
                                onclick="openDiff({{ $log->id }}, {{ json_encode($log->old_values) }}, {{ json_encode($log->new_values) }}, {{ json_encode($log->description) }})"
                                title="View change detail" aria-label="View change detail">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H7a2 2 0 0 0-2 2v5a2 2 0 0 1-2 2 2 2 0 0 1 2 2v5a2 2 0 0 0 2 2h1"/><path d="M16 3h1a2 2 0 0 1 2 2v5a2 2 0 0 1 2 2 2 2 0 0 1-2 2v5a2 2 0 0 1-2 2h-1"/></svg>
                            </button>
                            @endif
                        </td>
                    </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="al-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                            <div>No activity logs match these filters.</div>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($logs->hasPages())
    <div style="padding:14px 18px; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
        <span style="font-size:11px; color:var(--subtle);">
            Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ $logs->total() }}
        </span>
        <div style="display:flex; gap:6px;">
            @if($logs->onFirstPage())
                <span class="btn btn-ghost al-page" style="opacity:.35; cursor:default;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg> Prev
                </span>
            @else
                <a href="{{ $logs->previousPageUrl() }}" class="btn btn-ghost al-page">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg> Prev
                </a>
            @endif
            @if($logs->hasMorePages())
                <a href="{{ $logs->nextPageUrl() }}" class="btn btn-ghost al-page">
                    Next <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            @else
                <span class="btn btn-ghost al-page" style="opacity:.35; cursor:default;">
                    Next <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
            @endif
        </div>
    </div>
    @endif
</div>

{{-- Diff Modal --}}
<div id="diffModal" style="
    display:none; position:fixed; inset:0; z-index:1000;
    background:rgba(0,0,0,.7); backdrop-filter:blur(4px);
    align-items:center; justify-content:center;
">
    <div style="
        background:var(--surface); border:1px solid var(--border); border-radius:12px;
        width:min(860px,95vw); max-height:85vh; display:flex; flex-direction:column;
    ">
        <div style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
            <span style="font-family:var(--font-display); font-weight:700; font-size:14px;" id="diffTitle">Change Detail</span>
            <button onclick="closeDiff()" class="al-modal-close" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div style="padding:16px 20px; flex:1; overflow:auto;">
            <p style="font-size:11px; color:var(--subtle); margin-bottom:16px;" id="diffDesc"></p>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--danger); margin-bottom:8px;">Before</div>
                    <pre id="diffOld" style="
                        background:var(--bg); border:1px solid var(--border); border-radius:6px;
                        padding:14px; font-size:11px; font-family:var(--font-mono); color:var(--text);
                        overflow:auto; max-height:340px; white-space:pre-wrap; word-break:break-all;
                    "></pre>
                </div>
                <div>
                    <div style="font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--success); margin-bottom:8px;">After / Meta</div>
                    <pre id="diffNew" style="
                        background:var(--bg); border:1px solid var(--border); border-radius:6px;
                        padding:14px; font-size:11px; font-family:var(--font-mono); color:var(--text);
                        overflow:auto; max-height:340px; white-space:pre-wrap; word-break:break-all;
                    "></pre>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.filter-select, .filter-input {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    font-family: var(--font-mono);
    font-size: 11px;
    padding: 6px 10px;
    outline: none;
    transition: border-color .15s;
}
.filter-select:focus, .filter-input:focus { border-color: var(--accent); }
.filter-select option { background: var(--surface); }

/* ── Keyword search ── */
.al-search {
    display:flex; align-items:center; gap:7px;
    background:var(--surface); border:1px solid var(--border); border-radius:6px;
    padding:0 10px; transition:border-color .15s; min-width:230px; flex:1 1 230px; max-width:340px;
}
.al-search:focus-within { border-color:var(--accent); }
.al-search svg { width:13px; height:13px; stroke:var(--subtle); flex-shrink:0; }
.al-search-input {
    flex:1; background:none; border:none; outline:none;
    color:var(--text); font-family:var(--font-mono); font-size:11px; padding:7px 0;
}
.al-search-input::placeholder { color:var(--subtle); }

/* ── Active-filter chips ── */
.al-chips { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:16px; }
.al-chips-label { font-size:10px; text-transform:uppercase; letter-spacing:.1em; color:var(--subtle); }
.al-chip {
    display:inline-flex; align-items:center; gap:7px; font-size:11px; font-family:var(--font-mono);
    background:color-mix(in srgb, var(--accent) 10%, transparent);
    border:1px solid color-mix(in srgb, var(--accent) 35%, var(--border));
    color:var(--text); border-radius:6px; padding:4px 9px; text-decoration:none;
}
.al-chip svg { width:11px; height:11px; stroke:var(--subtle); }
.al-chip:hover svg { stroke:var(--danger); }
.al-chip-clear { background:transparent; border-color:var(--border); color:var(--subtle); }
.al-chip-clear:hover { color:var(--text); }

/* ── Rows ── */
.al-row td:first-child { border-left:3px solid var(--al-c, transparent); }
.data-table thead th:first-child { border-left:3px solid transparent; }
.data-table tr.al-row:hover td { background: color-mix(in srgb, var(--accent) 5%, transparent); }

.al-badge {
    display:inline-block; border-radius:4px; padding:2px 7px;
    font-size:9px; font-weight:600; letter-spacing:.08em;
}
.al-action {
    display:inline-flex; align-items:center; gap:6px; max-width:100%;
    border-radius:5px; padding:3px 8px; font-size:10px; font-family:var(--font-mono);
}
.al-action-ico { display:inline-flex; flex-shrink:0; }
.al-action-ico svg { width:12px; height:12px; }

/* ── Day separator ── */
.al-day td {
    background:var(--bg); color:var(--subtle);
    font-size:10px; letter-spacing:.1em; text-transform:uppercase;
    padding:7px 14px; font-family:var(--font-mono);
}

/* ── Buttons / chrome ── */
.al-diff-btn {
    background:var(--muted); border:none; border-radius:5px; padding:4px 7px;
    cursor:pointer; color:var(--subtle); display:inline-flex; align-items:center; justify-content:center;
}
.al-diff-btn:hover { color:var(--accent); }
.al-diff-btn svg { width:13px; height:13px; }
.al-modal-close { background:none; border:none; color:var(--subtle); cursor:pointer; display:inline-flex; padding:2px; }
.al-modal-close:hover { color:var(--text); }
.al-modal-close svg { width:16px; height:16px; }
.al-page { padding:4px 10px; display:inline-flex; align-items:center; gap:5px; }
.al-page svg { width:11px; height:11px; }

/* ── Empty state ── */
.al-empty { display:flex; flex-direction:column; align-items:center; text-align:center; padding:48px 16px; color:var(--subtle); font-size:12px; }
.al-empty svg { width:34px; height:34px; stroke:var(--muted); margin-bottom:12px; }
</style>
@endpush

@push('scripts')
<script>
function openDiff(id, oldVals, newVals, desc) {
    document.getElementById('diffDesc').textContent = desc;
    document.getElementById('diffOld').textContent  = oldVals ? JSON.stringify(oldVals, null, 2) : 'null';
    document.getElementById('diffNew').textContent  = newVals ? JSON.stringify(newVals, null, 2) : 'null';
    document.getElementById('diffModal').style.display = 'flex';
}
function closeDiff() {
    document.getElementById('diffModal').style.display = 'none';
}
document.getElementById('diffModal').addEventListener('click', function (e) {
    if (e.target === this) closeDiff();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDiff(); });
</script>
@endpush
