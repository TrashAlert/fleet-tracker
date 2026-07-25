<?php

namespace App\Providers;

use App\Support\FleetSettings;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Merge admin-editable overrides over config('fleet.*'). Self-guards
        // against a missing settings table so fresh clones / migrations boot.
        FleetSettings::apply();
    }
}
