<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

class SchoolClassService
{
    /**
     * Ambil semua kelas untuk satu tahun ajaran.
     */
    public function forYear(AcademicYear $year)
    {
        return SchoolClass::where('academic_year_id', $year->id)
            ->withCount('placements')
            ->orderBy('name')
            ->get();
    }

    /**
     * Buat kelas baru.
     */
    public function create(AcademicYear $year, string $name): SchoolClass
    {
        return DB::transaction(function () use ($year, $name) {
            $class = SchoolClass::create([
                'name' => strtoupper(trim($name)),
                'academic_year_id' => $year->id,
            ]);

            AuditLog::record('class.created', SchoolClass::class, $class->id, [], $class->toArray());

            return $class;
        });
    }

    /**
     * Update nama kelas.
     */
    public function update(SchoolClass $class, string $name): SchoolClass
    {
        return DB::transaction(function () use ($class, $name) {
            $old = $class->toArray();
            $class->update(['name' => strtoupper(trim($name))]);
            AuditLog::record('class.updated', SchoolClass::class, $class->id, $old, $class->fresh()->toArray());

            return $class->fresh();
        });
    }

    /**
     * Hapus kelas (hanya jika tidak ada siswa).
     */
    public function delete(SchoolClass $class): bool
    {
        if ($class->placements()->exists()) {
            throw new \RuntimeException('Kelas tidak dapat dihapus karena masih memiliki siswa.');
        }
        AuditLog::record('class.deleted', SchoolClass::class, $class->id, $class->toArray());

        return $class->delete();
    }
}
