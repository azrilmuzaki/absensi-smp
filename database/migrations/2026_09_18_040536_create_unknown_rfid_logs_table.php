<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('unknown_rfid_logs', function (Blueprint $table) {
            $table->id();
            $table->string('uid');
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unknown_rfid_logs');
    }
};
