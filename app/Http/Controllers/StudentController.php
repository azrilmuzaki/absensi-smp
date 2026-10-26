<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function __construct(private StudentService $service) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $activeYear = AcademicYear::where('is_active', true)->first();

        // Wali hanya melihat kelasnya
        $filters = $request->only(['search', 'class_id', 'academic_year_id', 'status']);
        if ($user->role === 'WALI_KELAS') {
            $filters['class_id'] = $user->class_id;
            $filters['academic_year_id'] = $activeYear?->id;
        }

        $students = $this->service->listWithFilters($filters);
        $allYears = AcademicYear::orderByDesc('start_date')->get();
        $allClasses = $user->role === 'ADMIN'
            ? SchoolClass::where('academic_year_id', $activeYear?->id)->orderBy('name')->get()
            : collect();

        return view('students.index', compact('students', 'allYears', 'allClasses', 'activeYear'));
    }

    public function create()
    {
        abort_unless(Auth::user()->role === 'ADMIN', 403);

        $activeYear = AcademicYear::where('is_active', true)->first();
        $classes = $activeYear
            ? SchoolClass::where('academic_year_id', $activeYear->id)->orderBy('name')->get()
            : collect();

        return view('students.create', compact('classes', 'activeYear'));
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->role === 'ADMIN', 403);

        $yearData = $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
        ]);

        $data = $request->validate([
            'nis' => 'required|string|max:20|unique:students,nis',
            'nisn' => 'nullable|string|max:20|unique:students,nisn',
            'name' => 'required|string|max:100',
            'gender' => 'required|in:L,P',
            'birth_date' => 'nullable|date',
            'school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')->where('academic_year_id', $yearData['academic_year_id']),
            ],
        ]);
        $data['academic_year_id'] = $yearData['academic_year_id'];

        $this->service->create($data, $data['school_class_id'], $data['academic_year_id']);

        return redirect()->route('students.index', $request->query())
            ->with('success', 'Siswa berhasil ditambahkan.');
    }

    public function show(Student $student)
    {
        $this->authorizeStudentAccess($student);

        $student->load([
            'placements.schoolClass',
            'placements.academicYear',
            'rfidCards',
            'attendances' => fn ($q) => $q->orderByDesc('attendance_date')->limit(30),
        ]);

        return view('students.show', compact('student'));
    }

    public function edit(Student $student)
    {
        $this->authorizeStudentAccess($student);
        $activeYear = AcademicYear::where('is_active', true)->first();
        $classes = $activeYear
            ? SchoolClass::where('academic_year_id', $activeYear->id)->orderBy('name')->get()
            : collect();

        return view('students.edit', compact('student', 'classes', 'activeYear'));
    }

    public function update(Request $request, Student $student)
    {
        $this->authorizeStudentAccess($student);

        $data = $request->validate([
            'nis' => 'required|string|max:20|unique:students,nis,'.$student->id,
            'nisn' => 'nullable|string|max:20|unique:students,nisn,'.$student->id,
            'name' => 'required|string|max:100',
            'gender' => 'required|in:L,P',
            'birth_date' => 'nullable|date',
        ]);

        $this->service->update($student, $data);

        return redirect()->route('students.show', array_merge($request->query(), ['student' => $student]))
            ->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function deactivate(Request $request, Student $student)
    {
        $this->authorizeStudentAccess($student);

        $data = $request->validate([
            'status' => 'required|in:NONAKTIF,LULUS',
        ]);

        $this->service->deactivate($student, $data['status']);

        return redirect()->route('students.index', $request->query())
            ->with('success', "Siswa {$student->name} berhasil dinonaktifkan.");
    }

    /**
     * Wali hanya boleh mengakses siswa di kelasnya.
     */
    private function authorizeStudentAccess(Student $student): void
    {
        $user = Auth::user();
        if ($user->role === 'WALI_KELAS') {
            $activeYear = AcademicYear::where('is_active', true)->first();
            $inClass = $student->placements()
                ->where('school_class_id', $user->class_id)
                ->where('academic_year_id', $activeYear?->id)
                ->exists();

            abort_unless($inClass, 403, 'Akses ditolak.');
        }
    }
}
