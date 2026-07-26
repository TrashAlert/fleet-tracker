<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Activity log listing page.
     */
    public function index(Request $request)
    {
        $query = ActivityLog::orderByDesc('logged_at');

        // Keyword search — matches a vehicle plate, a user/client name, or any
        // alert wording, since the identifying text is spread across the label,
        // the description, and (for web actions) the causer label.
        if ($request->filled('search')) {
            $term = '%'.$request->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('subject_label', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('causer_label', 'like', $term);
            });
        }

        // Precise vehicle filter — Vehicle-subject rows for the chosen vehicle
        // (CRUD, overspeed, telemetry, offline). Cross-references from other
        // subjects are reachable via the keyword search instead.
        if ($request->filled('vehicle_id')) {
            $query->where('subject_type', 'Vehicle')->where('subject_id', $request->vehicle_id);
        }

        // Filter by subject type
        if ($request->filled('subject')) {
            $query->where('subject_type', $request->subject);
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by causer type
        if ($request->filled('causer')) {
            $query->where('causer_type', $request->causer);
        }

        // Date range
        if ($request->filled('from')) {
            $query->whereDate('logged_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('logged_at', '<=', $request->to);
        }

        $logs = $query->paginate(50)->withQueryString();

        $subjectTypes = ActivityLog::select('subject_type')->distinct()->pluck('subject_type');
        $actionTypes = ActivityLog::select('action')->distinct()->pluck('action');
        $causerTypes = ActivityLog::select('causer_type')->distinct()->pluck('causer_type');
        $vehicles = Vehicle::orderBy('plate_number')->get(['id', 'plate_number', 'name']);

        return view('fleet.activity-log', compact('logs', 'subjectTypes', 'actionTypes', 'causerTypes', 'vehicles'));
    }

    /**
     * JSON endpoint — latest logs for a specific subject (e.g. vehicle detail panel).
     */
    public function forSubject(Request $request, string $type, int $id): JsonResponse
    {
        $logs = ActivityLog::forSubject($type, $id)
            ->recent(30)
            ->get(['action', 'description', 'causer_type', 'causer_label', 'logged_at']);

        return response()->json($logs);
    }

    /**
     * JSON — latest N logs across all subjects (dashboard widget).
     */
    public function latest(): JsonResponse
    {
        $logs = ActivityLog::recent(20)
            ->get(['action', 'subject_type', 'subject_label', 'description', 'causer_type', 'logged_at']);

        return response()->json($logs);
    }
}
