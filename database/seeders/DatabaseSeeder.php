<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ── 1. Admin utama ───────────────────────────────────────────
        $isDevelopment = app()->isLocal() || app()->environment('testing');
        $adminEmail = config('app.initial_admin_email') ?: ($isDevelopment ? 'admin@admin.com' : null);
        $adminPassword = config('app.initial_admin_password') ?: ($isDevelopment ? 'password' : null);

        if (! $adminEmail || ! $adminPassword || (! $isDevelopment && strlen($adminPassword) < 12)) {
            throw new \RuntimeException(
                'Set INITIAL_ADMIN_EMAIL and INITIAL_ADMIN_PASSWORD (minimum 12 characters outside local/testing) before seeding.'
            );
        }

        User::firstOrCreate(['email' => $adminEmail], [
            'name' => 'Administrator',
            'password' => Hash::make($adminPassword),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);

        // ── 2. Tahun ajaran aktif ────────────────────────────────────
        AcademicYear::firstOrCreate(['name' => '2026/2027'], [
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        // ── 3. Perangkat ESP32 pertama ───────────────────────────────
        Device::firstOrCreate(['device_code' => 'ESP32-ABSEN-01'], [
            'device_name' => 'Gerbang Utama',
            'api_key' => config('app.initial_device_api_key') ?: bin2hex(random_bytes(32)),
            'status' => 'OFFLINE',
        ]);

        // ── 4. Pengaturan absensi default ───────────────────────────
        $this->call(AttendanceSettingsSeeder::class);

        // ── 5. Data development (kelas & siswa sample) ───────────────
        if (app()->isLocal() || app()->environment('testing')) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
