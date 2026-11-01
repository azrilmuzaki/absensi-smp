<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\UnknownRfidLog;
use Illuminate\Http\JsonResponse;

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
        $students = Student::with('placements.schoolClass')
            ->where('status', 'ACTIVE')
            ->whereDoesntHave('rfidCards', function ($query) {
                $query->where('status', 'ACTIVE');
            })
            ->orderBy('name')
            ->get();

        return view('rfid.unknown-rfids', compact('logs', 'students'));
    }

    public function live(): JsonResponse
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);

        $logs = UnknownRfidLog::with('device')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $students = Student::with('placements.schoolClass')
            ->where('status', 'ACTIVE')
            ->whereDoesntHave('rfidCards', function ($query) {
                $query->where('status', 'ACTIVE');
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'total' => UnknownRfidLog::count(),
            'logs' => $logs->map(static function (UnknownRfidLog $log): array {
                return [
                    'id' => $log->id,
                    'uid' => $log->uid,
                    'created_at' => $log->created_at?->timezone(config('app.school_timezone'))->format('d M Y H:i:s'),
                    'device_name' => $log->device?->device_name ?? 'Unknown Device',
                ];
            })->values(),
            'students' => $students->map(static function (Student $student): array {
                $className = $student->placements->first()?->schoolClass?->name ?? 'Belum ada kelas';

                return [
                    'id' => $student->id,
                    'label' => "{$student->name} ({$className})",
                ];
            })->values(),
            'updated_at_label' => now(config('app.school_timezone'))->format('H:i:s'),
        ], 200, ['Cache-Control' => 'no-store, private']);
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
