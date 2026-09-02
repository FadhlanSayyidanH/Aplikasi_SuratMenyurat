<?php

namespace Tests\Feature\Livewire;

use App\Livewire\SuratReview;
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
 * Fitur "teruskan ke rekan sebag" (2026-09-02): sesama Penerima Disposisi
 * (bag_disposisi_anggota) satu Bag bisa saling meneruskan Surat Masuk.
 * Beda dari alur Kabag:
 *  - target hanya sesama Penerima Disposisi Bag yang sama (Kabag TIDAK termasuk)
 *  - seorang anggota hanya boleh MEMBATALKAN terusan yang DIA sendiri buat
 *    (surat_disposisi.ditambah_oleh) dan yang penerimanya belum merespon
 */
class SuratMasukTeruskanRekanTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $nama, string $role = 'user'): User
    {
        return User::create([
            'username' => 'user_'.str()->slug($nama).'_'.str()->random(4),
            'nama' => $nama,
            'role' => $role,
            'password' => Hash::make('Password123'),
        ]);
    }

    /**
     * Bag ber-Kabag + 3 Penerima Disposisi (A, B, C). Surat sudah diteruskan
     * Kasubdit ke Bag (baris Kabag), lalu Kabag meneruskan ke A -> A punya
     * kartu disposisi & jadi "aktor".
     *
     * @return array{0:Surat,1:BagMasuk,2:User kabag,3:User A,4:User B,5:User C}
     */
    private function bikinSkenario(): array
    {
        $turmin = $this->buatUser('Turmin Uji', 'turmin');
        $kasubdit = $this->buatUser('Kasubdit Uji', 'pimpinan');
        $kabag = $this->buatUser('Kabag Uji');
        $a = $this->buatUser('Anggota A');
        $b = $this->buatUser('Anggota B');
        $c = $this->buatUser('Anggota C');

        $bag = BagMasuk::create([
            'nama' => 'Bag Rekan Uji',
            'turmin_user_id' => $turmin->id,
            'kasubdit_user_id' => $kasubdit->id,
            'kabag_user_id' => $kabag->id,
        ]);
        foreach ([$a, $b, $c] as $i => $u) {
            BagDisposisiAnggota::create(['bag_id' => $bag->id, 'user_id' => $u->id, 'urutan' => $i + 1]);
        }

        $surat = Surat::create([
            'jenis' => 'masuk', 'nomor_surat' => '007/UJI/2026',
            'tanggal' => now()->toDateString(), 'tanggal_input_sistem' => now()->toDateString(),
            'perihal' => 'Uji teruskan rekan', 'nama_pengaju' => 'Pengirim',
            'klasifikasi' => 'Surat Biasa', 'status' => 'menunggu',
        ]);
        SuratApproval::create(['surat_id' => $surat->id, 'urutan' => 1, 'role' => 'Turmin', 'status' => 'disetujui', 'diproses_oleh' => $turmin->nama, 'diproses_at' => now()]);
        SuratApproval::create(['surat_id' => $surat->id, 'urutan' => 2, 'role' => 'Kasubdit', 'status' => 'menunggu']);

        Livewire::actingAs($kasubdit)->test(SuratReview::class, ['surat' => $surat])
            ->set('instruksiDisposisi', ['tindak_lanjuti'])
            ->set('bagTujuanTerpilih', [$bag->id])
            ->call('simpan');

        Livewire::actingAs($kabag)->test(SuratReview::class, ['surat' => $surat->fresh()])
            ->set('disposisiCatatan.'.$kabag->nama, 'Teruskan ke Anggota A.')
            ->set('kabagAnggotaTerpilih.'.$kabag->nama, [$a->id])
            ->call('simpanDisposisi', $kabag->nama);

        return [$surat->fresh(), $bag, $kabag, $a, $b, $c];
    }

    public function test_anggota_meneruskan_ke_rekan_sebag(): void
    {
        [$surat, , , $a, $b] = $this->bikinSkenario();

        Livewire::actingAs($a)->test(SuratReview::class, ['surat' => $surat])
            ->set('disposisiCatatan.'.$a->nama, 'Minta bantuan Anggota B.')
            ->set('rekanSebagTerpilih.'.$a->nama, [$b->id])
            ->call('simpanDisposisi', $a->nama);

        $rowB = SuratDisposisi::where('surat_id', $surat->id)->where('role', $b->nama)->first();
        $this->assertNotNull($rowB, 'Baris disposisi untuk Anggota B harus dibuat');
        $this->assertSame($a->nama, $rowB->ditambah_oleh, 'ditambah_oleh harus mencatat siapa yang meneruskan');
        $this->assertStringContainsString($b->nama, (string) Surat::find($surat->id)->disposisi);

        // B bisa membuka surat.
        Livewire::actingAs($b)->test(SuratReview::class, ['surat' => $surat->fresh()])->assertOk();
    }

    public function test_tidak_bisa_meneruskan_ke_akun_di_luar_penerima_disposisi_bag(): void
    {
        [$surat, , , $a] = $this->bikinSkenario();
        $orangLuar = $this->buatUser('Orang Luar Bag');

        Livewire::actingAs($a)->test(SuratReview::class, ['surat' => $surat])
            ->set('disposisiCatatan.'.$a->nama, 'Coba teruskan ke orang luar.')
            ->set('rekanSebagTerpilih.'.$a->nama, [$orangLuar->id])
            ->call('simpanDisposisi', $a->nama);

        $this->assertNull(
            SuratDisposisi::where('surat_id', $surat->id)->where('role', $orangLuar->nama)->first(),
            'Akun di luar Penerima Disposisi Bag tidak boleh dapat baris disposisi',
        );
    }

    public function test_tidak_bisa_meneruskan_ke_kabag(): void
    {
        [$surat, , $kabag, $a] = $this->bikinSkenario();

        Livewire::actingAs($a)->test(SuratReview::class, ['surat' => $surat])
            ->set('disposisiCatatan.'.$a->nama, 'Coba teruskan balik ke Kabag.')
            ->set('rekanSebagTerpilih.'.$a->nama, [$kabag->id])
            ->call('simpanDisposisi', $a->nama);

        // Kabag memang sudah punya baris (dari alur Kabag), tapi tidak boleh
        // muncul sebagai opsi rekan & tidak berubah ditambah_oleh-nya.
        $rowKabag = SuratDisposisi::where('surat_id', $surat->id)->where('role', $kabag->nama)->first();
        $this->assertNotSame($a->nama, $rowKabag->ditambah_oleh ?? null);

        $rekanInfo = Livewire::actingAs($a)->test(SuratReview::class, ['surat' => $surat->fresh()])
            ->instance()->rekanSebagUntuk($a->nama);
        $namaRekan = collect($rekanInfo['anggota'])->pluck('nama')->all();
        $this->assertNotContains($kabag->nama, $namaRekan, 'Kabag tidak boleh muncul di daftar rekan sebag');
        $this->assertNotContains($a->nama, $namaRekan, 'Diri sendiri tidak boleh muncul');
    }

    public function test_anggota_bisa_membatalkan_terusan_yang_dia_buat_sendiri(): void
    {
        [$surat, , , $a, $b] = $this->bikinSkenario();

        Livewire::actingAs($a)->test(SuratReview::class, ['surat' => $surat])
            ->set('disposisiCatatan.'.$a->nama, 'Teruskan ke B.')
            ->set('rekanSebagTerpilih.'.$a->nama, [$b->id])
            ->call('simpanDisposisi', $a->nama);
        $this->assertNotNull(SuratDisposisi::where('surat_id', $surat->id)->where('role', $b->nama)->first());

        // A buka lagi & uncheck B.
        Livewire::actingAs($a)->test(SuratReview::class, ['surat' => $surat->fresh()])
            ->set('disposisiCatatan.'.$a->nama, 'Tidak jadi.')
            ->set('rekanSebagTerpilih.'.$a->nama, [])
            ->call('simpanDisposisi', $a->nama);

        $this->assertNull(
            SuratDisposisi::where('surat_id', $surat->id)->where('role', $b->nama)->first(),
            'A boleh membatalkan terusan yang dia sendiri buat (B belum merespon)',
        );
        $this->assertStringNotContainsString($b->nama, (string) Surat::find($surat->id)->disposisi);
    }

    public function test_anggota_tidak_bisa_membatalkan_terusan_dari_kabag(): void
    {
        [$surat, , $kabag, $a, , $c] = $this->bikinSkenario();

        // Kabag juga meneruskan ke C (ditambah_oleh = Kabag). A sudah dapat
        // dari Kabag di bikinSkenario().
        Livewire::actingAs($kabag)->test(SuratReview::class, ['surat' => $surat->fresh()])
            ->set('disposisiCatatan.'.$kabag->nama, 'Teruskan ke A dan C.')
            ->set('kabagAnggotaTerpilih.'.$kabag->nama, [$a->id, $c->id])
            ->call('simpanDisposisi', $kabag->nama);

        $rowC = SuratDisposisi::where('surat_id', $surat->id)->where('role', $c->nama)->first();
        $this->assertSame($kabag->nama, $rowC->ditambah_oleh);

        // A mencoba meng-uncheck C (yang ditambahkan Kabag).
        Livewire::actingAs($a)->test(SuratReview::class, ['surat' => $surat->fresh()])
            ->set('disposisiCatatan.'.$a->nama, 'Coba batalkan C.')
            ->set('rekanSebagTerpilih.'.$a->nama, [])
            ->call('simpanDisposisi', $a->nama);

        $this->assertNotNull(
            SuratDisposisi::where('surat_id', $surat->id)->where('role', $c->nama)->first(),
            'Anggota tidak boleh membatalkan terusan yang dibuat Kabag',
        );
    }

    public function test_anggota_tidak_bisa_membatalkan_rekan_yang_sudah_merespon(): void
    {
        [$surat, , , $a, $b] = $this->bikinSkenario();

        Livewire::actingAs($a)->test(SuratReview::class, ['surat' => $surat])
            ->set('disposisiCatatan.'.$a->nama, 'Teruskan ke B.')
            ->set('rekanSebagTerpilih.'.$a->nama, [$b->id])
            ->call('simpanDisposisi', $a->nama);

        Livewire::actingAs($b)->test(SuratReview::class, ['surat' => $surat->fresh()])
            ->set('disposisiCatatan.'.$b->nama, 'Sudah saya kerjakan.')
            ->call('simpanDisposisi', $b->nama);

        Livewire::actingAs($a)->test(SuratReview::class, ['surat' => $surat->fresh()])
            ->set('disposisiCatatan.'.$a->nama, 'Coba batalkan B.')
            ->set('rekanSebagTerpilih.'.$a->nama, [])
            ->call('simpanDisposisi', $a->nama);

        $rowB = SuratDisposisi::where('surat_id', $surat->id)->where('role', $b->nama)->first();
        $this->assertNotNull($rowB, 'Rekan yang sudah merespon tidak boleh dibatalkan');
        $this->assertSame('Sudah saya kerjakan.', $rowB->catatan);
    }

    public function test_simpan_ulang_tidak_menggandakan_baris(): void
    {
        [$surat, , , $a, $b] = $this->bikinSkenario();

        for ($i = 0; $i < 2; $i++) {
            Livewire::actingAs($a)->test(SuratReview::class, ['surat' => $surat->fresh()])
                ->set('disposisiCatatan.'.$a->nama, 'Teruskan ke B ke-'.$i)
                ->set('rekanSebagTerpilih.'.$a->nama, [$b->id])
                ->call('simpanDisposisi', $a->nama);
        }

        $this->assertSame(1, SuratDisposisi::where('surat_id', $surat->id)->where('role', $b->nama)->count());
    }

    public function test_activity_log_ditulis_saat_meneruskan(): void
    {
        [$surat, , , $a, $b] = $this->bikinSkenario();

        Livewire::actingAs($a)->test(SuratReview::class, ['surat' => $surat])
            ->set('disposisiCatatan.'.$a->nama, 'Teruskan ke B.')
            ->set('rekanSebagTerpilih.'.$a->nama, [$b->id])
            ->call('simpanDisposisi', $a->nama);

        $this->assertDatabaseHas('activity_log', [
            'nama' => $a->nama,
            'aksi' => 'update',
        ]);
        $this->assertTrue(
            \App\Models\ActivityLog::where('surat_id', $surat->id)
                ->where('keterangan', 'like', '%terusan rekan sebag%')->exists(),
        );
    }
}
