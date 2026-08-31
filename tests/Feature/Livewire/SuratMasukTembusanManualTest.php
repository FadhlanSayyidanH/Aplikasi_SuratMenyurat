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
 * Cakupan test untuk fitur Tembusan Manual di alur Surat Masuk (ditambahkan
 * 2026-08-30, diminta user: "sama seperti di surat keluar hanya bedanya ini
 * untuk surat masuk, jadi akun yang dipilih bisa menerima dan mengisi
 * disposisinya"). BUKAN rantai approval berurutan seperti
 * MengelolaRantaiSuratKeluar -- Kasubdit tetap bisa pilih Bag seperti
 * biasa, tembusan manual cuma tambahan akun BEBAS di luar struktur Bag,
 * paralel/independen (bukan bergiliran), lewat mekanisme "Isi Disposisi"
 * yang SUDAH ADA (tidak berubah).
 */
class SuratMasukTembusanManualTest extends TestCase
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
     * @return array{0: Surat, 1: BagMasuk, 2: User turmin, 3: User kasubdit, 4: User[] anggota}
     */
    private function setupSuratMenungguKasubdit(): array
    {
        $turmin = $this->buatUser('Turmin Uji', 'turmin');
        $kasubdit = $this->buatUser('Kasubdit Uji', 'pimpinan');
        $anggota1 = $this->buatUser('Anggota Satu', 'user');
        $anggota2 = $this->buatUser('Anggota Dua', 'user');

        $bag = BagMasuk::create([
            'nama' => 'Bag Uji Tembusan',
            'turmin_user_id' => $turmin->id,
            'kasubdit_user_id' => $kasubdit->id,
        ]);

        BagDisposisiAnggota::create(['bag_id' => $bag->id, 'user_id' => $anggota1->id, 'urutan' => 1]);
        BagDisposisiAnggota::create(['bag_id' => $bag->id, 'user_id' => $anggota2->id, 'urutan' => 2]);

        $surat = Surat::create([
            'jenis' => 'masuk',
            'nomor_surat' => '001/UJI-TEMBUSAN/2026',
            'tanggal' => now()->toDateString(),
            'tanggal_input_sistem' => now()->toDateString(),
            'perihal' => 'Surat uji Tembusan Manual',
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

        return [$surat, $bag, $turmin, $kasubdit, [$anggota1, $anggota2]];
    }

    public function test_kasubdit_bisa_tambah_tembusan_manual_tanpa_pilih_bag_sama_sekali(): void
    {
        [$surat, , , $kasubdit] = $this->setupSuratMenungguKasubdit();
        $luar = $this->buatUser('Pejabat Luar', 'user');

        Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->call('pilihUserTembusan', $luar->id)
            ->call('simpan');

        $roles = SuratDisposisi::where('surat_id', $surat->id)->pluck('role')->all();
        $this->assertSame([$luar->nama], $roles, 'Tembusan manual harus jadi target disposisi walau tidak ada Bag dipilih sama sekali');
    }

    public function test_tembusan_manual_bekerja_bareng_bag_biasa(): void
    {
        [$surat, $bag, , $kasubdit, $anggota] = $this->setupSuratMenungguKasubdit();
        $luar = $this->buatUser('Pejabat Luar', 'user');

        Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->set('bagTujuanTerpilih', [$bag->id])
            ->call('pilihUserTembusan', $luar->id)
            ->call('simpan');

        $roles = SuratDisposisi::where('surat_id', $surat->id)->pluck('role')->sort()->values()->all();
        $expected = collect([...$anggota, $luar])->pluck('nama')->sort()->values()->all();
        $this->assertSame($expected, $roles, 'Anggota Bag DAN tembusan manual harus sama-sama jadi target disposisi');
    }

    public function test_tembusan_manual_bisa_isi_disposisinya_sendiri(): void
    {
        [$surat, , , $kasubdit] = $this->setupSuratMenungguKasubdit();
        $luar = $this->buatUser('Pejabat Luar', 'user');

        Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->call('pilihUserTembusan', $luar->id)
            ->call('simpan');

        $component = Livewire::actingAs($luar)->test(\App\Livewire\SuratReview::class, ['surat' => $surat->fresh()])
            ->set('disposisiCatatan.'.$luar->nama, 'Diterima, akan ditindaklanjuti.')
            ->call('simpanDisposisi', $luar->nama);

        $this->assertNull($component->get('errorRole')[$luar->nama] ?? null, 'Akun tembusan manual harus bisa mengisi disposisinya sendiri tanpa error');
        $row = SuratDisposisi::where('surat_id', $surat->id)->where('role', $luar->nama)->first();
        $this->assertSame('Diterima, akan ditindaklanjuti.', $row->catatan);
    }

    public function test_tembusan_manual_pra_terisi_saat_surat_dibuka_ulang(): void
    {
        [$surat, $bag, , $kasubdit] = $this->setupSuratMenungguKasubdit();
        $luar = $this->buatUser('Pejabat Luar', 'user');

        Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->set('bagTujuanTerpilih', [$bag->id])
            ->call('pilihUserTembusan', $luar->id)
            ->call('simpan');

        $component = Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat->fresh()]);

        $tembusan = collect($component->get('tembusanManualTerpilih'))->pluck('nama')->all();
        $this->assertSame([$luar->nama], $tembusan, 'Tembusan manual harus tetap terlihat saat surat dibuka ulang, tidak boleh hilang begitu saja');
        $this->assertContains($bag->id, $component->get('bagTujuanTerpilih'), 'Checkbox Bag biasa juga tidak boleh terganggu oleh fitur tembusan');
    }

    public function test_simpan_ulang_tidak_menghilangkan_tembusan_manual_yang_sudah_ada(): void
    {
        [$surat, , , $kasubdit] = $this->setupSuratMenungguKasubdit();
        $luar = $this->buatUser('Pejabat Luar', 'user');

        Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->call('pilihUserTembusan', $luar->id)
            ->call('simpan');

        // Buka ulang & simpan lagi TANPA secara eksplisit memilih ulang --
        // properti harus sudah pra-terisi dari mount(), jadi simpan ulang
        // tidak boleh diam-diam membuang tembusan yang sudah ada
        // (simpan() membangun ulang $disposisiList dari nol tiap panggilan).
        Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat->fresh()])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->call('simpan');

        $roles = SuratDisposisi::where('surat_id', $surat->id)->pluck('role')->all();
        $this->assertSame([$luar->nama], $roles, 'Simpan ulang tanpa mengubah apa pun tidak boleh menghilangkan tembusan manual yang sudah tersimpan');
    }

    public function test_pencarian_tembusan_tidak_menampilkan_admin_dan_turmin(): void
    {
        [$surat, , , $kasubdit] = $this->setupSuratMenungguKasubdit();
        $this->buatUser('Admin Uji Zzz', 'admin');
        $this->buatUser('Turmin Lain Zzz', 'turmin');
        $target = $this->buatUser('Pejabat Zzz', 'user');

        $component = Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->set('cariUserTembusan', 'Zzz');

        $nama = collect($component->get('opsiUserTembusan'))->pluck('nama')->all();
        $this->assertSame([$target->nama], $nama, 'Admin dan Turmin tidak boleh muncul sebagai opsi tembusan manual');
    }

    public function test_pilih_user_tembusan_yang_sama_dua_kali_tidak_duplikat(): void
    {
        [$surat, , , $kasubdit] = $this->setupSuratMenungguKasubdit();
        $luar = $this->buatUser('Pejabat Luar', 'user');

        $component = Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->call('pilihUserTembusan', $luar->id)
            ->call('pilihUserTembusan', $luar->id);

        $this->assertCount(1, $component->get('tembusanManualTerpilih'), 'Memilih akun yang sama dua kali tidak boleh menggandakan entri');
    }

    public function test_hapus_dari_tembusan_manual(): void
    {
        [$surat, , , $kasubdit] = $this->setupSuratMenungguKasubdit();
        $luar1 = $this->buatUser('Pejabat Satu', 'user');
        $luar2 = $this->buatUser('Pejabat Dua', 'user');

        $component = Livewire::actingAs($kasubdit)->test(\App\Livewire\SuratReview::class, ['surat' => $surat])
            ->call('pilihUserTembusan', $luar1->id)
            ->call('pilihUserTembusan', $luar2->id)
            ->call('hapusDariTembusanManual', 0);

        $sisa = collect($component->get('tembusanManualTerpilih'))->pluck('nama')->all();
        $this->assertSame([$luar2->nama], $sisa);
    }
}
