<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Push notification (bar notifikasi HP/desktop, jalan meski browser
// tertutup) -- lihat App\Console\Commands\KirimWebPushNotifikasi. Tiap
// menit cukup (bukan real-time, tapi sepadan dengan latensi wire:poll.8s
// yang sudah ada untuk popup in-app). Butuh cron `* * * * * php artisan
// schedule:run` terpasang di server (lihat deploy/).
Schedule::command('app:kirim-web-push-notifikasi')->everyMinute()->withoutOverlapping();
