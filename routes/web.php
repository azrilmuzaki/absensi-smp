<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceSettingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RfidCardController;
use App\Http\Controllers\SchoolCalendarController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UnknownRfidController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ── Auth ──────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ── Authenticated Routes ───────────────────────────────────────────────────
Route::middleware(['auth', 'active.user'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ── Absensi Harian & Laporan (Admin + Wali Kelas) ─────────────────────
    Route::middleware('role:ADMIN,WALI_KELAS')->group(function () {
        Route::get('/attendances', [AttendanceController::class, 'index'])->name('attendances.index');
        Route::post('/attendances/manual', [AttendanceController::class, 'storeManual'])->name('attendances.manual');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    // ── Data Siswa (Admin + Wali, authorization di controller) ────────────
    Route::middleware('role:ADMIN')->group(function () {
        Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
        Route::post('students', [StudentController::class, 'store'])->name('students.store');
    });

    Route::resource('students', StudentController::class)
        ->except(['destroy', 'create', 'store'])
        ->middleware('role:ADMIN,WALI_KELAS');

    Route::post('students/{student}/deactivate', [StudentController::class, 'deactivate'])
        ->name('students.deactivate')
        ->middleware('role:ADMIN');

    // ── ADMIN only ────────────────────────────────────────────────────────
    Route::middleware('role:ADMIN')->group(function () {

        // Kelas
        Route::resource('school-classes', SchoolClassController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // Tahun Ajaran
        Route::resource('academic-years', AcademicYearController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);
        Route::post('academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])
            ->name('academic-years.activate');

        // Naik Kelas
        Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
        Route::post('/promotions/preview', [PromotionController::class, 'preview'])->name('promotions.preview');
        Route::post('/promotions/execute', [PromotionController::class, 'execute'])->name('promotions.execute');

        // RFID
        Route::post('/students/{student}/rfid', [RfidCardController::class, 'assign'])->name('rfid.assign');
        Route::post('/rfid/{rfidCard}/revoke', [RfidCardController::class, 'revoke'])->name('rfid.revoke');
        Route::get('/unknown-rfids', [UnknownRfidController::class, 'index'])->name('unknown-rfids.index');
        Route::delete('/unknown-rfids/{unknownRfid}', [UnknownRfidController::class, 'destroy'])->name('unknown-rfids.destroy');
        Route::post('/unknown-rfids/clear-all', [UnknownRfidController::class, 'clearAll'])->name('unknown-rfids.clear');

        // Pengaturan Jam Absensi
        Route::get('/attendance-settings', [AttendanceSettingController::class, 'index'])->name('attendance-settings.index');
        Route::put('/attendance-settings', [AttendanceSettingController::class, 'update'])->name('attendance-settings.update');

        // Kalender Sekolah
        Route::get('/school-calendars', [SchoolCalendarController::class, 'index'])->name('school-calendars.index');
        Route::post('/school-calendars', [SchoolCalendarController::class, 'store'])->name('school-calendars.store');
        Route::delete('/school-calendars/{schoolCalendar}', [SchoolCalendarController::class, 'destroy'])->name('school-calendars.destroy');

        // Manajemen User
        Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::post('/users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');

        // Manajemen Perangkat ESP32
        Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
        Route::post('/devices/{device}/regenerate-key', [DeviceController::class, 'regenerateKey'])->name('devices.regenerate-key');
    });
});
