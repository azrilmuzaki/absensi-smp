<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassPlacement;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::where('is_active', true)->first();
        if (! $year) {
            $this->command->warn('Tidak ada tahun ajaran aktif. Lewati DevelopmentSeeder.');

            return;
        }

        // ── Buat kelas ────────────────────────────────────────────────
        $classNames = ['VII-A', 'VIII-A', 'IX-A'];
        $classes = [];
        foreach ($classNames as $cn) {
            $classes[$cn] = SchoolClass::create([
                'name' => $cn,
                'academic_year_id' => $year->id,
            ]);
        }

        // ── Buat Wali Kelas per kelas ──────────────────────────────────
        $waliIndex = 1;
        foreach ($classes as $className => $class) {
            User::create([
                'name' => 'Wali Kelas '.$className,
                'email' => 'walikelas'.$waliIndex.'@admin.com',
                'password' => bcrypt('password'),
                'role' => 'WALI_KELAS',
                'class_id' => $class->id,
                'is_active' => true,
            ]);
            $waliIndex++;
        }

        // ── Buat 5 siswa sample per kelas ─────────────────────────────
        $rfidCounter = 1;

        foreach ($classes as $className => $class) {
            for ($i = 1; $i <= 5; $i++) {
                $student = Student::create([
                    'nis' => str_pad(($rfidCounter * 10), 8, '0', STR_PAD_LEFT),
                    'nisn' => null,
                    'name' => 'Siswa '.$className.'-'.$i,
                    'gender' => $i % 2 === 0 ? 'P' : 'L',
                    'birth_date' => now()->subYears(rand(12, 15))->format('Y-m-d'),
                    'status' => 'ACTIVE',
                ]);

                StudentClassPlacement::create([
                    'student_id' => $student->id,
                    'school_class_id' => $class->id,
                    'academic_year_id' => $year->id,
                ]);

                RfidCard::create([
                    'student_id' => $student->id,
                    'uid' => strtoupper(str_pad(dechex($rfidCounter * 1000), 8, '0', STR_PAD_LEFT)),
                    'status' => 'ACTIVE',
                ]);

                $rfidCounter++;
            }
        }

        $totalStudents = $rfidCounter - 1;
        $totalClasses = count($classes);
        $this->command->info("Development data seeded: {$totalStudents} siswa di {$totalClasses} kelas, {$waliIndex} wali kelas.");
    }
}
