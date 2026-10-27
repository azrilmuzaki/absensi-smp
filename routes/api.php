<?php

use App\Http\Controllers\Api\DeviceApiController;
use App\Http\Controllers\Api\VercelCronController;
use Illuminate\Support\Facades\Route;

Route::get('/cron/mark-alpha', [VercelCronController::class, 'markAlpha'])
    ->name('api.cron.mark-alpha');

// ── ESP32 Device API ───────────────────────────────────────────────────────
// Semua endpoint dilindungi middleware 'device' (API Key autentikasi).
// ESP32 wajib mengirimkan header: X-Device-Key: {api_key}
// ─────────────────────────────────────────────────────────────────────────

Route::middleware('device')->group(function () {

    // Proses tap kartu RFID dari ESP32
    Route::post('/tap', [DeviceApiController::class, 'tap']);

    // Sinkronisasi data siswa + RFID ke LittleFS ESP32
    Route::get('/sync', [DeviceApiController::class, 'sync']);

    // Laporan status perangkat (heartbeat)
    Route::post('/heartbeat', [DeviceApiController::class, 'heartbeat']);

    // Kirim batch absensi offline saat koneksi kembali
    Route::post('/offline-queue', [DeviceApiController::class, 'offlineQueue']);
});
