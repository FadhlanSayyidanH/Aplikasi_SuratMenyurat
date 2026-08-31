<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Dashboard;
use App\Livewire\PengumumanAdmin;
use App\Models\Pengumuman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cakupan test untuk fitur pengumuman admin ke seluruh akun -- beda dari
 * notifikasi surat masuk/keluar (otomatis per-surat). Fokus: (1) hanya
 * admin yang bisa kirim/cabut, (2) SEMUA role melihat pengumuman aktif di
 * dashboard, (3) mencabut benar-benar menghapusnya dari tampilan semua
 * orang (bukan cuma disembunyikan untuk admin yang mencabut).
 */
class PengumumanTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role = 'user', string $nama = 'User Biasa'): User
    {
        return User::create([
            'username' => 'user_'.str()->random(8),
            'nama' => $nama,
            'role' => $role,
            'password' => Hash::make('Password123'),
        ]);
    }

    public function test_non_admin_tidak_bisa_akses_halaman_pengumuman(): void
    {
        $user = $this->buatUser('user');

        $this->actingAs($user)->get('/pengumuman')->assertForbidden();
    }

    public function test_admin_bisa_kirim_pengumuman(): void
    {
        $admin = $this->buatUser('admin', 'Admin Utama');

        Livewire::actingAs($admin)->test(PengumumanAdmin::class)
            ->set('pesan', 'Server akan maintenance malam ini pukul 22:00.')
            ->call('kirim')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pengumuman', [
            'pesan' => 'Server akan maintenance malam ini pukul 22:00.',
            'dibuat_oleh' => 'Admin Utama',
        ]);
    }

    public function test_pesan_kosong_ditolak(): void
    {
        $admin = $this->buatUser('admin');

        Livewire::actingAs($admin)->test(PengumumanAdmin::class)
            ->set('pesan', '   ')
            ->call('kirim');

        $this->assertDatabaseCount('pengumuman', 0);
    }

    public function test_admin_bisa_cabut_pengumuman(): void
    {
        $admin = $this->buatUser('admin');
        $item = Pengumuman::create(['pesan' => 'Uji coba', 'dibuat_oleh' => 'Admin']);

        Livewire::actingAs($admin)->test(PengumumanAdmin::class)
            ->call('cabut', $item->id);

        $this->assertDatabaseCount('pengumuman', 0);
    }

    public function test_pengumuman_aktif_tampil_di_dashboard_semua_role(): void
    {
        Pengumuman::create(['pesan' => 'Pengumuman untuk semua', 'dibuat_oleh' => 'Admin']);

        foreach (['admin', 'user', 'pimpinan', 'turmin'] as $role) {
            $user = $this->buatUser($role, 'Akun '.$role);

            Livewire::actingAs($user)->test(Dashboard::class)
                ->assertSee('Pengumuman untuk semua');
        }
    }

    public function test_pengumuman_yang_dicabut_hilang_dari_dashboard(): void
    {
        $item = Pengumuman::create(['pesan' => 'Akan dicabut', 'dibuat_oleh' => 'Admin']);
        $user = $this->buatUser('user');

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('Akan dicabut');

        $item->delete();

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertDontSee('Akan dicabut');
    }
}
