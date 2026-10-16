<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cek payment expired setiap 5 menit
Schedule::command('payments:expire')->everyFiveMinutes();
// Expire reward points setiap tengah malam
Schedule::command('rewards:expire-points')->dailyAt('00:05');
// Bersihkan payment logs lama (> 90 hari) setiap minggu
Schedule::command('payments:prune-logs --days=90')->weekly();


// Admin Dashboard
Schedule::command('admin:generate-daily-report')->dailyAt('06:00');
// Bersihkan export file lama di S3 (> 7 hari)
Schedule::call(function () {
    $files = \Illuminate\Support\Facades\Storage::disk('s3')
        ->files('exports/admin');

    foreach ($files as $file) {
        $lastModified = \Illuminate\Support\Facades\Storage::disk('s3')->lastModified($file);
        if ($lastModified < now()->subDays(7)->timestamp) {
            \Illuminate\Support\Facades\Storage::disk('s3')->delete($file);
        }
    }
})->weekly()->sundays()->at('02:00');
