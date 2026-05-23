<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'causer_type',
        'causer_label',
        'subject_type',
        'subject_id',
        'subject_label',
        'action',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'logged_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'logged_at'  => 'datetime',
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForSubject($query, string $type, int $id)
    {
        return $query->where('subject_type', $type)->where('subject_id', $id);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderByDesc('logged_at')->limit($limit);
    }
}
