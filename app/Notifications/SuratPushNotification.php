<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Push notification (notification bar HP/desktop, jalan meski browser
 * tertutup) untuk satu event surat -- judul/perihal/warna PERSIS meniru
 * popup in-app di resources/views/layouts/app.blade.php (lihat event
 * 'notifikasi-baru'), supaya pesannya konsisten baik dilihat lewat popup
 * (tab terbuka) maupun lewat notification bar (tab tertutup).
 *
 * SENGAJA TIDAK ShouldQueue -- pengirimnya sendiri
 * (App\Console\Commands\KirimWebPushNotifikasi) sudah jalan di background
 * lewat scheduler tiap menit, bukan di request HTTP user, jadi kirim
 * sinkron di sini tidak memblokir siapa pun. Menghindari ketergantungan ke
 * queue worker yang MEMANG BELUM ada proses jalan permanen di VPS ini
 * (QUEUE_CONNECTION=database tapi tidak ada systemd/supervisor untuk
 * `queue:work` -- lihat catatan health check 2026-08-27).
 */
class SuratPushNotification extends Notification
{
    public function __construct(
        private readonly string $judul,
        private readonly string $perihal,
        private readonly int $suratId,
    ) {
    }

    public function suratId(): int
    {
        return $this->suratId;
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage())
            ->title($this->judul)
            ->icon('/images/logo_ajendam.png')
            ->body($this->perihal)
            ->tag('surat-'.$this->suratId)
            ->data(['url' => url('/surat/'.$this->suratId)])
            ->options(['TTL' => 3600]);
    }
}
