<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class AcademicYearService
{
    /**
     * Ambil semua tahun ajaran, terbaru di atas.
     */
    public function all()
    {
        return AcademicYear::orderByDesc('start_date')->get();
    }

    /**
     * Buat tahun ajaran baru.
     */
    public function create(array $data): AcademicYear
    {
        return DB::transaction(function () use ($data) {
            $year = AcademicYear::create([
                'name' => $data['name'],
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'is_active' => false,
            ]);

            AuditLog::record('academic_year.created', AcademicYear::class, $year->id, [], $year->toArray());

            return $year;
        });
    }

    /**
     * Update tahun ajaran.
     */
    public function update(AcademicYear $year, array $data): AcademicYear
    {
        return DB::transaction(function () use ($year, $data) {
            $old = $year->toArray();

            $year->update([
                'name' => $data['name'],
                'start_date' => $data['start_date'] ?? $year->start_date,
                'end_date' => $data['end_date'] ?? $year->end_date,
            ]);

            AuditLog::record('academic_year.updated', AcademicYear::class, $year->id, $old, $year->fresh()->toArray());

            return $year->fresh();
        });
    }

    /**
     * Aktifkan tahun ajaran. Hanya satu yang boleh aktif.
     */
    public function activate(AcademicYear $year): AcademicYear
    {
        return DB::transaction(function () use ($year) {
            // Nonaktifkan semua tahun ajaran lain
            AcademicYear::where('id', '!=', $year->id)->update(['is_active' => false]);

            $year->update(['is_active' => true]);

            AuditLog::record('academic_year.activated', AcademicYear::class, $year->id);

            return $year->fresh();
        });
    }
}
