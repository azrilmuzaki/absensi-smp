<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\StudentClassPlacement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $activeYear = AcademicYear::where('is_active', true)->first();
        $today = now(config('app.school_timezone'))->toDateString();

        if ($user->role === 'ADMIN') {
            return view('dashboard.admin', $this->adminData($activeYear, $today) + [
                'year' => $activeYear,
                'today' => $today,
            ]);
        }

        return view('dashboard.wali', $this->waliData($user, $activeYear, $today) + [
            'myClass' => $user->schoolClass,
            'year' => $activeYear,
            'today' => $today,
        ]);
    }

    public function live(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $activeYear = AcademicYear::where('is_active', true)->first();
        $schoolTimezone = config('app.school_timezone');
        $today = now($schoolTimezone)->toDateString();
        $isAdmin = $user->role === 'ADMIN';
        $data = $isAdmin
            ? $this->adminData($activeYear, $today)
            : $this->waliData($user, $activeYear, $today);

        return response()->json([
            'stats' => $data['stats'],
            'recent_attendance' => $data['recentAttendance']->map(static function (Attendance $attendance): array {
                return [
                    'student_name' => $attendance->student?->name ?? '-',
                    'time' => (string) $attendance->time,
                    'status' => $attendance->status,
                    'source' => $attendance->source,
                ];
            })->values(),
            'absent_students' => $isAdmin ? [] : $data['absentStudents']->pluck('name')->values(),
            'today_label' => now($schoolTimezone)->format('d/m/Y'),
            'updated_at_label' => now($schoolTimezone)->format('H:i:s'),
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    /**
     * @return array{stats: array<string, int>, recentAttendance: EloquentCollection<int, Attendance>}
     */
    private function adminData(?AcademicYear $year, string $today): array
    {
        $totalStudents = Student::where('status', 'ACTIVE')->count();

        $todayAttendance = Attendance::where('attendance_date', $today)
            ->where('academic_year_id', $year?->id)
            ->get();

        $stats = [
            'total_students' => $totalStudents,
            'hadir' => $todayAttendance->where('status', 'HADIR')->count(),
            'terlambat' => $todayAttendance->where('status', 'TERLAMBAT')->count(),
            'izin' => $todayAttendance->where('status', 'IZIN')->count(),
            'sakit' => $todayAttendance->where('status', 'SAKIT')->count(),
            'alpha' => $todayAttendance->where('status', 'ALPHA')->count(),
        ];

        $recentAttendance = Attendance::with('student')
            ->where('attendance_date', $today)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return compact('stats', 'recentAttendance');
    }

    /**
     * @return array{
     *     stats: array<string, int>,
     *     absentStudents: EloquentCollection<int, Student>,
     *     recentAttendance: EloquentCollection<int, Attendance>
     * }
     */
    private function waliData(User $user, ?AcademicYear $year, string $today): array
    {
        $studentIds = StudentClassPlacement::where('school_class_id', $user->class_id)
            ->where('academic_year_id', $year?->id)
            ->pluck('student_id');

        $totalStudents = $studentIds->count();

        $todayAttendance = Attendance::where('attendance_date', $today)
            ->where('academic_year_id', $year?->id)
            ->whereIn('student_id', $studentIds)
            ->get();

        $stats = [
            'total_students' => $totalStudents,
            'hadir' => $todayAttendance->where('status', 'HADIR')->count(),
            'terlambat' => $todayAttendance->where('status', 'TERLAMBAT')->count(),
            'izin' => $todayAttendance->where('status', 'IZIN')->count(),
            'sakit' => $todayAttendance->where('status', 'SAKIT')->count(),
            'alpha' => $todayAttendance->where('status', 'ALPHA')->count(),
        ];

        $absentStudents = Student::whereIn('id', $studentIds)
            ->whereNotIn('id', $todayAttendance->pluck('student_id'))
            ->orderBy('name')
            ->get();

        $recentAttendance = Attendance::with('student')
            ->where('attendance_date', $today)
            ->whereIn('student_id', $studentIds)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return compact('stats', 'absentStudents', 'recentAttendance');
    }
}
