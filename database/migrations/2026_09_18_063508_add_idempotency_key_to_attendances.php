<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Unique key dibentuk dari: device_id + uid + date (dibuat oleh ESP32)
            // Memungkinkan server mendeteksi dan menolak kiriman duplikat (retry safe)
            $table->string('idempotency_key')->nullable()->unique()->after('source');

            // Index untuk mempercepat pencarian berdasar tanggal & student
            $table->index('attendance_date');
            $table->index(['student_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
            $table->dropIndex(['attendance_date']);
            $table->dropIndex(['student_id', 'attendance_date']);
        });
    }
};
