<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassPlacement;
use Illuminate\Support\Facades\DB;

class StudentService
{
    /**
     * Buat siswa baru dan tempatkan di kelas.
     */
    public function create(array $data, int $classId, int $academicYearId): Student
    {
        return DB::transaction(function () use ($data, $classId, $academicYearId) {
            $student = Student::create([
                'nis' => $data['nis'],
                'nisn' => $data['nisn'] ?? null,
                'name' => $data['name'],
                'gender' => $data['gender'],
                'birth_date' => $data['birth_date'] ?? null,
                'status' => 'ACTIVE',
            ]);

            StudentClassPlacement::create([
                'student_id' => $student->id,
                'school_class_id' => $classId,
                'academic_year_id' => $academicYearId,
            ]);

            AuditLog::record('student.created', Student::class, $student->id, [], $student->toArray());

            return $student;
        });
    }

    /**
     * Update data siswa.
     */
    public function update(Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data) {
            $old = $student->toArray();

            $student->update([
                'nis' => $data['nis'],
                'nisn' => $data['nisn'] ?? null,
                'name' => $data['name'],
                'gender' => $data['gender'],
                'birth_date' => $data['birth_date'] ?? null,
            ]);

            AuditLog::record('student.updated', Student::class, $student->id, $old, $student->fresh()->toArray());

            return $student->fresh();
        });
    }

    /**
     * Nonaktifkan siswa.
     */
    public function deactivate(Student $student, string $status = 'NONAKTIF'): Student
    {
        return DB::transaction(function () use ($student, $status) {
            $old = $student->toArray();
            $student->update(['status' => $status]);
            AuditLog::record('student.status_changed', Student::class, $student->id, $old, $student->fresh()->toArray());

            return $student->fresh();
        });
    }

    /**
     * Ambil daftar siswa aktif berdasar kelas dan tahun ajaran.
     */
    public function listByClass(SchoolClass $class, AcademicYear $year)
    {
        return Student::select('students.*')
            ->join('student_class_placements as scp', 'students.id', '=', 'scp.student_id')
            ->where('scp.school_class_id', $class->id)
            ->where('scp.academic_year_id', $year->id)
            ->where('students.status', 'ACTIVE')
            ->orderBy('students.name')
            ->get();
    }

    /**
     * Query list siswa dengan filter, untuk admin.
     */
    public function listWithFilters(array $filters = [])
    {
        $query = Student::with([
            'placements' => fn ($q) => $q->whereHas('academicYear', fn ($aq) => $aq->where('is_active', true))
                ->with(['schoolClass', 'academicYear']),
        ]);

        if (! empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(fn ($qb) => $qb->where('name', 'like', "%{$q}%")
                ->orWhere('nis', 'like', "%{$q}%")
                ->orWhere('nisn', 'like', "%{$q}%"));
        }

        if (! empty($filters['class_id'])) {
            $query->whereHas('placements', function ($qb) use ($filters) {
                $qb->where('school_class_id', $filters['class_id']);
                if (! empty($filters['academic_year_id'])) {
                    $qb->where('academic_year_id', $filters['academic_year_id']);
                }
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('name')->paginate(25);
    }
}
