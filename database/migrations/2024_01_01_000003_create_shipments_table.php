<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained();
            $table->string('tracking_code')->unique(); // shared with client
            $table->string('client_name');
            $table->string('client_email');
            $table->string('client_phone')->nullable();
            $table->text('origin_address');
            $table->text('destination_address');
            $table->decimal('destination_lat', 10, 7);
            $table->decimal('destination_lng', 10, 7);
            $table->timestamp('expected_delivery_at');
            $table->timestamp('actual_delivery_at')->nullable();
            $table->enum('status', ['pending', 'in_transit', 'delayed', 'delivered', 'cancelled'])
                  ->default('pending');
            $table->boolean('delay_notified')->default(false);
            $table->timestamps();

            $table->index('tracking_code');
            $table->index(['status', 'expected_delivery_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
