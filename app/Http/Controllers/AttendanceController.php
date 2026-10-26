<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassPlacement;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $service) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $activeYear = AcademicYear::where('is_active', true)->first();

        $schoolTimezone = config('app.school_timezone');
        $dateId = $request->input('date', now($schoolTimezone)->toDateString());
        $date = Carbon::parse($dateId, $schoolTimezone);

        // Hanya kelas yang dapat dilihat oleh user
        if ($user->role === 'ADMIN') {
            $classes = SchoolClass::where('academic_year_id', $activeYear?->id)->orderBy('name')->get();
            $classId = $request->input('class_id', $classes->first()?->id);
        } else {
            $classes = collect([$user->schoolClass]);
            $classId = $user->class_id;
        }

        // Ambil siswa di kelas tersebut (di tahun ajaran aktif)
        $placements = StudentClassPlacement::with('student')
            ->where('school_class_id', $classId)
            ->where('academic_year_id', $activeYear?->id)
            ->get();

        $studentIds = $placements->pluck('student_id');

        // Ambil data absensi mereka pada tanggal tersebut
        $attendances = Attendance::whereIn('student_id', $studentIds)
            ->where('attendance_date', $date->toDateString())
            ->get()
            ->keyBy('student_id');

        // Susun dataset untuk view
        $dataset = $placements->map(function ($p) use ($attendances) {
            $student = $p->student;
            $attn = $attendances->get($student->id);

            return (object) [
                'student' => $student,
                'status' => $attn?->status ?? 'BELUM_HADIR',
                'time' => $attn?->time ?? '-',
                'source' => $attn?->source ?? '-',
            ];
        })->sortBy('student.name');

        return view('attendances.index', compact('dataset', 'classes', 'classId', 'date', 'activeYear'));
    }

    public function storeManual(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'date' => 'required|date',
            'status' => 'required|in:HADIR,TERLAMBAT,IZIN,SAKIT,ALPHA',
            'time' => ['nullable', 'required_if:status,HADIR,TERLAMBAT', 'date_format:H:i'],
            'reason' => 'nullable|string|max:100',
        ]);

        $activeYear = AcademicYear::where('is_active', true)->firstOrFail();
        $student = Student::findOrFail($data['student_id']);

        // Check permission if WALI_KELAS
        if (auth()->user()->role === 'WALI_KELAS') {
            $inClass = $student->placements()
                ->where('school_class_id', auth()->user()->class_id)
                ->where('academic_year_id', $activeYear->id)
                ->exists();
            abort_unless($inClass, 403, 'Anda tidak bisa absen siswa dari kelas lain.');
        }

        $this->service->manualInput(
            $student,
            $activeYear,
            $data['date'],
            $data['status'],
            $data['time'] ?? null,
            $data['reason'] ?? null,
        );

        return back()->with('success', "Absensi {$student->name} berhasil diperbarui (Status: {$data['status']}).");
    }
}
