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
 * GET /surat_edit_pdf.php (OnlyOfficeController::editPdf()) already followed
 * the "view always if involved, edit only if it's your turn" pattern before
 * 2026-09-01 -- but shared bolehEditTahap() with editDocx(), which had a bug
 * (see OnlyOfficeEditDocxTest) that let a user whose OWN stage (or a later
 * stage) was already processed still get permissions.edit=true. That bug
 * applied here too, just harder to reach in practice since the web UI never
 * linked a plain "Buka" for Surat Keluar in that state (SuratReview always
 * routed through the reset-confirming "Buka & Edit" button instead). This
 * file locks in the fixed behavior for PDF, which had zero test coverage
 * before this fix.
 */
class OnlyOfficeEditPdfTest extends TestCase
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
        return User::create([
            'username' => 'user_'.str()->slug($nama),
            'nama' => $nama,
            'role' => 'user',
            'password' => Hash::make('Password123'),
            'token' => bin2hex(random_bytes(32)),
            'token_expires_at' => now()->addHour(),
        ]);
    }

    /** @return array{0: Surat, 1: SuratFile} */
    private function buatSuratPdfDenganRantai(string $statusSurat, array $tahap): array
    {
        $surat = Surat::create([
            'jenis' => 'keluar',
            'tanggal' => now()->toDateString(),
            'perihal' => 'Surat uji viewer PDF',
            'klasifikasi' => 'Surat Keputusan',
            'status' => $statusSurat,
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

        $fileName = 'test_'.uniqid().'.pdf';
        $path = config('suratapp.uploads_path').'/'.$fileName;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, '%PDF-1.4 dummy');
        $this->filePaths[] = $path;

        $file = SuratFile::create([
            'surat_id' => $surat->id,
            'urutan' => 1,
            'file_name' => $fileName,
            'file_original_name' => 'Draft.pdf',
        ]);

        return [$surat, $file];
    }

    private function bukaSebagai(User $user, SuratFile $file)
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->token)
            ->getJson('/surat_edit_pdf.php?file_id='.$file->id);
    }

    public function test_giliran_aktif_boleh_lihat_dan_edit(): void
    {
        [, $file] = $this->buatSuratPdfDenganRantai('menunggu', [
            ['A Kaur', 'disetujui'],
            ['B Kasi', 'menunggu'],
        ]);
        $user = $this->buatUser('B Kasi');

        $response = $this->bukaSebagai($user, $file);

        $response->assertOk();
        $this->assertTrue($response->json('config.document.permissions.edit'));
    }

    public function test_tahap_sendiri_sudah_diproses_boleh_lihat_tidak_boleh_edit(): void
    {
        [, $file] = $this->buatSuratPdfDenganRantai('menunggu', [
            ['A Kaur', 'disetujui'],
            ['B Kasi', 'disetujui'],
            ['C Kabag', 'menunggu'],
        ]);
        $user = $this->buatUser('B Kasi');

        $response = $this->bukaSebagai($user, $file);

        $response->assertOk();
        $this->assertFalse($response->json('config.document.permissions.edit'));
    }

    public function test_surat_disetujui_penuh_boleh_lihat_tidak_boleh_edit(): void
    {
        [, $file] = $this->buatSuratPdfDenganRantai('disetujui', [
            ['A Kaur', 'disetujui'],
        ]);
        $user = $this->buatUser('A Kaur');

        $response = $this->bukaSebagai($user, $file);

        $response->assertOk();
        $this->assertFalse($response->json('config.document.permissions.edit'));
    }

    public function test_orang_tidak_terlibat_tetap_403(): void
    {
        [, $file] = $this->buatSuratPdfDenganRantai('menunggu', [
            ['A Kaur', 'menunggu'],
        ]);
        $orangLuar = $this->buatUser('Orang Tidak Terlibat');

        $response = $this->bukaSebagai($orangLuar, $file);

        $response->assertStatus(403);
    }
}
