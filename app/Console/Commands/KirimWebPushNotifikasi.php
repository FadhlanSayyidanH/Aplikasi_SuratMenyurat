<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SuratPushNotification;
use App\Services\SidebarMenuService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dijalankan scheduler tiap menit (lihat routes/console.php) -- padanan
 * SERVER-SIDE dari App\Livewire\Dashboard::deteksiNotifikasiBaru() (yang
 * cuma jalan selama tab browser terbuka & wire:poll.8s menembak). Sengaja
 * PAKAI ULANG App\Services\SidebarMenuService::notifikasiKeluar()/
 * notifikasiArsipKeluar()/notifikasiMasuk() (sumber tunggal "apa yang
 * dianggap notifikasi untuk user ini") persis seperti Dashboard.php,
 * supaya push (bar notifikasi HP, jalan meski browser tertutup) & popup
 * in-app SELALU menghasilkan event yang sama -- tidak ada logika kedua
 * yang bisa diam-diam berbeda.
 *
 * "Sudah pernah dilihat" disimpan per user di tabel push_notification_state
 * (bukan di properti Livewire yang cuma hidup di satu tab) supaya diff-nya
 * tahan lintas proses/lintas run command.
 */
#[Signature('app:kirim-web-push-notifikasi')]
#[Description('Kirim push notification untuk surat yang baru masuk notifikasi tiap user (dibandingkan run sebelumnya)')]
class KirimWebPushNotifikasi extends Command
{
    public function handle(): void
    {
        $userIds = DB::table('push_subscriptions')
            ->where('subscribable_type', User::class)
            ->distinct()
            ->pluck('subscribable_id');

        if ($userIds->isEmpty()) {
            return;
        }

        $users = User::query()->whereIn('id', $userIds)->get();
        $stateByUserId = DB::table('push_notification_state')
            ->whereIn('user_id', $userIds)->get()->keyBy('user_id');

        foreach ($users as $user) {
            try {
                $this->prosesUser($user, $stateByUserId->get($user->id));
            } catch (\Throwable $e) {
                // Satu user gagal (mis. layanan push down sesaat) TIDAK
                // boleh menghentikan pengiriman ke user lain dalam batch
                // yang sama.
                Log::warning('Gagal kirim web push untuk user #'.$user->id, ['error' => $e->getMessage()]);
            }
        }
    }

    private function prosesUser(User $user, ?object $state): void
    {
        $service = new SidebarMenuService($user);

        $perlu = $service->notifikasiKeluar();
        $arsip = $service->notifikasiArsipKeluar();
        $masuk = $service->notifikasiMasuk();

        $idPerlu = $perlu->pluck('id')->filter()->values()->all();
        $idArsip = $arsip->pluck('id')->filter()->values()->all();
        $idMasuk = $masuk->pluck('id')->filter()->values()->all();

        if ($state === null) {
            // Baseline pertama untuk user ini -- jangan kirim apa pun,
            // sama seperti "$prevPerlu === null" di Dashboard::deteksiNotifikasiBaru().
            DB::table('push_notification_state')->insert([
                'user_id' => $user->id,
                'id_perlu_ditindak' => json_encode($idPerlu),
                'id_arsip_keluar' => json_encode($idArsip),
                'id_masuk' => json_encode($idMasuk),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $prevPerlu = json_decode($state->id_perlu_ditindak, true) ?? [];
        $prevArsip = json_decode($state->id_arsip_keluar, true) ?? [];
        $prevMasuk = json_decode($state->id_masuk, true) ?? [];

        foreach ($perlu as $s) {
            if ($s->id && !in_array($s->id, $prevPerlu, true)) {
                $user->notify(new SuratPushNotification('Surat Keluar Perlu Ditindak', $s->perihal, $s->id));
            }
        }
        foreach ($arsip as $s) {
            if ($s->id && !in_array($s->id, $prevArsip, true)) {
                $judul = $s->status === 'disetujui' ? 'Surat Keluar Disetujui' : 'Surat Keluar Ditolak';
                $user->notify(new SuratPushNotification($judul, $s->perihal, $s->id));
            }
        }
        foreach ($masuk as $s) {
            if ($s->id && !in_array($s->id, $prevMasuk, true)) {
                $user->notify(new SuratPushNotification('Surat Masuk Diterima', $s->perihal, $s->id));
            }
        }

        DB::table('push_notification_state')->where('user_id', $user->id)->update([
            'id_perlu_ditindak' => json_encode($idPerlu),
            'id_arsip_keluar' => json_encode($idArsip),
            'id_masuk' => json_encode($idMasuk),
            'updated_at' => now(),
        ]);
    }
}
