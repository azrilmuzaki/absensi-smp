<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceSetting;
use App\Models\Device;
use App\Models\RfidCard;
use App\Models\Student;
use App\Models\StudentClassPlacement;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class DeviceApiController extends Controller
{
    public function __construct(private AttendanceService $attendanceService) {}

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/tap
    // Dipanggil oleh ESP32 setiap kali ada kartu yang di-scan
    // ─────────────────────────────────────────────────────────────────────
    public function tap(Request $request)
    {
        $data = $request->validate([
            'uid' => 'required|string|max:50',
            'timestamp' => 'nullable|string', // ISO8601 dari RTC ESP32
            'idempotency_key' => 'nullable|string|max:100',
        ]);

        $device = $request->attributes->get('device');
        $apiKey = $request->header('X-Device-Key') ?? $request->input('api_key');

        $result = $this->attendanceService->processTap(array_merge($data, [
            'api_key' => $apiKey,
        ]));

        $httpCode = $result['success'] ? 200 : 422;

        return response()->json($result, $httpCode);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/sync
    // ESP32 memanggil endpoint ini untuk mengunduh cache siswa + RFID.
    // Hasilnya disimpan di LittleFS sebagai JSON lokal.
    // ─────────────────────────────────────────────────────────────────────
    public function sync(Request $request)
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        if (! $activeYear) {
            return response()->json([
                'success' => false,
                'code' => 'NO_ACTIVE_YEAR',
                'message' => 'Tidak ada tahun ajaran aktif.',
            ], 422);
        }

        // Ambil semua siswa aktif + RFID aktif mereka + kelas mereka
        $placements = StudentClassPlacement::with([
            'student.rfidCards' => fn ($q) => $q->where('status', 'ACTIVE'),
            'schoolClass',
        ])
            ->where('academic_year_id', $activeYear->id)
            ->whereHas('student', fn ($q) => $q->where('status', 'ACTIVE'))
            ->whereHas('student.rfidCards', fn ($q) => $q->where('status', 'ACTIVE'))
            ->get();

        // Susun format ringkas yang efisien untuk disimpan di LittleFS ESP32
        $payload = $placements->map(function ($p) {
            $rfid = $p->student->rfidCards->first();

            return [
                'uid' => $rfid->uid,
                'sid' => $p->student->id,
                'name' => $p->student->name,
                'class' => $p->schoolClass->name,
            ];
        })->values();

        // Ambil pengaturan waktu absensi
        $settings = AttendanceSetting::pluck('value', 'key');

        // Update device last_sync_at
        $device = $request->attributes->get('device');
        $device->update(['last_sync_at' => now()]);

        return response()->json([
            'success' => true,
            'synced_at' => now()->toIso8601String(),
            'academic_year' => $activeYear->name,
            'total_students' => $payload->count(),
            'students' => $payload,
            'settings' => [
                'jam_masuk' => $settings['jam_masuk'] ?? '06:00:00',
                'jam_terlambat' => $settings['jam_terlambat'] ?? '07:05:00',
                'batas_scan' => $settings['batas_scan'] ?? '10:00:00',
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/heartbeat
    // ESP32 mengirimkan sinyal hidup setiap beberapa menit.
    // Digunakan untuk monitoring status perangkat di dashboard.
    // ─────────────────────────────────────────────────────────────────────
    public function heartbeat(Request $request)
    {
        $device = $request->attributes->get('device');

        $device->update([
            'status' => 'ONLINE',
            'last_seen_at' => now(),
        ]);

        // Cek apakah ada sync terbaru yang perlu dilakukan
        // (jika ada perubahan data siswa sejak last_sync_at)
        $needsSync = false;
        if ($device->last_sync_at) {
            // Cek apakah ada rfid_cards atau students yang diubah setelah sync terakhir
            $recentChange = RfidCard::where('updated_at', '>', $device->last_sync_at)->exists()
                || Student::where('updated_at', '>', $device->last_sync_at)->exists();
            $needsSync = $recentChange;
        } else {
            $needsSync = true; // Belum pernah sync → minta sync sekarang
        }

        return response()->json([
            'success' => true,
            'server_time' => now()->toIso8601String(),
            'needs_sync' => $needsSync,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/offline-queue
    // ESP32 mengirimkan batch absensi yang tertunda saat koneksi kembali.
    // Setiap item diproses satu per satu; idempotency_key mencegah duplikat.
    // ─────────────────────────────────────────────────────────────────────
    public function offlineQueue(Request $request)
    {
        $data = $request->validate([
            'queue' => 'required|array|min:1|max:500',
            'queue.*.uid' => 'required|string',
            'queue.*.timestamp' => 'required|string',
            'queue.*.idempotency_key' => 'required|string',
        ]);

        $apiKey = $request->header('X-Device-Key') ?? $request->input('api_key');
        $results = [];

        foreach ($data['queue'] as $item) {
            $result = $this->attendanceService->processTap(array_merge($item, [
                'api_key' => $apiKey,
            ]));
            $results[] = [
                'idempotency_key' => $item['idempotency_key'],
                'uid' => $item['uid'],
                'code' => $result['code'],
                'success' => $result['success'],
            ];
        }

        $successCount = collect($results)->where('success', true)->count();

        return response()->json([
            // This indicates the batch was processed; each item has its own outcome.
            'success' => true,
            'processed' => count($results),
            'accepted' => $successCount,
            'results' => $results,
        ]);
    }
}
