<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Free-text delivery instructions the geocoded address can't capture:
            // unit/house number, floor, gate code, "ring bell", landmark, etc.
            // Shown to the driver, never on the public tracking page.
            $table->text('delivery_notes')->nullable()->after('destination_address');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('delivery_notes');
        });
    }
};
