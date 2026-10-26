<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Services\PromotionService;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(private PromotionService $service) {}

    public function preview(Request $request)
    {
        $fromYear = AcademicYear::where('is_active', true)->firstOrFail();

        $data = $request->validate([
            'to_year_id' => 'required|exists:academic_years,id',
        ]);

        $toYear = AcademicYear::findOrFail($data['to_year_id']);

        abort_if($fromYear->id === $toYear->id, 422, 'Tahun tujuan tidak boleh sama dengan tahun aktif.');

        $map = $this->service->buildPromotionMap($fromYear, $toYear);
        $years = AcademicYear::orderByDesc('start_date')->get();

        return view('promotions.preview', compact('map', 'fromYear', 'toYear', 'years'));
    }

    public function execute(Request $request)
    {
        $data = $request->validate([
            'from_year_id' => 'required|exists:academic_years,id',
            'to_year_id' => 'required|exists:academic_years,id',
        ]);

        $fromYear = AcademicYear::findOrFail($data['from_year_id']);
        $toYear = AcademicYear::findOrFail($data['to_year_id']);

        abort_if($fromYear->id === $toYear->id, 422, 'Tahun tujuan tidak boleh sama dengan tahun aktif.');

        try {
            $result = $this->service->execute($fromYear, $toYear);

            return redirect()->route('promotions.index')
                ->with('success', "Mass promotion selesai: {$result['promoted']} naik kelas, {$result['graduated']} lulus.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Gagal: '.$e->getMessage()]);
        }
    }

    public function index()
    {
        $years = AcademicYear::orderByDesc('start_date')->get();

        return view('promotions.index', compact('years'));
    }
}
