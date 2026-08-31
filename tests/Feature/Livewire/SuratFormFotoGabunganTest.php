<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Surat\SuratForm;
use App\Models\Surat;
use App\Models\SuratFile;
use App\Models\User;
use App\Services\FileEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cakupan test untuk App\Services\SuratFileService::simpanFileSuratMasuk()
 * -- keluhan pengguna: hasil foto/scan surat masuk berhalaman banyak
 * (dari galeri/kamera) tersimpan sebagai N lampiran PDF satu-halaman
 * terpisah, jadi harus buka file satu-satu untuk baca satu surat yang
 * sama. Sekarang 2 foto atau lebih dalam satu submission otomatis
 * digabung jadi SATU PDF multi-halaman. Fokus test: (1) 2+ foto -> 1
 * lampiran PDF gabungan dengan jumlah halaman yang benar, (2) 1 foto
 * SAJA tetap seperti perilaku lama (1 PDF 1 halaman, tidak "digabung"
 * dengan dirinya sendiri), (3) file non-foto ikut tersimpan terpisah di
 * samping PDF gabungan, (4) Surat Keluar TIDAK terpengaruh sama sekali
 * (tetap N lampiran terpisah walau uploadnya banyak foto).
 */
class SuratFormFotoGabunganTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(): User
    {
        return User::create([
            'username' => 'user_form_'.str()->random(8),
            'nama' => 'Kaur Uji Form',
            'role' => 'user',
            'password' => Hash::make('Password123'),
            'boleh_input_masuk' => true,
            'boleh_input_keluar' => true,
        ]);
    }

    /**
     * UploadedFile::fake()->create() mengisi file dengan bytes JUNK
     * (bukan PDF sungguhan) -- lolos kalau divalidasi langsung, tapi
     * SuratFileService memang sengaja MENGENDUS isi file sungguhan
     * (bukan cuma percaya MIME yang diklaim), jadi begitu file itu lewat
     * pipeline upload Livewire (yang deteksi ulang MIME dari isi fisik),
     * validasinya gagal ("isi file tidak sesuai ekstensinya"). Untuk
     * menguji "file non-foto ikut tersimpan apa adanya", perlu PDF
     * SUNGGUHAN -- generate lewat dompdf (sudah jadi dependency app ini).
     */
    private function pdfAsliUntukUji(string $originalName): UploadedFile
    {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml('<p>Lampiran uji SuratFormFotoGabunganTest</p>');
        $dompdf->render();

        // Pakai UploadedFile::fake() supaya properti internal yang dibutuhkan
        // helper testing Livewire (mis. ->name) tetap ada -- lalu TIMPA isi
        // fisiknya dengan PDF sungguhan (bukan konstruksi UploadedFile manual,
        // yang bikin Livewire::test()->set('files', ...) error "Undefined
        // property $name").
        $file = UploadedFile::fake()->create($originalName, 1, 'application/pdf');
        file_put_contents($file->getRealPath(), $dompdf->output());

        return $file;
    }

    private function jumlahHalamanPdf(string $fileName): int
    {
        $crypto = app(FileEncryptionService::class);
        $storedPath = config('suratapp.uploads_path').'/'.$fileName;
        $tempPath = $crypto->decryptToTempFile($storedPath, '.pdf');

        exec('pdfinfo '.escapeshellarg($tempPath).' 2>&1', $output);
        @unlink($tempPath);

        foreach ($output as $line) {
            if (preg_match('/^Pages:\s*(\d+)/', $line, $m)) {
                return (int) $m[1];
            }
        }

        $this->fail('pdfinfo tidak mengembalikan jumlah halaman: '.implode("\n", $output));
    }

    public function test_dua_foto_sekaligus_digabung_jadi_satu_pdf_dua_halaman(): void
    {
        $user = $this->buatUser();

        Livewire::actingAs($user)->test(SuratForm::class)
            ->set('jenis', 'masuk')
            ->set('nomorSurat', '001/TEST/2026')
            ->set('namaPengaju', 'Pengirim Uji')
            ->set('perihal', 'Uji gabung foto jadi satu PDF')
            ->set('files', [
                UploadedFile::fake()->image('halaman1.jpg', 800, 1000),
                UploadedFile::fake()->image('halaman2.jpg', 800, 1000),
            ])
            ->call('submit');

        $surat = Surat::query()->where('perihal', 'Uji gabung foto jadi satu PDF')->firstOrFail();
        $files = SuratFile::query()->where('surat_id', $surat->id)->get();

        $this->assertCount(1, $files, 'Harusnya cuma 1 lampiran (PDF gabungan), bukan 2 lampiran terpisah');
        $this->assertStringEndsWith('.pdf', $files->first()->file_name);
        $this->assertSame('halaman1.pdf', $files->first()->file_original_name);
        $this->assertSame(2, $this->jumlahHalamanPdf($files->first()->file_name));
    }

    public function test_satu_foto_saja_tetap_satu_pdf_satu_halaman_seperti_sebelumnya(): void
    {
        $user = $this->buatUser();

        Livewire::actingAs($user)->test(SuratForm::class)
            ->set('jenis', 'masuk')
            ->set('nomorSurat', '002/TEST/2026')
            ->set('namaPengaju', 'Pengirim Uji')
            ->set('perihal', 'Uji satu foto saja')
            ->set('files', [UploadedFile::fake()->image('satu.jpg', 800, 1000)])
            ->call('submit');

        $surat = Surat::query()->where('perihal', 'Uji satu foto saja')->firstOrFail();
        $files = SuratFile::query()->where('surat_id', $surat->id)->get();

        $this->assertCount(1, $files);
        $this->assertSame(1, $this->jumlahHalamanPdf($files->first()->file_name));
    }

    public function test_file_non_foto_ikut_tersimpan_terpisah_di_samping_pdf_gabungan(): void
    {
        $user = $this->buatUser();

        Livewire::actingAs($user)->test(SuratForm::class)
            ->set('jenis', 'masuk')
            ->set('nomorSurat', '003/TEST/2026')
            ->set('namaPengaju', 'Pengirim Uji')
            ->set('perihal', 'Uji campur foto dan pdf')
            ->set('files', [
                UploadedFile::fake()->image('halaman1.jpg', 800, 1000),
                UploadedFile::fake()->image('halaman2.jpg', 800, 1000),
                $this->pdfAsliUntukUji('lampiran-lain.pdf'),
            ])
            ->call('submit');

        $surat = Surat::query()->where('perihal', 'Uji campur foto dan pdf')->firstOrFail();
        $files = SuratFile::query()->where('surat_id', $surat->id)->orderBy('urutan')->get();

        $this->assertCount(2, $files, 'PDF gabungan (2 foto) + 1 PDF lampiran lain = 2 baris');
        $this->assertSame(2, $this->jumlahHalamanPdf($files[0]->file_name), 'Lampiran pertama harus PDF gabungan 2 halaman');
    }

    /**
     * Surat Keluar SENGAJA tidak dites lewat wizard penuh (setup rantai
     * approval-nya tidak relevan dengan fitur ini dan menambah kompleksitas
     * tidak perlu) -- yang perlu dipastikan cuma: jalur lama
     * (simpanFileSurat() dipanggil per-file, TANPA lewat
     * simpanFileSuratMasuk()) masih menghasilkan N lampiran terpisah untuk
     * N foto, bukan digabung. Ini persis jalur yang tetap dipakai
     * App\Livewire\Surat\SuratForm::submit() & Api\SuratController@create
     * untuk jenis='keluar' (lihat percabangan if($jenis==='masuk') di
     * kedua tempat itu -- 'keluar' TIDAK PERNAH memanggil simpanFileSuratMasuk()).
     */
    public function test_simpanFileSurat_per_file_lama_tidak_menggabung_foto(): void
    {
        $mimeWhitelist = config('suratapp.mime_keluar');
        $service = app(\App\Services\SuratFileService::class);

        $saved1 = $service->simpanFileSurat(UploadedFile::fake()->image('a.jpg', 800, 1000), $mimeWhitelist);
        $saved2 = $service->simpanFileSurat(UploadedFile::fake()->image('b.jpg', 800, 1000), $mimeWhitelist);

        $this->assertNotSame($saved1['file_name'], $saved2['file_name']);
        $this->assertSame(1, $this->jumlahHalamanPdf($saved1['file_name']));
        $this->assertSame(1, $this->jumlahHalamanPdf($saved2['file_name']));
    }
}
