<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\StudentClassPlacement;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $activeYear = AcademicYear::where('is_active', true)->first();
        $today = now(config('app.school_timezone'))->toDateString();

        if ($user->role === 'ADMIN') {
            return $this->adminDashboard($activeYear, $today);
        }

        return $this->waliDashboard($user, $activeYear, $today);
    }

    private function adminDashboard(?AcademicYear $year, string $today)
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

        return view('dashboard.admin', compact('stats', 'recentAttendance', 'year', 'today'));
    }

    private function waliDashboard($user, ?AcademicYear $year, string $today)
    {
        $classId = $user->class_id;

        $studentIds = StudentClassPlacement::where('school_class_id', $classId)
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

        $myClass = $user->schoolClass;

        return view('dashboard.wali', compact('stats', 'absentStudents', 'recentAttendance', 'myClass', 'year', 'today'));
    }
}
