<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Who/what triggered the action
            $table->string('causer_type')->default('system'); // 'system', 'mqtt', 'web'
            $table->string('causer_label')->nullable();       // e.g. IP address, device ID, "Fleet Manager"

            // What was affected
            $table->string('subject_type');                   // e.g. 'Vehicle', 'Shipment', 'Alert'
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();      // e.g. plate number, tracking code

            // The action
            $table->string('action');                         // 'created', 'updated', 'deleted', 'toggled', 'mqtt_received', etc.
            $table->text('description');                      // Human-readable summary

            // Change detail
            $table->json('old_values')->nullable();           // Before state (for updates)
            $table->json('new_values')->nullable();           // After state

            // Context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();

            // Indexes for filtering
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
            $table->index('logged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
