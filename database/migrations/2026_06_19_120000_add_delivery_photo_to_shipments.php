<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Relative path to the proof-of-delivery photo the driver captures
            // on confirmation, e.g. "delivery-proofs/abc123.jpg" on the public disk.
            $table->string('delivery_photo_path')->nullable()->after('delivery_flag_sent');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('delivery_photo_path');
        });
    }
};
