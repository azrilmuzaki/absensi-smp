<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\StudentClassPlacement;
use Illuminate\Support\Facades\DB;

class PromotionService
{
    /**
     * Mapping kenaikan kelas. IX-x → LULUS.
     * Disesuaikan dengan nama kelas yang ada di database.
     */
    public function buildPromotionMap(AcademicYear $fromYear, AcademicYear $toYear): array
    {
        $fromClasses = SchoolClass::where('academic_year_id', $fromYear->id)
            ->with(['placements.student'])
            ->orderBy('name')
            ->get();

        $toClasses = SchoolClass::where('academic_year_id', $toYear->id)
            ->keyBy('name')
            ->all();

        $map = [];

        foreach ($fromClasses as $class) {
            $targetName = $this->resolveTargetClass($class->name);

            $students = $class->placements
                ->map(fn ($p) => $p->student)
                ->filter(fn ($student) => $student && $student->status === 'ACTIVE');

            $map[] = [
                'from_class' => $class,
                'to_class_name' => $targetName,
                'to_class' => $toClasses[$targetName] ?? null,
                'will_graduate' => str_starts_with(strtoupper($class->name), 'IX'),
                'students' => $students,
                'student_count' => $students->count(),
            ];
        }

        return $map;
    }

    /**
     * Jalankan mass promotion setelah konfirmasi admin.
     * Dibungkus dalam satu transaksi agar aman.
     */
    public function execute(AcademicYear $fromYear, AcademicYear $toYear): array
    {
        return DB::transaction(function () use ($fromYear, $toYear) {
            $promotionMap = $this->buildPromotionMap($fromYear, $toYear);

            $promoted = 0;
            $graduated = 0;
            $details = [];

            foreach ($promotionMap as $entry) {
                foreach ($entry['students'] as $student) {
                    if ($entry['will_graduate']) {
                        // Tandai siswa lulus
                        $student->update(['status' => 'LULUS']);
                        $graduated++;
                    } elseif ($entry['to_class']) {
                        // Buat placement baru di tahun ajaran baru
                        // Gunakan updateOrCreate agar idempotent
                        StudentClassPlacement::updateOrCreate(
                            [
                                'student_id' => $student->id,
                                'academic_year_id' => $toYear->id,
                            ],
                            [
                                'school_class_id' => $entry['to_class']->id,
                            ]
                        );
                        $promoted++;
                    }
                }

                $details[] = [
                    'from' => $entry['from_class']->name,
                    'to' => $entry['to_class_name'],
                    'count' => $entry['student_count'],
                    'graduate' => $entry['will_graduate'],
                ];
            }

            AuditLog::record('mass_promotion.executed', null, null, [
                'from_year' => $fromYear->name,
            ], [
                'to_year' => $toYear->name,
                'promoted' => $promoted,
                'graduated' => $graduated,
                'details' => $details,
            ]);

            return [
                'promoted' => $promoted,
                'graduated' => $graduated,
                'details' => $details,
            ];
        });
    }

    /**
     * Tentukan nama kelas tujuan dari nama kelas asal.
     * Contoh: VII-A → VIII-A, VIII-B → IX-B, IX-A → LULUS.
     */
    private function resolveTargetClass(string $className): string
    {
        // Pisahkan level dan suffix: "VII-A" → ["VII", "A"]
        $parts = explode('-', strtoupper(trim($className)), 2);
        $level = $parts[0] ?? '';
        $suffix = isset($parts[1]) ? '-'.$parts[1] : '';

        $nextLevel = match ($level) {
            'VII' => 'VIII',
            'VIII' => 'IX',
            'IX' => 'LULUS', // Akan di-handle sebagai LULUS
            default => $level,
        };

        return $nextLevel === 'LULUS' ? 'LULUS' : $nextLevel.$suffix;
    }
}
