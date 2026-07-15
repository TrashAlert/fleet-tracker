<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Service tier chosen at creation (see config/fleet.php delivery_tiers).
            // Null = custom expected date (admin escape hatch) or legacy rows;
            // expected_delivery_at stays the single source of truth for lateness.
            $table->string('delivery_tier')->nullable()->after('expected_delivery_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('delivery_tier');
        });
    }
};
