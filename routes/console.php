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
