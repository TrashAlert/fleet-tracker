<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_telemetry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('speed_kmh')->default(0);
            $table->float('heading')->default(0);       // degrees 0–360
            $table->integer('satellites')->default(0);  // GPS satellite count
            $table->float('hdop')->nullable();          // horizontal dilution of precision
            $table->timestamp('recorded_at');           // timestamp from GPS device
            $table->timestamps();

            $table->index(['vehicle_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_telemetry');
    }
};
