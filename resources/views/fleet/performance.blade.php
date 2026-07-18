@extends('layouts.app')
@section('title', 'Performance')

@section('content')

@php
    // seconds -> compact human duration
    $fmtDur = function ($seconds) {
        $seconds = (int) $seconds;
        if ($seconds <= 0) return '0m';
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        if ($h > 0) return $h . 'h ' . $m . 'm';
        if ($m > 0) return $m . 'm';
        return $seconds . 's';
    };
    $windowLabel = $days == 1 ? 'last 24 hours' : "last {$days} days";
@endphp

{{-- ── Window selector ─────────────────────────────────────────────────── --}}
<div class="perf-window">
    <span class="perf-window-label">Window</span>
    @foreach([1 => '24h', 7 => '7 days', 30 => '30 days'] as $d => $label)
        <a href="{{ route('fleet.performance', ['days' => $d]) }}"
           class="perf-window-btn {{ $days == $d ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</div>

{{-- ── Device (ESP32) uptime ───────────────────────────────────────────── --}}
<div class="perf-heading">
    Device Uptime
    <span>ESP32 connectivity over the {{ $windowLabel }} · a gap over {{ $fmtDur($offlineGap) }} counts as downtime</span>
</div>

<div class="stats-grid">
    <div class="stat-tile">
        <div class="stat-label">Total Devices</div>
        <div class="stat-value accent">{{ $fleet['total'] }}</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Online Now</div>
        <div class="stat-value success">{{ $fleet['online_now'] }}</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Offline Now</div>
        <div class="stat-value danger">{{ $fleet['offline_now'] }}</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Avg Uptime</div>
        <div class="stat-value">{{ $fleet['avg_uptime'] }}%</div>
    </div>
</div>

<div class="perf-card">
    <table class="perf-table">
        <thead>
            <tr>
                <th>Vehicle</th>
                <th>Status</th>
                <th style="width:30%;">Uptime</th>
                <th>Downtime</th>
                <th>Offline episodes</th>
                <th>Last seen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($deviceStats as $d)
                @php
                    $pct      = $d->uptime_pct;
                    $barColor = $pct >= 95 ? 'var(--success)' : ($pct >= 80 ? '#f59e0b' : 'var(--danger)');
                @endphp
                <tr>
                    <td>
                        <div style="font-weight:600;">{{ $d->vehicle->name }}</div>
                        <div class="mono" style="font-size:10px; color:var(--subtle);">{{ $d->vehicle->plate_number }}</div>
                    </td>
                    <td>
                        <span class="pill {{ $d->online_now ? 'pill-online' : 'pill-offline' }}">
                            {{ $d->online_now ? 'online' : 'offline' }}
                        </span>
                    </td>
                    <td>
                        <div class="uptime-bar">
                            <div class="uptime-bar-fill" style="width:{{ $pct }}%; background:{{ $barColor }};"></div>
                        </div>
                        <div class="mono" style="font-size:11px; margin-top:3px;">
                            {{ $d->has_data ? $pct . '%' : 'no data in window' }}
                        </div>
                    </td>
                    <td class="mono">{{ $fmtDur($d->downtime) }}</td>
                    <td class="mono">{{ $d->episodes }}</td>
                    <td style="color:var(--subtle); font-size:12px;">
                        {{ $d->last_seen ? $d->last_seen->diffForHumans() : 'Never' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center; color:var(--subtle); padding:24px 0;">No vehicles registered</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- ── Delivery performance ────────────────────────────────────────────── --}}
<div class="perf-heading">
    Delivery Performance
    <span>completed in the {{ $windowLabel }}</span>
</div>

<div class="stats-grid">
    <div class="stat-tile">
        <div class="stat-label">Delivered</div>
        <div class="stat-value accent">{{ $deliveredCount }}</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">On-time Rate</div>
        <div class="stat-value {{ $onTimePct === null ? '' : ($onTimePct >= 90 ? 'success' : ($onTimePct >= 70 ? '' : 'danger')) }}">
            {{ $onTimePct === null ? '—' : $onTimePct . '%' }}
        </div>
        <div style="font-size:10px; color:var(--subtle); margin-top:4px;">
            {{ $onTime }} on time · {{ $late }} late
        </div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Avg vs ETA</div>
        @if($avgLatenessMin === null)
            <div class="stat-value">—</div>
        @elseif($avgLatenessMin > 0)
            <div class="stat-value danger">+{{ $avgLatenessMin }}m</div>
            <div style="font-size:10px; color:var(--subtle); margin-top:4px;">late on average</div>
        @else
            <div class="stat-value success">{{ abs($avgLatenessMin) }}m</div>
            <div style="font-size:10px; color:var(--subtle); margin-top:4px;">early on average</div>
        @endif
    </div>
    <div class="stat-tile">
        <div class="stat-label">Avg Fulfilment</div>
        <div class="stat-value">
            {{ $avgFulfilMin === null ? '—' : ($avgFulfilMin >= 60 ? round($avgFulfilMin / 60, 1) . 'h' : $avgFulfilMin . 'm') }}
        </div>
        <div style="font-size:10px; color:var(--subtle); margin-top:4px;">create → delivered</div>
    </div>
</div>

<div class="perf-heading sub">
    Right Now
    <span>current shipment state across the fleet</span>
</div>
<div class="stats-grid">
    <div class="stat-tile">
        <div class="stat-label">Active Shipments</div>
        <div class="stat-value">{{ $snapshot['active'] }}</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">In Transit</div>
        <div class="stat-value accent">{{ $snapshot['in_transit'] }}</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Delayed</div>
        <div class="stat-value danger">{{ $snapshot['delayed'] }}</div>
    </div>
</div>

{{-- ── Alerts ──────────────────────────────────────────────────────────── --}}
<div class="perf-heading">
    Alerts
    <span>raised in the {{ $windowLabel }}</span>
</div>

<div class="stats-grid">
    <div class="stat-tile">
        <div class="stat-label">Overspeed</div>
        <div class="stat-value">{{ $alerts['overspeed'] }}</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Delay</div>
        <div class="stat-value">{{ $alerts['delay'] }}</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Offline</div>
        <div class="stat-value">{{ $alerts['offline'] }}</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Geofence</div>
        <div class="stat-value">{{ $alerts['geofence'] }}</div>
    </div>
    <div class="stat-tile">
        <div class="stat-label">Total</div>
        <div class="stat-value {{ $alertTotal > 0 ? 'danger' : 'success' }}">{{ $alertTotal }}</div>
    </div>
</div>

@endsection

@push('styles')
<style>
    /* Stat numerals in Space Grotesk (page-scoped override of the layout's
       Syne default; Space Grotesk's max weight is 700) */
    .stat-value {
        font-family: var(--font-numeric);
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .perf-window {
        display: flex; align-items: center; gap: 8px;
        margin-bottom: 22px; flex-wrap: wrap;
    }
    .perf-window-label {
        font-size: 11px; color: var(--subtle);
        text-transform: uppercase; letter-spacing: 0.06em; margin-right: 4px;
    }
    .perf-window-btn {
        font-family: var(--font-mono); font-size: 12px;
        padding: 6px 14px; border-radius: 6px; text-decoration: none;
        color: var(--text); background: var(--muted);
        border: 1px solid var(--border); transition: all .15s;
    }
    .perf-window-btn:hover { border-color: var(--accent); color: var(--accent); }
    .perf-window-btn.active { background: var(--accent); color: #04121a; border-color: var(--accent); font-weight: 600; }

    .perf-heading {
        font-family: var(--font-display); font-weight: 700; font-size: 16px;
        margin: 30px 0 14px; display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap;
    }
    .perf-heading.sub { font-size: 13px; margin-top: 22px; }
    .perf-heading span {
        font-family: var(--font-mono); font-weight: 400; font-size: 11px; color: var(--subtle);
    }

    .perf-card {
        background: var(--card, var(--muted)); border: 1px solid var(--border);
        border-radius: 10px; overflow: hidden; margin-top: 4px;
    }
    .perf-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .perf-table th {
        text-align: left; padding: 12px 16px; font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.05em; color: var(--subtle);
        border-bottom: 1px solid var(--border);
    }
    .perf-table td { padding: 12px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    .perf-table tr:last-child td { border-bottom: none; }

    .uptime-bar {
        width: 100%; height: 7px; border-radius: 4px;
        background: var(--border); overflow: hidden;
    }
    .uptime-bar-fill { height: 100%; border-radius: 4px; transition: width .3s; }
</style>
@endpush
