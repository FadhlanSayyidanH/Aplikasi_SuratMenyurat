<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\WebPush\Events\NotificationFailed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tanpa ini, subscription push yang gagal terkirim (device offline
        // lama/uninstall/endpoint kadaluarsa -- balasan 404/410 dari
        // FCM dkk) GAGAL SENYAP: paket NotificationChannels\WebPush cuma
        // dispatch event NotificationFailed, tidak pernah menulis apa pun
        // ke log dengan sendirinya. Ditemukan pas debug laporan user
        // "notif belum muncul di PC" 2026-08-28 -- ternyata salah satu
        // subscription-nya sudah 410 Gone, tapi laravel.log kosong sama
        // sekali soal itu sampai dicek manual lewat tinker.
        Event::listen(function (NotificationFailed $event) {
            Log::warning('Web push gagal terkirim', [
                'subscribable_id' => $event->subscription->subscribable_id,
                'endpoint' => $event->subscription->endpoint,
                'reason' => $event->report->getReason(),
                'status_code' => $event->report->getResponse()?->getStatusCode(),
            ]);
        });
    }
}
