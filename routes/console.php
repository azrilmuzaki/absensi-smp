<?php

use App\Console\Commands\MarkAbsentStudents;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tandai siswa tanpa catatan hadir pada pukul 10:15 waktu sekolah.
Schedule::command(MarkAbsentStudents::class)
    ->dailyAt('10:15')
    ->timezone(config('app.school_timezone'))
    ->name('mark-alpha-daily')
    ->withoutOverlapping();
