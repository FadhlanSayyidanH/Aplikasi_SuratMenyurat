<?php

namespace Tests\Feature\Console;

use App\Models\Surat;
use App\Models\SuratApproval;
use App\Models\User;
use App\Notifications\SuratPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Cakupan test untuk App\Console\Commands\KirimWebPushNotifikasi -- padanan
 * server-side dari App\Livewire\Dashboard::deteksiNotifikasiBaru() (lihat
 * komentar lengkap di command-nya). Fokus: (1) run pertama untuk seorang
 * user cuma menyimpan baseline, TIDAK kirim apa pun, sama seperti popup
 * in-app tidak muncul di poll pertama; (2) run berikutnya cuma kirim untuk
 * surat yang BENAR-BENAR baru dibanding run sebelumnya, bukan re-kirim
 * ulang tiap kali command jalan.
 */
class KirimWebPushNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    private function buatUserDenganSubscription(string $nama): User
    {
        $user = User::create([
            'username' => 'user_'.str($nama)->slug(),
            'nama' => $nama,
            'role' => 'user',
            'password' => Hash::make('Password123'),
        ]);
        $user->updatePushSubscription(endpoint: 'https://fcm.example.test/'.$user->id, key: 'k', token: 't');

        return $user;
    }

    private function buatSuratMenungguTahap1(string $nama, string $perihal): Surat
    {
        $surat = Surat::create([
            'jenis' => 'keluar',
            'tanggal' => now()->toDateString(),
            'perihal' => $perihal,
            'klasifikasi' => 'Surat Keputusan',
            'status' => 'menunggu',
            'kabag_dituju' => 'Bag Uji',
        ]);
        SuratApproval::create([
            'surat_id' => $surat->id,
            'urutan' => 1,
            'role' => $nama,
            'status' => 'menunggu',
        ]);

        return $surat;
    }

    public function test_run_pertama_hanya_simpan_baseline_tanpa_kirim_notifikasi(): void
    {
        Notification::fake();
        $user = $this->buatUserDenganSubscription('Kaur Uji');
        $this->buatSuratMenungguTahap1('Kaur Uji', 'Surat pertama');

        $this->artisan('app:kirim-web-push-notifikasi')->assertExitCode(0);

        Notification::assertNothingSent();
        $this->assertDatabaseHas('push_notification_state', ['user_id' => $user->id]);
    }

    public function test_surat_baru_setelah_baseline_memicu_notifikasi(): void
    {
        Notification::fake();
        $user = $this->buatUserDenganSubscription('Kasi Uji');
        $this->buatSuratMenungguTahap1('Kasi Uji', 'Surat lama');

        $this->artisan('app:kirim-web-push-notifikasi'); // baseline, belum kirim

        $suratBaru = $this->buatSuratMenungguTahap1('Kasi Uji', 'Surat baru perlu ditindak');
        $this->artisan('app:kirim-web-push-notifikasi');

        Notification::assertSentTo(
            $user,
            SuratPushNotification::class,
            fn (SuratPushNotification $n) => $n->suratId() === $suratBaru->id
        );
        Notification::assertCount(1);
    }

    public function test_run_berulang_tanpa_perubahan_tidak_kirim_ulang(): void
    {
        Notification::fake();
        $this->buatUserDenganSubscription('Kabag Uji');
        $this->buatSuratMenungguTahap1('Kabag Uji', 'Surat statis');

        $this->artisan('app:kirim-web-push-notifikasi'); // baseline
        $this->artisan('app:kirim-web-push-notifikasi'); // sama persis, tidak ada yang baru
        $this->artisan('app:kirim-web-push-notifikasi'); // sama persis lagi

        Notification::assertNothingSent();
    }

    public function test_user_tanpa_subscription_dilewati(): void
    {
        Notification::fake();
        User::create([
            'username' => 'user_tanpa_sub',
            'nama' => 'Tanpa Subscription',
            'role' => 'user',
            'password' => Hash::make('Password123'),
        ]);
        $this->buatSuratMenungguTahap1('Tanpa Subscription', 'Surat apa saja');

        $this->artisan('app:kirim-web-push-notifikasi')->assertExitCode(0);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('push_notification_state', 0);
    }
}
