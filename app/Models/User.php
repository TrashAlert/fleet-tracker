<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable, Loggable;

    // Don't log password changes in plaintext
    protected array $loggableHidden = ['password', 'remember_token'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'vehicle_id',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'last_login_at' => 'datetime',
        'password'      => 'hashed',
    ];

    // ── Role helpers ────────────────────────────────────────────────────────

    public function isAdmin(): bool    { return $this->role === 'admin'; }
    public function isManager(): bool  { return $this->role === 'manager'; }
    public function isDriver(): bool   { return $this->role === 'driver'; }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles);
    }

    public function getRoleBadgeColor(): string
    {
        return match($this->role) {
            'admin'   => 'var(--danger)',
            'manager' => 'var(--accent)',
            'driver'  => 'var(--success)',
            default   => 'var(--subtle)',
        };
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
