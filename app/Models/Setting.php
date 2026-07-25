<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single admin-editable override for a config('fleet.*') key. See
 * App\Support\FleetSettings for the mechanism (boot-time config merge).
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];
}
