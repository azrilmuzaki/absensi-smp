<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Services\AcademicYearService;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    public function __construct(private AcademicYearService $service) {}

    public function index()
    {
        $years = $this->service->all();

        return view('academic-years.index', compact('years'));
    }

    public function create()
    {
        return view('academic-years.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:20|unique:academic_years,name',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $this->service->create($data);

        return redirect()->route('academic-years.index')
            ->with('success', 'Tahun ajaran berhasil ditambahkan.');
    }

    public function edit(AcademicYear $academicYear)
    {
        return view('academic-years.edit', ['year' => $academicYear]);
    }

    public function update(Request $request, AcademicYear $academicYear)
    {
        abort_if($academicYear->is_active, 403, 'Tahun ajaran aktif tidak dapat diubah.');

        $data = $request->validate([
            'name' => 'required|string|max:20|unique:academic_years,name,'.$academicYear->id,
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $this->service->update($academicYear, $data);

        return redirect()->route('academic-years.index')
            ->with('success', 'Tahun ajaran berhasil diperbarui.');
    }

    public function activate(AcademicYear $academicYear)
    {
        $this->service->activate($academicYear);

        return redirect()->route('academic-years.index')
            ->with('success', "Tahun ajaran {$academicYear->name} diaktifkan.");
    }
}
