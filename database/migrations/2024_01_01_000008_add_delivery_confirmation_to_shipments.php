<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // When the vehicle first entered the destination radius
            $table->timestamp('near_destination_at')->nullable()->after('delay_notified');

            // When the vehicle left the radius after being near (for the 5-min flag timer)
            $table->timestamp('left_radius_at')->nullable()->after('near_destination_at');

            // Whether the driver has been flagged for leaving without confirming
            $table->boolean('delivery_flag_sent')->default(false)->after('left_radius_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['near_destination_at', 'left_radius_at', 'delivery_flag_sent']);
        });
    }
};
