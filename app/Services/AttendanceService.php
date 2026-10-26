<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceAudit;
use App\Models\AttendanceSetting;
use App\Models\Device;
use App\Models\RfidCard;
use App\Models\SchoolCalendar;
use App\Models\Student;
use App\Models\UnknownRfidLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /**
     * Memproses absensi dari perangkat ESP32.
     * Mengembalikan response array yang aman dikirim ke ESP32.
     */
    public function processTap(array $data)
    {
        return DB::transaction(function () use ($data) {
            $device = Device::where('api_key', $data['api_key'])->first();
            if (! $device) {
                return ['success' => false, 'code' => 'INVALID_DEVICE', 'message' => 'Perangkat tidak terdaftar.'];
            }

            // Perbarui last seen & IP
            $device->update([
                'last_seen_at' => now(),
                'status' => 'ONLINE',
            ]);

            $uid = strtoupper(trim($data['uid']));
            $schoolTimezone = config('app.school_timezone');
            $timestamp = ! empty($data['timestamp'])
                ? Carbon::parse($data['timestamp'])->setTimezone($schoolTimezone)
                : now($schoolTimezone);
            $dateOnly = $timestamp->toDateString();

            // 1. Cek Libur / Kalender
            $calendar = SchoolCalendar::where('date', $dateOnly)->first();
            if ($calendar && $calendar->status === 'HOLIDAY') {
                return ['success' => false, 'code' => 'HOLIDAY', 'message' => 'Hari ini libur: '.$calendar->description];
            }

            // 2. Cek Kartu RFID Aktif
            $card = RfidCard::with([
                'student.placements' => function ($q) {
                    $q->whereHas('academicYear', fn ($aq) => $aq->where('is_active', true));
                },
            ])
                ->where('uid', $uid)
                ->where('status', 'ACTIVE')
                ->first();

            if (! $card || ! $card->student) {
                UnknownRfidLog::create(['uid' => $uid, 'device_id' => $device->id]);

                return ['success' => false, 'code' => 'UNKNOWN_CARD', 'message' => 'Kartu tidak dikenali.'];
            }

            $student = $card->student;

            if ($student->status !== 'ACTIVE') {
                return ['success' => false, 'code' => 'INACTIVE_STUDENT', 'message' => 'Status siswa tidak aktif.'];
            }

            // 3. Cek Tahun Ajaran Aktif
            $activeYear = AcademicYear::where('is_active', true)->first();
            if (! $activeYear) {
                return ['success' => false, 'code' => 'NO_ACTIVE_YEAR', 'message' => 'Tidak ada tahun ajaran aktif.'];
            }

            // 4. Pastikan Siswa punya penempatan di tahun ajaran ini
            $placement = $student->placements->first();
            if (! $placement) {
                return ['success' => false, 'code' => 'NO_PLACEMENT', 'message' => 'Siswa belum terdaftar di kelas manapun.'];
            }

            // 5. Cek Idempotency / Duplikasi (mencegah double tap pada tanggal yang sama)
            // Khusus ESP32 idempotency key akan kita gunakan untuk resend offline logic
            $idempotencyKey = $data['idempotency_key'] ?? $student->id.'-'.$dateOnly;

            $existing = Attendance::where('student_id', $student->id)
                ->where('attendance_date', $dateOnly)
                ->first();

            if ($existing) {
                return [
                    'success' => true,
                    'code' => 'DUPLICATE',
                    'message' => "Siswa {$student->name} sudah absen hari ini sebagai {$existing->status}.",
                    'student' => $student->name,
                    'status' => $existing->status,
                ];
            }

            // 6. Penentuan Status Hadir / Terlambat
            $timeOnly = $timestamp->format('H:i:s');
            $settings = AttendanceSetting::pluck('value', 'key');

            $entryStart = $settings['jam_masuk'] ?? '06:00:00';
            $entryLate = $settings['jam_terlambat'] ?? '07:05:00';
            $entryEnd = $settings['batas_scan'] ?? '10:00:00';

            if ($timeOnly < $entryStart) {
                return ['success' => false, 'code' => 'TOO_EARLY', 'message' => 'Belum waktunya absensi.'];
            }
            if ($timeOnly > $entryEnd) {
                return ['success' => false, 'code' => 'TOO_LATE', 'message' => 'Waktu absen ditutup. Hubungi petugas piket.'];
            }

            $status = ($timeOnly <= $entryLate) ? 'HADIR' : 'TERLAMBAT';

            // 7. Simpan Absensi
            Attendance::create([
                'student_id' => $student->id,
                'academic_year_id' => $activeYear->id,
                'attendance_date' => $dateOnly,
                'status' => $status,
                'time' => $timeOnly,
                'device_id' => $device->id,
                'source' => 'RFID',
                'idempotency_key' => $idempotencyKey,
            ]);

            return [
                'success' => true,
                'code' => 'SUCCESS',
                'message' => "Terima kasih, {$student->name}.",
                'student' => $student->name,
                'status' => $status,
            ];
        });
    }

    /**
     * Koreksi atau input status absensi manual oleh Admin/Wali.
     */
    public function manualInput(
        Student $student,
        AcademicYear $year,
        string $date,
        string $newStatus,
        ?string $time,
        ?string $reason = null,
    ): Attendance {
        return DB::transaction(function () use ($student, $year, $date, $newStatus, $time, $reason) {
            $attendance = Attendance::where('student_id', $student->id)
                ->where('attendance_date', $date)
                ->first();

            $oldStatus = $attendance?->status;
            $schoolTimezone = config('app.school_timezone');
            $attendanceTime = in_array($newStatus, ['HADIR', 'TERLAMBAT'], true)
                ? ($time ? $time.':00' : $attendance?->time)
                : null;

            if ($attendance) {
                $attendance->update([
                    'status' => $newStatus,
                    'time' => $attendanceTime,
                    'source' => 'MANUAL',
                ]);
            } else {
                $attendance = Attendance::create([
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'attendance_date' => $date,
                    'status' => $newStatus,
                    'time' => $attendanceTime,
                    'device_id' => null,
                    'source' => 'MANUAL',
                    'idempotency_key' => $student->id.'-m-'.Carbon::parse($date, $schoolTimezone)->timestamp,
                ]);
            }

            // Rekam audit trail
            AttendanceAudit::create([
                'attendance_id' => $attendance->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => auth()->id(),
                'reason' => $reason ?? 'Koreksi manual',
            ]);

            return $attendance;
        });
    }
}
