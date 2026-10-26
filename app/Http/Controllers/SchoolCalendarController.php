<?php

namespace App\Http\Controllers;

use App\Models\SchoolCalendar;
use Illuminate\Http\Request;

class SchoolCalendarController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);

        $schoolTime = now(config('app.school_timezone'));
        $month = $request->input('month', $schoolTime->month);
        $year = $request->input('year', $schoolTime->year);

        $calendars = SchoolCalendar::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get();

        return view('school-calendars.index', compact('calendars', 'month', 'year'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);

        $data = $request->validate([
            'date' => 'required|date',
            'status' => 'required|in:HOLIDAY,ACTIVE',
            'description' => 'nullable|string|max:100',
        ]);

        SchoolCalendar::updateOrCreate(
            ['date' => $data['date']],
            ['status' => $data['status'], 'description' => $data['description']]
        );

        return back()->with('success', 'Kalender sekolah berhasil diperbarui.');
    }

    public function destroy(SchoolCalendar $schoolCalendar)
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);
        $schoolCalendar->delete();

        return back()->with('success', 'Data kalender berhasil dihapus.');
    }
}
