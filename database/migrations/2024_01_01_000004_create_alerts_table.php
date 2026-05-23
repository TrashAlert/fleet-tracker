<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['overspeed', 'delay', 'offline', 'geofence']);
            $table->text('message');
            $table->json('meta')->nullable(); // extra data e.g. speed, coordinates
            $table->boolean('is_read')->default(false);
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->index(['vehicle_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
