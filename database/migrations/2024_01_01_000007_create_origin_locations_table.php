<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('origin_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');                      // e.g. "Main Warehouse", "KL Hub"
            $table->text('address');                     // Full address string
            $table->decimal('latitude',  10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('origin_locations');
    }
};
