<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Failed-delivery tracking: how many door attempts were made, and
            // the details of the most recent one (fuller history lives in
            // activity_logs, which also feeds the client timeline).
            $table->unsignedTinyInteger('delivery_attempts')->default(0)->after('delivery_flag_sent');
            $table->timestamp('last_attempt_at')->nullable()->after('delivery_attempts');
            $table->string('last_attempt_reason')->nullable()->after('last_attempt_at');
            $table->string('last_attempt_photo_path')->nullable()->after('last_attempt_reason');

            // Widen status from an enum to a plain string so the new terminal
            // value 'returned' is accepted. An enum carries a CHECK constraint
            // on BOTH MySQL and SQLite that would otherwise reject it; ->change()
            // rebuilds the column on both drivers and drops the old constraint.
            // Allowed values are enforced at the app layer (updateShipmentStatus
            // validation + the code that sets status).
            $table->string('status', 20)->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_attempts',
                'last_attempt_at',
                'last_attempt_reason',
                'last_attempt_photo_path',
            ]);

            $table->enum('status', ['pending', 'in_transit', 'delayed', 'delivered', 'cancelled'])
                ->default('pending')->change();
        });
    }
};
