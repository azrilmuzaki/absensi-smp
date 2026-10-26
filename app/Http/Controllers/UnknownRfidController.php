<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\UnknownRfidLog;

class UnknownRfidController extends Controller
{
    public function index()
    {
        // Hanya Admin
        abort_unless(auth()->user()->role === 'ADMIN', 403);

        $logs = UnknownRfidLog::with('device')
            ->orderByDesc('created_at')
            ->paginate(50);

        // Ambil daftar siswa yang aktif tapi belum punya/memiliki kartu aktif
        // Ini berguna untuk quick-assign dari tabel Unknown RFID
        $students = Student::where('status', 'ACTIVE')
            ->whereDoesntHave('rfidCards', function ($query) {
                $query->where('status', 'ACTIVE');
            })
            ->orderBy('name')
            ->get();

        return view('rfid.unknown-rfids', compact('logs', 'students'));
    }

    public function destroy(UnknownRfidLog $unknownRfid)
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);
        $unknownRfid->delete();

        return back()->with('success', 'Log RFID Tidak Dikenal berhasil dihapus.');
    }

    public function clearAll()
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);
        UnknownRfidLog::truncate();

        return back()->with('success', 'Semua log RFID Tidak Dikenal berhasil dibersihkan.');
    }
}
