<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Index pada rfid_cards.uid — digunakan setiap kali ESP32 scan kartu
        Schema::table('rfid_cards', function (Blueprint $table) {
            $table->index('uid');
            $table->index('status');
            $table->index(['student_id', 'status']);
        });

        // Index pada student_class_placements — digunakan saat query siswa per kelas/tahun ajaran
        Schema::table('student_class_placements', function (Blueprint $table) {
            $table->index('academic_year_id');
            $table->index(['school_class_id', 'academic_year_id']);
        });

        // Index pada students — untuk pencarian & filter
        Schema::table('students', function (Blueprint $table) {
            $table->index('status');
            $table->index('name');
        });

        // Index pada unknown_rfid_logs — untuk admin monitor
        Schema::table('unknown_rfid_logs', function (Blueprint $table) {
            $table->index('uid');
            $table->index('device_id');
        });

        // Index pada audit_logs — untuk query riwayat per user atau per model
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('rfid_cards', function (Blueprint $table) {
            $table->dropIndex(['uid']);
            $table->dropIndex(['status']);
            $table->dropIndex(['student_id', 'status']);
        });

        Schema::table('student_class_placements', function (Blueprint $table) {
            $table->dropIndex(['academic_year_id']);
            $table->dropIndex(['school_class_id', 'academic_year_id']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['name']);
        });

        Schema::table('unknown_rfid_logs', function (Blueprint $table) {
            $table->dropIndex(['uid']);
            $table->dropIndex(['device_id']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
