@extends('layouts.app')
@section('title', 'Activity Log')

@section('content')

{{-- Filter bar --}}
<form method="GET" action="{{ route('fleet.activity-log') }}"
      style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; align-items:center;">

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
                    <th style="width:150px;">Timestamp</th>
                    <th style="width:70px;">Source</th>
                    <th style="width:75px;">Subject</th>
                    <th style="width:120px;">Label</th>
                    <th style="width:175px;">Action</th>
                    <th>Description</th>
                    <th style="width:44px;text-align:center;">Diff</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td class="mono" style="color:var(--subtle); font-size:11px; white-space:nowrap;">
                        {{ $log->logged_at->format('Y-m-d H:i:s') }}
                    </td>

                    {{-- Source badge --}}
                    <td>
                        @php
                            $src = match($log->causer_type) {
                                'mqtt'   => ['label' => 'MQTT',   'color' => 'var(--accent)',   'bg' => '#002a30'],
                                'web'    => ['label' => 'WEB',    'color' => '#a78bfa',         'bg' => '#1a0a30'],
                                'system' => ['label' => 'SYS',    'color' => 'var(--subtle)',   'bg' => 'var(--muted)'],
                                default  => ['label' => strtoupper($log->causer_type), 'color' => 'var(--text)', 'bg' => 'var(--muted)'],
                            };
                        @endphp
                        <span style="
                            display:inline-block; padding:2px 7px; border-radius:4px;
                            font-size:9px; font-weight:600; letter-spacing:.08em;
                            background:{{ $src['bg'] }}; color:{{ $src['color'] }};
                        ">{{ $src['label'] }}</span>
                    </td>

                    {{-- Subject --}}
                    <td style="font-size:11px; color:var(--subtle);">{{ $log->subject_type }}</td>

                    {{-- Label --}}
                    <td class="mono" style="font-size:11px; color:var(--text);">
                        {{ $log->subject_label ?? '—' }}
                    </td>

                    {{-- Action badge --}}
                    <td>
                        @php
                            $act = match(true) {
                                str_contains($log->action, 'deleted')          => ['color'=>'var(--danger)',  'bg'=>'#2a1010'],
                                str_contains($log->action, 'created')          => ['color'=>'var(--success)', 'bg'=>'#0a2010'],
                                str_contains($log->action, 'updated')          => ['color'=>'var(--warning)', 'bg'=>'#2a1f00'],
                                str_contains($log->action, 'toggled')          => ['color'=>'var(--warning)', 'bg'=>'#2a1f00'],
                                str_contains($log->action, 'overspeed')        => ['color'=>'var(--danger)',  'bg'=>'#2a1010'],
                                str_contains($log->action, 'delayed')          => ['color'=>'var(--warning)', 'bg'=>'#2a1f00'],
                                str_contains($log->action, 'delivered')        => ['color'=>'var(--success)', 'bg'=>'#0a2010'],
                                str_contains($log->action, 'error')            => ['color'=>'var(--danger)',  'bg'=>'#2a1010'],
                                str_contains($log->action, 'mqtt_telemetry')   => ['color'=>'var(--accent)',  'bg'=>'#002a30'],
                                str_contains($log->action, 'read')             => ['color'=>'#94a3b8',        'bg'=>'var(--muted)'],
                                default                                         => ['color'=>'var(--subtle)',  'bg'=>'var(--muted)'],
                            };
                        @endphp
                        <span style="
                            display:inline-block; padding:2px 8px; border-radius:4px;
                            font-size:10px; font-family:var(--font-mono);
                            background:{{ $act['bg'] }}; color:{{ $act['color'] }};
                        ">{{ $log->action }}</span>
                    </td>

                    {{-- Description --}}
                    <td style="font-size:12px; color:var(--text);">{{ $log->description }}</td>

                    {{-- Diff button --}}
                    <td style="text-align:center;">
                        @if($log->old_values || $log->new_values)
                        <button
                            onclick="openDiff({{ $log->id }}, {{ json_encode($log->old_values) }}, {{ json_encode($log->new_values) }}, {{ json_encode($log->description) }})"
                            style="background:var(--muted);border:none;border-radius:4px;padding:3px 7px;cursor:pointer;color:var(--subtle);font-size:11px;"
                            title="View diff">
                            {}
                        </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:48px; color:var(--subtle);">
                        No activity logs found.
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
                <span class="btn btn-ghost" style="padding:4px 10px; opacity:.35; cursor:default;">← Prev</span>
            @else
                <a href="{{ $logs->previousPageUrl() }}" class="btn btn-ghost" style="padding:4px 10px;">← Prev</a>
            @endif
            @if($logs->hasMorePages())
                <a href="{{ $logs->nextPageUrl() }}" class="btn btn-ghost" style="padding:4px 10px;">Next →</a>
            @else
                <span class="btn btn-ghost" style="padding:4px 10px; opacity:.35; cursor:default;">Next →</span>
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
            <button onclick="closeDiff()" style="background:none;border:none;color:var(--subtle);cursor:pointer;font-size:20px;line-height:1;">×</button>
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
</style>
@endpush

@push('scripts')
<script>
function openDiff(id, oldVals, newVals, desc) {
    document.getElementById('diffDesc').textContent = desc;
    document.getElementById('diffOld').textContent  = oldVals ? JSON.stringify(oldVals, null, 2) : 'null';
    document.getElementById('diffNew').textContent  = newVals ? JSON.stringify(newVals, null, 2) : 'null';
    const modal = document.getElementById('diffModal');
    modal.style.display = 'flex';
}
function closeDiff() {
    document.getElementById('diffModal').style.display = 'none';
}
document.getElementById('diffModal').addEventListener('click', function(e) {
    if (e.target === this) closeDiff();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDiff(); });
</script>
@endpush
