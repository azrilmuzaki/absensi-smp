<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Services\SchoolClassService;
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    public function __construct(private SchoolClassService $service) {}

    public function index(Request $request)
    {
        $activeYear = AcademicYear::where('is_active', true)->first();
        $yearId = $request->input('year_id', $activeYear?->id);
        $year = $yearId ? AcademicYear::findOrFail($yearId) : $activeYear;
        $allYears = AcademicYear::orderByDesc('start_date')->get();

        $classes = $year
            ? $this->service->forYear($year)
            : collect();

        return view('school-classes.index', compact('classes', 'year', 'allYears'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:20',
            'academic_year_id' => 'required|exists:academic_years,id',
        ]);

        $year = AcademicYear::findOrFail($data['academic_year_id']);
        $this->service->create($year, $data['name']);

        return redirect()->route('school-classes.index', ['year_id' => $year->id])
            ->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function update(Request $request, SchoolClass $schoolClass)
    {
        $data = $request->validate(['name' => 'required|string|max:20']);
        $this->service->update($schoolClass, $data['name']);

        return redirect()->route('school-classes.index', ['year_id' => $schoolClass->academic_year_id])
            ->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(SchoolClass $schoolClass)
    {
        try {
            $yearId = $schoolClass->academic_year_id;
            $this->service->delete($schoolClass);

            return redirect()->route('school-classes.index', ['year_id' => $yearId])
                ->with('success', 'Kelas berhasil dihapus.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
