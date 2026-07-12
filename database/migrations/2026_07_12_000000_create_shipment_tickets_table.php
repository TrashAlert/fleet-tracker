<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_tickets', function (Blueprint $table) {
            $table->id();
            // Short code shown on-screen after submitting — the customer reads
            // it to staff at the counter, who look the request up by it.
            $table->string('request_code')->unique();
            $table->enum('status', ['pending', 'approved', 'denied'])->default('pending');

            // Essential details typed by the customer. The requester IS the new
            // shipment's client. Destination is free text — the manager geocodes
            // it (and picks the pickup warehouse) when approving.
            $table->string('client_name');
            $table->string('client_email');
            $table->string('client_phone')->nullable();
            $table->text('destination_address');
            $table->text('delivery_notes')->nullable();
            $table->timestamp('requested_delivery_at')->nullable();

            // Review outcome
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            // Set when approval creates the real shipment
            $table->foreignId('created_shipment_id')->nullable()->constrained('shipments');

            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_tickets');
    }
};
