<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\SchoolCalendar;
use App\Models\StudentClassPlacement;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MarkAbsentStudents extends Command
{
    protected $signature = 'attendance:mark-alpha {--date= : Tanggal (YYYY-MM-DD), default hari ini}';

    protected $description = 'Tandai siswa yang tidak hadir sebagai ALPHA secara otomatis';

    public function handle(): int
    {
        $schoolTimezone = config('app.school_timezone');
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'), $schoolTimezone)
            : now($schoolTimezone);
        $dateStr = $date->toDateString();

        // Cek hari libur mingguan
        $settings = AttendanceSetting::pluck('value', 'key');
        $holidayDays = explode(',', $settings['hari_libur'] ?? '0,6');
        if (in_array($date->dayOfWeek, $holidayDays)) {
            $this->info("Hari libur mingguan ({$dateStr}). Tidak ada proses ALPHA.");

            return 0;
        }

        // Cek kalender libur
        $calendar = SchoolCalendar::where('date', $dateStr)->where('status', 'HOLIDAY')->first();
        if ($calendar) {
            $this->info("Hari libur: {$calendar->description}. Tidak ada proses ALPHA.");

            return 0;
        }

        $activeYear = AcademicYear::where('is_active', true)->first();
        if (! $activeYear) {
            $this->error('Tidak ada tahun ajaran aktif.');

            return 1;
        }

        // Ambil semua siswa aktif di tahun ajaran ini
        $allStudentIds = StudentClassPlacement::where('academic_year_id', $activeYear->id)
            ->whereHas('student', fn ($q) => $q->where('status', 'ACTIVE'))
            ->pluck('student_id');

        // Ambil siswa yang SUDAH punya absensi hari ini
        $presentIds = Attendance::where('attendance_date', $dateStr)
            ->where('academic_year_id', $activeYear->id)
            ->pluck('student_id');

        // Siswa yang BELUM absen = ALPHA
        $absentIds = $allStudentIds->diff($presentIds);

        if ($absentIds->isEmpty()) {
            $this->info("Semua siswa sudah tercatat absensi untuk {$dateStr}.");

            return 0;
        }

        $inserted = 0;
        foreach ($absentIds as $studentId) {
            Attendance::create([
                'student_id' => $studentId,
                'academic_year_id' => $activeYear->id,
                'attendance_date' => $dateStr,
                'status' => 'ALPHA',
                'time' => null,
                'device_id' => null,
                'source' => 'SYSTEM',
                'idempotency_key' => $studentId.'-alpha-'.$dateStr,
            ]);
            $inserted++;
        }

        $this->info("Selesai: {$inserted} siswa ditandai ALPHA untuk {$dateStr}.");

        return 0;
    }
}
