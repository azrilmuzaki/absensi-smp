<?php

namespace Database\Seeders;

use App\Models\AttendanceSetting;
use Illuminate\Database\Seeder;

class AttendanceSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'jam_masuk', 'value' => '06:00:00', 'description' => 'Jam awal absensi dibuka. Tap sebelum ini → ditolak.'],
            ['key' => 'jam_terlambat', 'value' => '07:05:00', 'description' => 'Batas tepat waktu. Tap setelah ini → TERLAMBAT.'],
            ['key' => 'batas_scan', 'value' => '10:00:00', 'description' => 'Jam terakhir tap diterima. Setelah ini → ditolak.'],
            ['key' => 'jam_alpha', 'value' => '10:15:00', 'description' => 'Jam scheduler menandai siswa tidak hadir sebagai ALPHA.'],
            ['key' => 'hari_libur', 'value' => '0,6', 'description' => 'Hari libur mingguan. 0=Minggu, 6=Sabtu. Pisahkan koma.'],
        ];

        foreach ($settings as $setting) {
            AttendanceSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
