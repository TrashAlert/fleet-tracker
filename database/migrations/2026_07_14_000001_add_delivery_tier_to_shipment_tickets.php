<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_tickets', function (Blueprint $table) {
            // Customers now pick a service tier instead of a free date.
            $table->string('delivery_tier')->default('standard')->after('delivery_notes');
            // Superseded by the tier — existing rows are pre-launch test data.
            $table->dropColumn('requested_delivery_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_tickets', function (Blueprint $table) {
            $table->dropColumn('delivery_tier');
            $table->timestamp('requested_delivery_at')->nullable();
        });
    }
};
