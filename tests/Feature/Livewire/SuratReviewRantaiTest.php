<?php

namespace Tests\Feature\Livewire;

use App\Livewire\SuratReview;
use App\Models\Surat;
use App\Models\SuratApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cakupan test untuk panel "Ubah Rantai Proses" (App\Livewire\SuratReview::
 * simpanEditRantaiManual()/mulaiEditRantai()/recomputeStatusSurat()) --
 * logika ini butuh 4 ronde perbaikan dalam SATU sesi kerja sebelum test ini
 * ditulis (lihat memori project-surat-edit-rantai-bugfix), padahal sebelumnya
 * TIDAK ADA test otomatis sama sekali untuknya -- seluruh verifikasi sebelum
 * ini murni manual lewat browser & tinker. Skenario di bawah PERSIS meniru
 * yang divalidasi manual saat itu, supaya regresi apa pun pada perilaku ini
 * langsung ketahuan tanpa perlu pengujian manual berulang.
 *
 * Rantai referensi 6 tahap dipakai di semua test (meniru struktur Bag
 * Kaur->Kasi->Kabag->Kabagtu->Turmin->Kasubdit yang dipakai saat verifikasi
 * manual): A, B, C, D, E, F.
 */
class SuratReviewRantaiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Surat, 1: array<int, User>} */
    private function buatSuratDenganRantaiEnamTahap(array $statusPerTahap): array
    {
        $namaTahap = ['A Kaur', 'B Kasi', 'C Kabag', 'D Kabagtu', 'E Turmin', 'F Kasubdit'];

        $users = [];
        foreach ($namaTahap as $i => $nama) {
            $users[] = User::create([
                'username' => 'user_tahap_'.$i,
                'nama' => $nama,
                'role' => 'user',
                'password' => Hash::make('Password123'),
            ]);
        }

        $surat = Surat::create([
            'jenis' => 'keluar',
            'tanggal' => now()->toDateString(),
            'perihal' => 'Surat uji rantai proses',
            'klasifikasi' => 'Surat Keputusan',
            'status' => 'menunggu',
            'kabag_dituju' => 'Bag Uji',
        ]);

        foreach ($namaTahap as $i => $nama) {
            $status = $statusPerTahap[$i];
            SuratApproval::create([
                'surat_id' => $surat->id,
                'urutan' => $i + 1,
                'role' => $nama,
                'status' => $status,
                'diproses_oleh' => $status === 'disetujui' ? $nama : null,
                'diproses_at' => $status === 'disetujui' ? now() : null,
            ]);
        }

        return [$surat, $users];
    }

    /**
     * Reproduksi bug ronde 4: rantai A-F, A-D sudah disetujui, giliran E.
     * E memangkas rantai manual jadi PERSIS A,B,C,D (menghapus dirinya
     * sendiri & F) -- seharusnya surat otomatis jadi "disetujui" penuh
     * (recomputeStatusSurat() WAJIB terpanggil), bukan ngambang di
     * 'menunggu' tanpa ada lagi yang bisa memprosesnya.
     */
    public function test_memangkas_rantai_manual_ke_prefix_yang_sudah_disetujui_penuh_menyelesaikan_surat(): void
    {
        [$surat, $users] = $this->buatSuratDenganRantaiEnamTahap([
            'disetujui', 'disetujui', 'disetujui', 'disetujui', 'menunggu', 'menunggu',
        ]);
        $baris = SuratApproval::where('surat_id', $surat->id)->orderBy('urutan')->get();
        $idAsalABCD = $baris->take(4)->pluck('id')->all();
        $waktuAsalABCD = $baris->take(4)->pluck('diproses_at')->map(fn ($t) => $t->toDateTimeString())->all();

        Livewire::actingAs($users[4]) // E, giliran aktif
            ->test(SuratReview::class, ['surat' => $surat])
            ->call('mulaiEditRantai')
            ->call('toggleModeManual')
            ->call('hapusDariRantaiManual', 5) // buang F (indeks ke-5 di rantai manual hasil pra-isi)
            ->call('hapusDariRantaiManual', 4) // buang E (dirinya sendiri, sekarang indeks ke-4)
            ->call('simpanEditRantai');

        $surat->refresh();
        $this->assertSame('disetujui', $surat->status, 'surat harus otomatis ke-ACC penuh begitu tidak ada lagi tahap menunggu');

        $sisaBaris = SuratApproval::where('surat_id', $surat->id)->orderBy('urutan')->get();
        $this->assertCount(4, $sisaBaris, 'tahap E & F harus terhapus, tersisa persis A-D');
        $this->assertSame($idAsalABCD, $sisaBaris->pluck('id')->all(), 'baris A-D TIDAK BOLEH dibuat ulang -- id aslinya harus tetap sama');
        $this->assertSame($waktuAsalABCD, $sisaBaris->pluck('diproses_at')->map(fn ($t) => $t->toDateTimeString())->all(), 'waktu persetujuan A-D asli tidak boleh berubah');
        $this->assertTrue($sisaBaris->every(fn (SuratApproval $b) => $b->status === 'disetujui'));
    }

    /**
     * Rantai A-F, A-D sudah disetujui, giliran E. E menyusun ulang rantai
     * manual TANPA mengubah A-D sama sekali, cuma menghapus D lalu menambah
     * F kembali -- posisi A,B,C harus tetap dipertahankan APA ADANYA (id &
     * waktu tidak berubah), posisi D dst diganti jadi tahap baru.
     */
    public function test_mengubah_rantai_setelah_prefix_yang_sama_mempertahankan_prefix_itu(): void
    {
        [$surat, $users] = $this->buatSuratDenganRantaiEnamTahap([
            'disetujui', 'disetujui', 'disetujui', 'disetujui', 'menunggu', 'menunggu',
        ]);
        $baris = SuratApproval::where('surat_id', $surat->id)->orderBy('urutan')->get();
        $idAsalABC = $baris->take(3)->pluck('id')->all();

        Livewire::actingAs($users[4]) // E
            ->test(SuratReview::class, ['surat' => $surat])
            ->call('mulaiEditRantai')
            ->call('toggleModeManual')
            ->call('hapusDariRantaiManual', 3) // buang D (posisi ke-4, indeks 3) -- A,B,C tetap
            ->call('simpanEditRantai');

        $surat->refresh();
        $this->assertSame('menunggu', $surat->status, 'masih ada tahap baru yang menunggu, surat belum boleh selesai');

        $sisaBaris = SuratApproval::where('surat_id', $surat->id)->orderBy('urutan')->get();
        $this->assertSame($idAsalABC, $sisaBaris->take(3)->pluck('id')->all(), 'A, B, C tidak berubah posisinya -- baris aslinya harus dipertahankan');
        $this->assertSame(['disetujui', 'disetujui', 'disetujui'], $sisaBaris->take(3)->pluck('status')->all());

        // D lama (sudah disetujui) HILANG dari rantai baru (posisi ke-4
        // sekarang langsung E, menunggu) -- rantai baru: A,B,C,E, semuanya
        // setelah posisi 3 berstatus 'menunggu' baru.
        $tahapKe4 = $sisaBaris->get(3);
        $this->assertSame('E Turmin', $tahapKe4->role);
        $this->assertSame('menunggu', $tahapKe4->status);
    }

    /**
     * Rantai A-D sudah disetujui, giliran E. E dengan SADAR menghapus B dari
     * rantai manual (bukan cuma menggeser tahap yang belum diproses) --
     * begitu ada posisi di dalam prefix yang sudah disetujui ikut berubah,
     * posisi itu DAN SETERUSNYA (termasuk B, C, D yang tadinya sudah
     * disetujui) harus di-reset jadi tahap 'menunggu' baru -- ini perilaku
     * yang SENGAJA (lihat komentar bisaEditRantai()), bukan bug.
     */
    public function test_mengubah_posisi_di_dalam_prefix_yang_sudah_disetujui_mereset_dari_titik_itu(): void
    {
        [$surat, $users] = $this->buatSuratDenganRantaiEnamTahap([
            'disetujui', 'disetujui', 'disetujui', 'disetujui', 'menunggu', 'menunggu',
        ]);
        $baris = SuratApproval::where('surat_id', $surat->id)->orderBy('urutan')->get();
        $idAsalA = $baris->get(0)->id;

        Livewire::actingAs($users[4]) // E
            ->test(SuratReview::class, ['surat' => $surat])
            ->call('mulaiEditRantai')
            ->call('toggleModeManual')
            ->call('hapusDariRantaiManual', 1) // buang B (posisi ke-2) -- A tetap, tapi urutan sesudahnya bergeser
            ->call('simpanEditRantai');

        $surat->refresh();
        $this->assertSame('menunggu', $surat->status);

        $sisaBaris = SuratApproval::where('surat_id', $surat->id)->orderBy('urutan')->get();
        $this->assertSame($idAsalA, $sisaBaris->first()->id, 'A tidak tersentuh -- posisinya persis sama');
        $this->assertSame('disetujui', $sisaBaris->first()->status);

        // Posisi ke-2 dst SEMUA jadi tahap baru 'menunggu' (C, D, E, F -- B hilang).
        $sisanya = $sisaBaris->slice(1);
        $this->assertTrue($sisanya->every(fn (SuratApproval $b) => $b->status === 'menunggu'), 'C & D yang tadinya sudah disetujui harus ikut ter-reset karena B (sebelum mereka) berubah');
        $this->assertSame(['C Kabag', 'D Kabagtu', 'E Turmin', 'F Kasubdit'], $sisanya->pluck('role')->all());
    }
}
