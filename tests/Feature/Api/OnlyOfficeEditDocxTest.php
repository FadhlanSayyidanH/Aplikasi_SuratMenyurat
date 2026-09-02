<?php

namespace Tests\Feature\Api;

use App\Models\Surat;
use App\Models\SuratApproval;
use App\Models\SuratFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cakupan test untuk GET /surat_edit_docx.php (OnlyOfficeController::editDocx())
 * -- sebelum 2026-09-01 endpoint ini 403 TOTAL untuk siapa pun yang bukan
 * gilirannya SEKARANG, sehingga pejabat yang gilirannya sudah lewat (baik
 * karena tahapnya sendiri sudah diproses, atau tahap sesudahnya sudah
 * diproses) tidak bisa MELIHAT dokumen sama sekali kecuali lewat
 * SuratReview::editDocumentAndOpen() yang memaksa reset rantai approval
 * dulu. Sejak perbaikan ini, endpoint mengikuti pola editPdf(): akses
 * LIHAT dasar (bolehDiaksesOleh) vs izin EDIT (bolehEditTahap) dipisah --
 * lihat memori project-surat-buka-edit-view-only.
 */
class OnlyOfficeEditDocxTest extends TestCase
{
    use RefreshDatabase;

    private array $filePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->filePaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    private function buatUser(string $nama): User
    {
        $token = bin2hex(random_bytes(32));

        return User::create([
            'username' => 'user_'.str()->slug($nama),
            'nama' => $nama,
            'role' => 'user',
            'password' => Hash::make('Password123'),
            'token' => $token,
            'token_expires_at' => now()->addHour(),
        ]);
    }

    /** @return array{0: Surat, 1: SuratFile} */
    private function buatSuratDocxDenganRantai(string $statusSuratKeseluruhan, array $tahap): array
    {
        $surat = Surat::create([
            'jenis' => 'keluar',
            'tanggal' => now()->toDateString(),
            'perihal' => 'Surat uji buka & edit',
            'klasifikasi' => 'Surat Keputusan',
            'status' => $statusSuratKeseluruhan,
            'kabag_dituju' => 'Bag Uji',
        ]);

        foreach ($tahap as $i => [$nama, $status]) {
            SuratApproval::create([
                'surat_id' => $surat->id,
                'urutan' => $i + 1,
                'role' => $nama,
                'status' => $status,
                'diproses_oleh' => $status === 'disetujui' ? $nama : null,
                'diproses_at' => $status === 'disetujui' ? now() : null,
            ]);
        }

        $fileName = 'test_'.uniqid().'.docx';
        $path = config('suratapp.uploads_path').'/'.$fileName;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, 'dummy docx content');
        $this->filePaths[] = $path;

        $file = SuratFile::create([
            'surat_id' => $surat->id,
            'urutan' => 1,
            'file_name' => $fileName,
            'file_original_name' => 'Draft.docx',
        ]);

        return [$surat, $file];
    }

    private function bukaSebagai(User $user, SuratFile $file)
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->token)
            ->getJson('/surat_edit_docx.php?file_id='.$file->id);
    }

    public function test_giliran_aktif_boleh_lihat_dan_edit(): void
    {
        [$surat, $file] = $this->buatSuratDocxDenganRantai('menunggu', [
            ['A Kaur', 'disetujui'],
            ['B Kasi', 'menunggu'],
        ]);
        $user = $this->buatUser('B Kasi');

        $response = $this->bukaSebagai($user, $file);

        $response->assertOk();
        $this->assertTrue($response->json('config.document.permissions.edit'));
    }

    public function test_tahap_sendiri_sudah_diproses_tetap_boleh_lihat_tapi_tidak_edit(): void
    {
        [$surat, $file] = $this->buatSuratDocxDenganRantai('menunggu', [
            ['A Kaur', 'disetujui'],
            ['B Kasi', 'disetujui'],
            ['C Kabag', 'menunggu'],
        ]);
        $user = $this->buatUser('B Kasi');

        $response = $this->bukaSebagai($user, $file);

        $response->assertOk();
        $this->assertFalse($response->json('config.document.permissions.edit'));
    }

    public function test_tahap_sesudahnya_sudah_diproses_tetap_boleh_lihat_tapi_tidak_edit(): void
    {
        // Skenario nyata yang dilaporkan: dokumen sempat diedit ulang oleh
        // pejabat awal (mereset rantai), lalu diproses lagi -- pejabat yang
        // tahapnya SUDAH LEWAT (di depan urutan yang sekarang aktif) harus
        // tetap bisa membuka dokumen untuk melihat, bukan 403 total.
        [$surat, $file] = $this->buatSuratDocxDenganRantai('menunggu', [
            ['A Kaur', 'disetujui'],
            ['B Kasi', 'menunggu'],
            ['C Kabag', 'menunggu'],
        ]);
        // C sudah "melangkahi" (skenario admin override / rantai manual) --
        // A disetujui, B masih menunggu, tapi C sudah diproses duluan.
        SuratApproval::where('surat_id', $surat->id)->where('role', 'C Kabag')
            ->update(['status' => 'disetujui', 'diproses_oleh' => 'C Kabag', 'diproses_at' => now()]);
        $user = $this->buatUser('A Kaur');

        $response = $this->bukaSebagai($user, $file);

        $response->assertOk();
        $this->assertFalse($response->json('config.document.permissions.edit'));
    }

    public function test_surat_sudah_disetujui_sepenuhnya_tetap_boleh_lihat_tapi_tidak_edit(): void
    {
        [$surat, $file] = $this->buatSuratDocxDenganRantai('disetujui', [
            ['A Kaur', 'disetujui'],
            ['B Kasi', 'disetujui'],
        ]);
        $user = $this->buatUser('A Kaur');

        $response = $this->bukaSebagai($user, $file);

        $response->assertOk();
        $this->assertFalse($response->json('config.document.permissions.edit'));
    }

    public function test_admin_selalu_boleh_lihat(): void
    {
        [$surat, $file] = $this->buatSuratDocxDenganRantai('menunggu', [
            ['A Kaur', 'menunggu'],
        ]);
        $admin = User::create([
            'username' => 'admin_uji',
            'nama' => 'Admin Uji',
            'role' => 'admin',
            'password' => Hash::make('Password123'),
            'token' => bin2hex(random_bytes(32)),
            'token_expires_at' => now()->addHour(),
        ]);

        $response = $this->bukaSebagai($admin, $file);

        $response->assertOk();
    }

    public function test_orang_yang_tidak_terlibat_sama_sekali_tetap_403(): void
    {
        [$surat, $file] = $this->buatSuratDocxDenganRantai('menunggu', [
            ['A Kaur', 'menunggu'],
        ]);
        $orangLuar = $this->buatUser('Orang Tidak Terlibat');

        $response = $this->bukaSebagai($orangLuar, $file);

        $response->assertStatus(403);
    }
}
