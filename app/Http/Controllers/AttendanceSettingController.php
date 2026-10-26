<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSetting;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AttendanceSettingController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);

        $settings = AttendanceSetting::all()->keyBy('key');

        return view('attendance-settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);

        $data = $request->validate([
            'jam_masuk' => 'required|date_format:H:i',
            'jam_terlambat' => 'required|date_format:H:i',
            'batas_scan' => 'required|date_format:H:i',
        ]);

        $oldSettings = AttendanceSetting::pluck('value', 'key')->toArray();

        foreach ($data as $key => $value) {
            AttendanceSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value.':00']
            );
        }

        AuditLog::record('settings.attendance_updated', null, null, $oldSettings, $data);

        return back()->with('success', 'Pengaturan waktu mesin absensi berhasil diperbarui.');
    }
}
