<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;

class OriginLocation extends Model
{
    use Loggable;

    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'contact_name',
        'contact_phone',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
