<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Shipment;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    /**
     * System performance overview (admin/manager).
     *
     * All metrics are derived from existing data — no extra plumbing:
     *   - Device (ESP32) uptime  : gaps in gps_telemetry
     *   - Delivery performance   : shipments (expected vs actual delivery)
     *   - Alert breakdown        : alerts grouped by type
     *
     * NOTE: the device-uptime query uses a window function (LAG),
     * which requires MySQL 8.0+.
     */
    public function index(Request $request)
    {
        // Window selector: 1 / 7 / 30 days
        $days = (int) $request->query('days', 7);
        if (! in_array($days, [1, 7, 30], true)) {
            $days = 7;
        }

        $now           = now();
        $start         = $now->copy()->subDays($days);
        $startStr      = $start->toDateTimeString();
        $windowSeconds = max(1, (int) $start->diffInSeconds($now));
        $offlineGap    = (int) config('fleet.offline_alert_threshold_seconds', 180);

        // ── DEVICE (ESP32) UPTIME ────────────────────────────────────────
        // Telemetry coverage per vehicle within the window.
        $coverage = DB::table('gps_telemetry')
            ->select(
                'vehicle_id',
                DB::raw('COUNT(*) as points'),
                DB::raw('MAX(recorded_at) as last_at')
            )
            ->where('recorded_at', '>=', $startStr)
            ->groupBy('vehicle_id')
            ->get()
            ->keyBy('vehicle_id');

        // Sum of gaps longer than the offline threshold = "internal" downtime
        // (silent stretches between two reported points).
        $gapRows = DB::select("
            SELECT vehicle_id,
                   COUNT(*)                       AS episodes,
                   COALESCE(SUM(gap_seconds), 0)  AS downtime_seconds
            FROM (
                SELECT vehicle_id,
                       TIMESTAMPDIFF(
                           SECOND,
                           LAG(recorded_at) OVER (PARTITION BY vehicle_id ORDER BY recorded_at),
                           recorded_at
                       ) AS gap_seconds
                FROM gps_telemetry
                WHERE recorded_at >= ?
            ) g
            WHERE g.gap_seconds > ?
            GROUP BY vehicle_id
        ", [$startStr, $offlineGap]);

        $gaps = collect($gapRows)->keyBy('vehicle_id');

        $vehicles = Vehicle::with('latestPosition')->orderBy('name')->get();

        $deviceStats = $vehicles->map(function (Vehicle $v) use ($coverage, $gaps, $now, $windowSeconds, $offlineGap) {
            $cov     = $coverage->get($v->id);
            $gap     = $gaps->get($v->id);
            $hasData = $cov && $cov->points > 0;

            if (! $hasData) {
                // No telemetry in the window at all → fully down for the window.
                return (object) [
                    'vehicle'    => $v,
                    'has_data'   => false,
                    'online_now' => false,
                    'last_seen'  => $v->latestPosition?->recorded_at,
                    'uptime_pct' => 0.0,
                    'downtime'   => $windowSeconds,
                    'episodes'   => $gap ? (int) $gap->episodes : 0,
                ];
            }

            $downtime = $gap ? (int) $gap->downtime_seconds : 0;
            $episodes = $gap ? (int) $gap->episodes : 0;

            // Trailing silence (last point → now) counts as current downtime.
            $lastAt    = Carbon::parse($cov->last_at);
            $trailing  = (int) $now->diffInSeconds($lastAt);   // magnitude
            $onlineNow = $trailing <= $offlineGap;
            if (! $onlineNow) {
                $downtime += $trailing;
                $episodes += 1;
            }

            $uptime = max(0, $windowSeconds - $downtime);
            $pct    = round(min(100, $uptime / $windowSeconds * 100), 1);

            return (object) [
                'vehicle'    => $v,
                'has_data'   => true,
                'online_now' => $onlineNow,
                'last_seen'  => $lastAt,
                'uptime_pct' => $pct,
                'downtime'   => $downtime,
                'episodes'   => $episodes,
            ];
        });

        $fleet = [
            'total'       => $vehicles->count(),
            'online_now'  => $deviceStats->where('online_now', true)->count(),
            'offline_now' => $deviceStats->where('online_now', false)->count(),
            'avg_uptime'  => $deviceStats->count() ? round($deviceStats->avg('uptime_pct'), 1) : 0.0,
        ];

        // ── DELIVERY PERFORMANCE (delivered within the window) ───────────
        $delivered = Shipment::where('status', 'delivered')
            ->whereNotNull('actual_delivery_at')
            ->where('actual_delivery_at', '>=', $startStr)
            ->get(['expected_delivery_at', 'actual_delivery_at', 'created_at']);

        $deliveredCount = $delivered->count();

        $withEta = $delivered->filter(fn ($s) => $s->expected_delivery_at);
        $onTime  = $withEta->filter(fn ($s) => $s->actual_delivery_at->lte($s->expected_delivery_at))->count();
        $late    = $withEta->count() - $onTime;

        // Use raw timestamps to avoid any signed-diff ambiguity.
        $avgLatenessMin = $withEta->count()
            ? round($withEta->avg(fn ($s) =>
                ($s->actual_delivery_at->getTimestamp() - $s->expected_delivery_at->getTimestamp()) / 60), 1)
            : null;

        $avgFulfilMin = $deliveredCount
            ? round($delivered->avg(fn ($s) =>
                ($s->actual_delivery_at->getTimestamp() - $s->created_at->getTimestamp()) / 60), 1)
            : null;

        $onTimePct = $withEta->count() ? round($onTime / $withEta->count() * 100, 1) : null;

        // Point-in-time snapshot (not windowed)
        $snapshot = [
            'active'     => Shipment::whereIn('status', ['pending', 'in_transit', 'delayed'])->count(),
            'in_transit' => Shipment::where('status', 'in_transit')->count(),
            'delayed'    => Shipment::where('status', 'delayed')->count(),
        ];

        // ── ALERT BREAKDOWN (within the window) ──────────────────────────
        $alertCounts = Alert::where('triggered_at', '>=', $startStr)
            ->select('type', DB::raw('COUNT(*) as c'))
            ->groupBy('type')
            ->pluck('c', 'type');

        $alerts = [
            'overspeed' => (int) ($alertCounts['overspeed'] ?? 0),
            'delay'     => (int) ($alertCounts['delay'] ?? 0),
            'offline'   => (int) ($alertCounts['offline'] ?? 0),
            'geofence'  => (int) ($alertCounts['geofence'] ?? 0),
        ];
        $alertTotal = array_sum($alerts);

        return view('fleet.performance', compact(
            'days',
            'offlineGap',
            'deviceStats',
            'fleet',
            'deliveredCount',
            'onTime',
            'late',
            'onTimePct',
            'avgLatenessMin',
            'avgFulfilMin',
            'snapshot',
            'alerts',
            'alertTotal'
        ));
    }
}
