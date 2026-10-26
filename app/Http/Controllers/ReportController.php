<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\StudentClassPlacement;
use App\Services\MonthlyAttendanceWorkbookExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $activeYear = AcademicYear::where('is_active', true)->first();

        $schoolTimezone = config('app.school_timezone');
        $schoolTime = now($schoolTimezone);
        $month = $request->input('month', $schoolTime->month);
        $year = $request->input('year', $schoolTime->year);

        // Kelas yang bisa dilihat
        if ($user->role === 'ADMIN') {
            $classes = SchoolClass::where('academic_year_id', $activeYear?->id)->orderBy('name')->get();
            $classId = $request->input('class_id', $classes->first()?->id);
        } else {
            $classes = SchoolClass::where('id', $user->class_id)->get();
            $classId = $user->class_id;
        }

        // Siswa di kelas ini
        $placements = StudentClassPlacement::with('student')
            ->where('school_class_id', $classId)
            ->where('academic_year_id', $activeYear?->id)
            ->get()
            ->sortBy('student.name');

        $studentIds = $placements->pluck('student_id');

        // Ambil semua absensi di bulan ini untuk siswa di kelas ini
        $startDate = Carbon::parse(sprintf('%04d-%02d-01', $year, $month), $schoolTimezone)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $attendances = Attendance::whereIn('student_id', $studentIds)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get();

        // Susun rekap per siswa
        $report = $placements->map(function ($p) use ($attendances) {
            $studentAttn = $attendances->where('student_id', $p->student_id);

            return (object) [
                'student' => $p->student,
                'hadir' => $studentAttn->where('status', 'HADIR')->count(),
                'terlambat' => $studentAttn->where('status', 'TERLAMBAT')->count(),
                'izin' => $studentAttn->where('status', 'IZIN')->count(),
                'sakit' => $studentAttn->where('status', 'SAKIT')->count(),
                'alpha' => $studentAttn->where('status', 'ALPHA')->count(),
                'total' => $studentAttn->count(),
            ];
        });

        // Hitung hari efektif sekolah di bulan ini (non weekend)
        $schoolDays = 0;
        $current = $startDate->copy();
        while ($current->lte($endDate) && $current->lte($schoolTime)) {
            if (! $current->isWeekend()) {
                $schoolDays++;
            }
            $current->addDay();
        }

        $className = $classes->firstWhere('id', $classId)?->name ?? '-';

        return view('reports.index', compact('report', 'classes', 'classId', 'month', 'year', 'className', 'schoolDays'));
    }

    public function export(Request $request, MonthlyAttendanceWorkbookExporter $workbookExporter)
    {
        $data = Validator::make($request->all(), [
            'period' => ['required', Rule::in(['day', 'month', 'year'])],
            'date' => ['required_if:period,day', 'nullable', 'date'],
            'month' => ['required_if:period,month', 'nullable', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:1900,2200'],
            'class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
        ])->validate();

        $user = auth()->user();
        $activeYear = AcademicYear::where('is_active', true)->first();

        if (! $activeYear) {
            return back()->withErrors(['class_id' => 'Tidak ada tahun ajaran aktif.']);
        }

        if ($user->role === 'ADMIN') {
            $classId = $data['class_id'] ?? SchoolClass::where('academic_year_id', $activeYear->id)->value('id');
            $schoolClass = $classId
                ? SchoolClass::where('academic_year_id', $activeYear->id)->find($classId)
                : null;

            abort_unless($schoolClass, 403, 'Kelas tidak tersedia pada tahun ajaran aktif.');
        } else {
            $classId = $user->class_id;
            $schoolClass = $classId
                ? SchoolClass::where('academic_year_id', $activeYear->id)->find($classId)
                : null;

            abort_unless($schoolClass, 403, 'Kelas wali tidak tersedia pada tahun ajaran aktif.');
        }

        $schoolTimezone = config('app.school_timezone');

        if ($data['period'] === 'day') {
            $startDate = Carbon::parse($data['date'], $schoolTimezone)->startOfDay();
            $endDate = $startDate->copy()->endOfDay();
            $periodLabel = $startDate->format('Y-m-d');
        } elseif ($data['period'] === 'month') {
            $startDate = Carbon::parse(sprintf('%04d-%02d-01', $data['year'], $data['month']), $schoolTimezone)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            $periodLabel = $startDate->format('Y-m');
        } else {
            $startDate = Carbon::parse($data['year'].'-01-01', $schoolTimezone)->startOfYear();
            $endDate = $startDate->copy()->endOfYear();
            $periodLabel = $startDate->format('Y');
        }

        $placements = StudentClassPlacement::with('student')
            ->where('school_class_id', $schoolClass->id)
            ->where('academic_year_id', $activeYear->id)
            ->get()
            ->sortBy(fn ($placement) => $placement->student?->name ?? '');

        $studentIds = $placements->pluck('student_id');
        $attendanceByStudent = Attendance::whereIn('student_id', $studentIds)
            ->where('academic_year_id', $activeYear->id)
            ->whereBetween('attendance_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('attendance_date')
            ->get()
            ->groupBy('student_id');

        $classFileName = preg_replace('/[^A-Za-z0-9_-]/', '-', $schoolClass->name);

        if ($data['period'] === 'month') {
            $monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            $daysInMonth = $startDate->daysInMonth;
            $students = [];

            foreach ($placements as $placement) {
                $student = $placement->student;
                if (! $student) {
                    continue;
                }

                $records = $attendanceByStudent->get($student->id, collect());
                $days = array_fill(1, $daysInMonth, '');
                foreach ($records as $attendance) {
                    $days[$attendance->attendance_date->day] = match ($attendance->status) {
                        'HADIR' => 'H',
                        'TERLAMBAT' => 'T',
                        'IZIN' => 'I',
                        'SAKIT' => 'S',
                        'ALPHA' => 'A',
                        default => '',
                    };
                }

                $sakit = $records->where('status', 'SAKIT')->count();
                $izin = $records->where('status', 'IZIN')->count();
                $alpha = $records->where('status', 'ALPHA')->count();
                $students[] = [
                    'name' => $student->name,
                    'days' => $days,
                    'sick' => $sakit,
                    'leave' => $izin,
                    'alpha' => $alpha,
                    'total' => $sakit + $izin + $alpha,
                ];
            }

            $semester = $startDate->month >= 7 ? 'Ganjil' : 'Genap';
            $workbook = $workbookExporter->export(
                $schoolClass->name,
                $monthNames[$startDate->month].' '.$startDate->year,
                $semester,
                $activeYear->name,
                $daysInMonth,
                $students
            );

            return response()->streamDownload(
                static fn () => print ($workbook),
                sprintf('absensi-bulanan-%s-%s.xlsx', $periodLabel, $classFileName),
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            );
        }

        $filename = sprintf('absensi-%s-%s-%s.csv', $data['period'], $periodLabel, $classFileName);

        return response()->streamDownload(function () use ($data, $placements, $attendanceByStudent, $schoolClass, $activeYear, $periodLabel) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF"); // BOM agar karakter Indonesia terbaca baik di Excel.

            $safe = static function ($value) {
                $value = (string) ($value ?? '');

                return preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
            };
            $writeRow = static fn (array $row) => fputcsv($output, $row, ',', '"', '');

            $writeRow(['Tahun Ajaran', $activeYear->name]);
            $writeRow(['Kelas', $schoolClass->name]);
            $writeRow([$data['period'] === 'day' ? 'Tanggal' : 'Tahun', $periodLabel]);
            $writeRow([]);
            $writeRow(['NIS', 'NISN', 'Nama Siswa', 'Kelas', 'Tanggal Absensi', 'Waktu', 'Status', 'Sumber']);

            foreach ($placements as $placement) {
                $student = $placement->student;
                if (! $student) {
                    continue;
                }

                $records = $attendanceByStudent->get($student->id, collect());

                if ($data['period'] === 'day') {
                    $attendance = $records->first();
                    $writeRow([
                        $safe($student->nis),
                        $safe($student->nisn),
                        $safe($student->name),
                        $safe($schoolClass->name),
                        $periodLabel,
                        $attendance?->time ?? '',
                        $attendance?->status ?? 'BELUM_HADIR',
                        $attendance?->source ?? '',
                    ]);

                    continue;
                }

                if ($records->isEmpty()) {
                    $writeRow([
                        $safe($student->nis),
                        $safe($student->nisn),
                        $safe($student->name),
                        $safe($schoolClass->name),
                        '',
                        '',
                        'TIDAK ADA CATATAN',
                        '',
                    ]);

                    continue;
                }

                foreach ($records as $attendance) {
                    $writeRow([
                        $safe($student->nis),
                        $safe($student->nisn),
                        $safe($student->name),
                        $safe($schoolClass->name),
                        $attendance->attendance_date->toDateString(),
                        $attendance->time ?? '',
                        $attendance->status,
                        $attendance->source,
                    ]);
                }
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
