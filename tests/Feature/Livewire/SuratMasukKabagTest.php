<?php

namespace Tests\Feature\Livewire;

use App\Models\BagDisposisiAnggota;
use App\Models\BagMasuk;
use App\Models\Surat;
use App\Models\SuratApproval;
use App\Models\SuratDisposisi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cakupan test untuk fitur Kabag di alur Surat Masuk (ditambahkan
 * 2026-08-29). Konsep: Turmin input (tidak berubah), Kasubditbinum
 * meneruskan ke Bag tujuan (TIDAK berubah -- masih App\Livewire\SuratReview::simpan()),
 * tapi kalau Bag itu SUDAH diatur Kabag-nya (bag_masuk.kabag_user_id),
 * surat berhenti dulu di Kabag alih-alih langsung diblast ke SELURUH
 * bag_disposisi_anggota seperti sebelumnya -- Kabag isi disposisi/catatan
 * (lewat mekanisme "Isi Disposisi" yang SUDAH ADA, tidak berubah) lalu
 * lewat checkbox BARU pilih anggota bag-nya sendiri yang dituju.
 *
 * Bag TANPA Kabag terdaftar (kabag_user_id null) HARUS tetap berperilaku
 * SAMA PERSIS seperti sebelum fitur ini ada (fallback wajib, bukan
 * opsional yang gampang lupa).
 */
class SuratMasukKabagTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $nama, string $role): User
    {
        return User::create([
            'username' => 'user_'.str()->slug($nama).'_'.str()->random(4),
            'nama' => $nama,
            'role' => $role,
            'password' => Hash::make('Password123'),
        ]);
    }

    /**
     * @return array{0: Surat, 1: BagMasuk, 2: User turmin, 3: User kasubdit, 4: User kabag, 5: User[] anggota}
     */
    private function setupSuratMenungguKasubdit(bool $denganKabag): array
    {
        $turmin = $this->buatUser('Turmin Uji', 'turmin');
        $kasubdit = $this->buatUser('Kasubdit Uji', 'pimpinan');
        $kabag = $this->buatUser('Kabag Uji', 'user');
        $anggota1 = $this->buatUser('Anggota Satu', 'user');
        $anggota2 = $this->buatUser('Anggota Dua', 'user');

        $bag = BagMasuk::create([
            'nama' => 'Bag Uji Kabag',
            'turmin_user_id' => $turmin->id,
            'kasubdit_user_id' => $kasubdit->id,
            'kabag_user_id' => $denganKabag ? $kabag->id : null,
        ]);

        BagDisposisiAnggota::create(['bag_id' => $bag->id, 'user_id' => $anggota1->id, 'urutan' => 1]);
        BagDisposisiAnggota::create(['bag_id' => $bag->id, 'user_id' => $anggota2->id, 'urutan' => 2]);

        $surat = Surat::create([
            'jenis' => 'masuk',
            'nomor_surat' => '001/UJI/2026',
            'tanggal' => now()->toDateString(),
            'tanggal_input_sistem' => now()->toDateString(),
            'perihal' => 'Surat uji Kabag',
            'nama_pengaju' => 'Pengirim Uji',
            'klasifikasi' => 'Surat Biasa',
            'status' => 'menunggu',
        ]);

        SuratApproval::create([
            'surat_id' => $surat->id, 'urutan' => 1, 'role' => 'Turmin',
            'status' => 'disetujui', 'diproses_oleh' => $turmin->nama, 'diproses_at' => now(),
        ]);
        SuratApproval::create([
            'surat_id' => $surat->id, 'urutan' => 2, 'role' => 'Kasubdit', 'status' => 'menunggu',
        ]);

        return [$surat, $bag, $turmin, $kasubdit, $kabag, [$anggota1, $anggota2]];
    }

    public function test_bag_dengan_kabag_hanya_membuat_disposisi_untuk_kabag(): void
    {
        [$surat, $bag, , $kasubdit, $kabag] = $this->setupSuratMenungguKasubdit(denganKabag: true);

        Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->set('bagTujuanTerpilih', [$bag->id])
            ->call('simpan');

        $roles = SuratDisposisi::where('surat_id', $surat->id)->pluck('role')->all();

        $this->assertSame([$kabag->nama], $roles, 'Bag ber-Kabag: hanya Kabag yang dapat disposisi, bukan seluruh anggota');
    }

    /**
     * Regresi 2026-08-29 (dilaporkan user): checkbox "Diteruskan Kepada" di
     * sisi Kasubdit tidak ikut pra-tercentang saat surat dibuka ulang,
     * padahal Bag itu SUDAH diteruskan sebelumnya -- KHUSUS utk Bag yang
     * punya Kabag. muatBagDisposisi() lama mengecek "sudah diteruskan"
     * dengan syarat SEMUA anggota_masuk sudah dapat disposisi (cocok utk
     * perilaku lama blast-ke-semua), tapi Bag ber-Kabag cuma menghasilkan
     * SATU baris disposisi (milik Kabag), jadi syarat itu tidak akan pernah
     * terpenuhi lagi sejak fitur Kabag ada.
     */
    public function test_checkbox_bag_kasubdit_pra_tercentang_lagi_untuk_bag_ber_kabag_yang_sudah_diteruskan(): void
    {
        [$surat, $bag, , $kasubdit, $kabag] = $this->setupSuratMenungguKasubdit(denganKabag: true);

        Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->set('bagTujuanTerpilih', [$bag->id])
            ->call('simpan');

        // Buka ulang halaman review sebagai Kasubdit (mis. dari daftar
        // "Riwayat" atau refresh) -- checkbox Bag harus tetap tercentang.
        $component = Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat->fresh()]);

        $this->assertContains($bag->id, $component->get('bagTujuanTerpilih'), 'Checkbox Bag ber-Kabag harus tetap pra-tercentang setelah surat yang sudah diteruskan dibuka ulang');
    }

    public function test_checkbox_bag_kasubdit_tidak_pra_tercentang_kalau_belum_pernah_diteruskan(): void
    {
        [$surat, $bag, , $kasubdit] = $this->setupSuratMenungguKasubdit(denganKabag: true);

        $component = Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat]);

        $this->assertNotContains($bag->id, $component->get('bagTujuanTerpilih'), 'Belum pernah diteruskan -- checkbox tidak boleh tercentang');
    }

    public function test_bag_tanpa_kabag_tetap_blast_ke_semua_anggota_seperti_sebelumnya(): void
    {
        [$surat, $bag, , $kasubdit, , $anggota] = $this->setupSuratMenungguKasubdit(denganKabag: false);

        Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->set('bagTujuanTerpilih', [$bag->id])
            ->call('simpan');

        $roles = SuratDisposisi::where('surat_id', $surat->id)->pluck('role')->sort()->values()->all();
        $namaAnggota = collect($anggota)->pluck('nama')->sort()->values()->all();

        $this->assertSame($namaAnggota, $roles, 'Bag tanpa Kabag: perilaku lama (blast ke semua anggota) harus tetap sama persis');
    }

    public function test_kabag_isi_catatan_dan_teruskan_ke_sebagian_anggota(): void
    {
        [$surat, $bag, , $kasubdit, $kabag, $anggota] = $this->setupSuratMenungguKasubdit(denganKabag: true);
        [$anggota1, $anggota2] = $anggota;

        Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->set('bagTujuanTerpilih', [$bag->id])
            ->call('simpan');

        Livewire::actingAs($kabag)->test(\App\Livewire\SuratReview::class, ['surat' => $surat->fresh()])
            ->set('disposisiCatatan.'.$kabag->nama, 'Tindak lanjuti segera, teruskan ke Anggota Satu saja.')
            ->set('kabagAnggotaTerpilih.'.$kabag->nama, [$anggota1->id])
            ->call('simpanDisposisi', $kabag->nama);

        $roles = SuratDisposisi::where('surat_id', $surat->id)->pluck('role')->sort()->values()->all();
        $this->assertSame([$anggota1->nama, $kabag->nama], $roles, 'Cuma Anggota Satu yang dicentang -- Anggota Dua TIDAK boleh ikut dapat disposisi');

        $kabagRow = SuratDisposisi::where('surat_id', $surat->id)->where('role', $kabag->nama)->first();
        $this->assertSame('Tindak lanjuti segera, teruskan ke Anggota Satu saja.', $kabagRow->catatan);

        $anggota1Row = SuratDisposisi::where('surat_id', $surat->id)->where('role', $anggota1->nama)->first();
        $this->assertNull($anggota1Row->catatan, 'Anggota yang baru diteruskan belum mengisi responsnya sendiri');

        $suratFresh = Surat::find($surat->id);
        $this->assertStringContainsString($anggota1->nama, (string) $suratFresh->disposisi, 'Kolom disposisi (string) ikut diperbarui supaya konsisten dengan sebelumnya');
    }

    public function test_kabag_bisa_simpan_catatan_tanpa_meneruskan_ke_siapa_pun(): void
    {
        [$surat, $bag, , $kasubdit, $kabag] = $this->setupSuratMenungguKasubdit(denganKabag: true);

        Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->set('bagTujuanTerpilih', [$bag->id])
            ->call('simpan');

        $component = Livewire::actingAs($kabag)->test(\App\Livewire\SuratReview::class, ['surat' => $surat->fresh()])
            ->set('disposisiCatatan.'.$kabag->nama, 'Cukup dicatat, tidak perlu diteruskan.')
            ->call('simpanDisposisi', $kabag->nama);

        $this->assertNull($component->get('errorRole')[$kabag->nama] ?? null, 'Kabag harus bisa mengisi disposisinya sendiri tanpa error');
        $kabagRow = SuratDisposisi::where('surat_id', $surat->id)->where('role', $kabag->nama)->first();
        $this->assertSame('Cukup dicatat, tidak perlu diteruskan.', $kabagRow->catatan, 'Catatan Kabag sendiri harus benar-benar tersimpan');
        $this->assertSame(1, SuratDisposisi::where('surat_id', $surat->id)->count(), 'Tidak boleh ada baris disposisi tambahan kalau tidak ada checkbox dicentang');
    }

    public function test_anggota_yang_diteruskan_kabag_bisa_mengakses_surat(): void
    {
        [$surat, $bag, , $kasubdit, $kabag, $anggota] = $this->setupSuratMenungguKasubdit(denganKabag: true);
        [$anggota1] = $anggota;

        Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->set('bagTujuanTerpilih', [$bag->id])
            ->call('simpan');

        Livewire::actingAs($kabag)->test(\App\Livewire\SuratReview::class, ['surat' => $surat->fresh()])
            ->set('disposisiCatatan.'.$kabag->nama, 'Diteruskan.')
            ->set('kabagAnggotaTerpilih.'.$kabag->nama, [$anggota1->id])
            ->call('simpanDisposisi', $kabag->nama);

        Livewire::actingAs($anggota1)->test(\App\Livewire\SuratReview::class, ['surat' => $surat->fresh()])
            ->assertOk();
    }

    /**
     * Regresi 2026-08-29: akun Kabag bisa saja SUDAH terdaftar sebagai
     * bag_disposisi_anggota di bag-nya sendiri sejak sebelum fitur ini ada
     * (dulu Kasubdit blast ke semua anggota termasuk Kabag). Nama sendiri
     * TIDAK boleh muncul di daftar "teruskan ke" -- ditemukan live saat
     * kabagtestpers (yang juga anggota bag "bagtest" sendiri) melihat
     * checkbox namanya sendiri ikut ter-centang otomatis.
     */
    public function test_kabag_yang_juga_anggota_bag_sendiri_tidak_muncul_di_daftar_teruskan_ke(): void
    {
        [$surat, $bag, , $kasubdit, $kabag, $anggota] = $this->setupSuratMenungguKasubdit(denganKabag: true);
        [$anggota1] = $anggota;
        BagDisposisiAnggota::create(['bag_id' => $bag->id, 'user_id' => $kabag->id, 'urutan' => 0]);

        Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->set('bagTujuanTerpilih', [$bag->id])
            ->call('simpan');

        $component = Livewire::actingAs($kabag)->test(\App\Livewire\SuratReview::class, ['surat' => $surat->fresh()]);

        $namaAnggotaMasuk = collect($component->instance()->kabagInfoUntuk($kabag->nama)['anggota_masuk'])->pluck('nama')->all();
        $this->assertNotContains($kabag->nama, $namaAnggotaMasuk, 'Kabag tidak boleh bisa meneruskan surat ke dirinya sendiri');
        $this->assertContains($anggota1->nama, $namaAnggotaMasuk, 'Anggota lain tetap harus muncul');

        $this->assertSame([], $component->get('kabagAnggotaTerpilih')[$kabag->nama] ?? [], 'Checkbox nama sendiri tidak boleh ter-pra-centang');
    }

    public function test_simpan_ulang_kabag_tidak_membuat_duplikat_saat_checkbox_sama(): void
    {
        [$surat, $bag, , $kasubdit, $kabag, $anggota] = $this->setupSuratMenungguKasubdit(denganKabag: true);
        [$anggota1] = $anggota;

        Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->set('bagTujuanTerpilih', [$bag->id])
            ->call('simpan');

        for ($i = 0; $i < 2; $i++) {
            Livewire::actingAs($kabag)->test(\App\Livewire\SuratReview::class, ['surat' => $surat->fresh()])
                ->set('disposisiCatatan.'.$kabag->nama, 'Diteruskan ke-'.$i)
                ->set('kabagAnggotaTerpilih.'.$kabag->nama, [$anggota1->id])
                ->call('simpanDisposisi', $kabag->nama);
        }

        $this->assertSame(1, SuratDisposisi::where('surat_id', $surat->id)->where('role', $anggota1->nama)->count(), 'Simpan berulang dengan checkbox yang sama tidak boleh menggandakan baris disposisi');
    }
}
