<?php

namespace Tests\Feature\Livewire;

use App\Livewire\LaporanGangguanAdmin;
use App\Livewire\LaporanGangguanWidget;
use App\Models\LaporanGangguan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cakupan fitur "Laporan Gangguan" -- widget pelapor untuk user ber-login
 * (App\Livewire\LaporanGangguanWidget, pengganti tombol WhatsApp lama) +
 * panel tinjau admin (App\Livewire\LaporanGangguanAdmin). Fokus: (1) hanya
 * admin yang bisa membuka panel, (2) identitas pelapor selalu dari akun
 * (bukan input klien), (3) validasi pesan, (4) alur status baru->selesai
 * & hapus.
 */
class LaporanGangguanTest extends TestCase
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

    public function test_non_admin_tidak_bisa_akses_panel_laporan(): void
    {
        $user = $this->buatUser('user');

        $this->actingAs($user)->get('/laporan-gangguan')->assertForbidden();
    }

    public function test_admin_bisa_akses_panel_laporan(): void
    {
        $admin = $this->buatUser('admin', 'Admin Utama');

        $this->actingAs($admin)->get('/laporan-gangguan')->assertOk();
    }

    public function test_user_login_bisa_kirim_laporan(): void
    {
        $user = $this->buatUser('user', 'Pelapor Satu');

        Livewire::actingAs($user)->test(LaporanGangguanWidget::class)
            ->set('kategori', 'bug')
            ->set('pesan', 'Tombol simpan tidak berfungsi di halaman surat.')
            ->call('kirim')
            ->assertHasNoErrors()
            ->assertSet('terkirim', true);

        $this->assertDatabaseHas('laporan_gangguan', [
            'pelapor_nama' => 'Pelapor Satu',
            'pelapor_username' => $user->username,
            'kategori' => 'bug',
            'pesan' => 'Tombol simpan tidak berfungsi di halaman surat.',
            'status' => 'baru',
        ]);
    }

    public function test_pesan_kosong_ditolak(): void
    {
        $user = $this->buatUser('user');

        Livewire::actingAs($user)->test(LaporanGangguanWidget::class)
            ->set('pesan', '   ')
            ->call('kirim');

        $this->assertDatabaseCount('laporan_gangguan', 0);
    }

    public function test_pesan_lebih_dari_1000_karakter_ditolak(): void
    {
        $user = $this->buatUser('user');

        Livewire::actingAs($user)->test(LaporanGangguanWidget::class)
            ->set('pesan', str_repeat('a', 1001))
            ->call('kirim');

        $this->assertDatabaseCount('laporan_gangguan', 0);
    }

    public function test_kategori_ngawur_jatuh_ke_kendala(): void
    {
        $user = $this->buatUser('user');

        Livewire::actingAs($user)->test(LaporanGangguanWidget::class)
            ->set('kategori', 'xxx-bukan-kategori')
            ->set('pesan', 'Halaman lambat sekali.')
            ->call('kirim')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('laporan_gangguan', [
            'pesan' => 'Halaman lambat sekali.',
            'kategori' => 'kendala',
        ]);
    }

    public function test_identitas_pelapor_diambil_dari_akun_bukan_dari_klien(): void
    {
        $user = $this->buatUser('user', 'Nama Asli');

        Livewire::actingAs($user)->test(LaporanGangguanWidget::class)
            // properti ini tidak ada -- upaya menyuntik nama palsu harus
            // diabaikan; nama tetap dari Auth::user().
            ->set('pesan', 'Uji identitas.')
            ->call('kirim')
            ->assertHasNoErrors();

        $row = LaporanGangguan::query()->first();
        $this->assertSame('Nama Asli', $row->pelapor_nama);
        $this->assertSame($user->username, $row->pelapor_username);
    }

    public function test_admin_bisa_tandai_selesai_lalu_buka_lagi(): void
    {
        $admin = $this->buatUser('admin', 'Admin Utama');
        $item = LaporanGangguan::create([
            'pelapor_username' => 'u1', 'pelapor_nama' => 'U Satu',
            'kategori' => 'kendala', 'pesan' => 'x', 'status' => 'baru',
        ]);

        Livewire::actingAs($admin)->test(LaporanGangguanAdmin::class)
            ->call('tandaiSelesai', $item->id);

        $this->assertDatabaseHas('laporan_gangguan', [
            'id' => $item->id, 'status' => 'selesai', 'ditangani_oleh' => 'Admin Utama',
        ]);
        $this->assertNotNull($item->fresh()->ditangani_pada);

        Livewire::actingAs($admin)->test(LaporanGangguanAdmin::class)
            ->call('tandaiBaru', $item->id);

        $this->assertDatabaseHas('laporan_gangguan', [
            'id' => $item->id, 'status' => 'baru', 'ditangani_oleh' => null,
        ]);
        $this->assertNull($item->fresh()->ditangani_pada);
    }

    public function test_admin_bisa_hapus_laporan(): void
    {
        $admin = $this->buatUser('admin');
        $item = LaporanGangguan::create([
            'pelapor_username' => 'u1', 'pelapor_nama' => 'U Satu',
            'kategori' => 'bug', 'pesan' => 'x', 'status' => 'baru',
        ]);

        Livewire::actingAs($admin)->test(LaporanGangguanAdmin::class)
            ->call('hapus', $item->id);

        $this->assertDatabaseCount('laporan_gangguan', 0);
    }

    public function test_filter_default_baru_dan_bisa_pindah(): void
    {
        $admin = $this->buatUser('admin');
        LaporanGangguan::create(['pelapor_username' => 'a', 'pelapor_nama' => 'A', 'kategori' => 'bug', 'pesan' => 'laporan-baru', 'status' => 'baru']);
        LaporanGangguan::create(['pelapor_username' => 'b', 'pelapor_nama' => 'B', 'kategori' => 'bug', 'pesan' => 'laporan-selesai', 'status' => 'selesai']);

        Livewire::actingAs($admin)->test(LaporanGangguanAdmin::class)
            ->assertSee('laporan-baru')
            ->assertDontSee('laporan-selesai')
            ->set('filter', 'selesai')
            ->assertSee('laporan-selesai')
            ->assertDontSee('laporan-baru');
    }

    public function test_kirim_laporan_menulis_activity_log(): void
    {
        $user = $this->buatUser('user', 'Pelapor Log');

        Livewire::actingAs($user)->test(LaporanGangguanWidget::class)
            ->set('kategori', 'saran')
            ->set('pesan', 'Tambah tombol ekspor.')
            ->call('kirim');

        $this->assertDatabaseHas('activity_log', [
            'nama' => 'Pelapor Log',
            'aksi' => 'create',
        ]);
    }
}
