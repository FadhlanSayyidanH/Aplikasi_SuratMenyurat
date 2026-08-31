<?php

namespace App\Livewire;

use App\Models\Surat;
use App\Models\SuratFile;
use App\Models\SuratFileAnnotation;
use App\Services\BaseUrlResolver;
use App\Services\TokenAuthService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Editor "Coret Foto" -- migrasi dari lib/screens/image_annotate_screen.dart.
 * Markup (gambar bebas + catatan tempel) digambar di kanvas HTML5 murni lewat
 * Alpine (lihat resources/views/livewire/surat-file-annotate.blade.php),
 * disimpan/dimuat sebagai data vektor (bentuk PdfAnnotationDokumen, lihat
 * lib/models/pdf_annotation.dart) TANPA mengubah file foto aslinya sama
 * sekali -- foto dianggap dokumen SATU "halaman" (kunci '1'), sama seperti
 * lib/utils/image_markup.dart::halamanFotoTunggal.
 *
 * SENGAJA meniru langsung Eloquent SuratFileController::annotationGet/Save
 * (bukan memanggilnya lewat HTTP) karena komponen ini jalan di sesi web
 * (Livewire), bukan lewat token bearer API -- lihat komentar di
 * routes/web_annotate.php.
 */
#[Layout('layouts.app', ['title' => 'Coret Foto'])]
class SuratFileAnnotate extends Component
{
    public SuratFile $suratFile;

    public string $imageUrl = '';

    public string $currentUserName = '';

    /** @var array<int, array> coretan tersimpan (halaman '1') -- dikirim ke Alpine lewat @js() di blade. */
    public array $initialCoretan = [];

    /** @var array<int, array> catatan tersimpan (halaman '1'). */
    public array $initialCatatan = [];

    public function mount(SuratFile $suratFile): void
    {
        $surat = $suratFile->surat;
        abort_unless($surat, 404);
        $this->authorizeAccess($surat);

        $ext = strtolower(pathinfo($suratFile->file_original_name, PATHINFO_EXTENSION));
        abort_unless(in_array($ext, ['jpg', 'jpeg', 'png'], true), 404, 'Halaman ini hanya untuk lampiran foto (JPG/PNG).');

        $this->suratFile = $suratFile;
        $this->currentUserName = Auth::user()->nama;

        $token = app(TokenAuthService::class)->getOrCreateFileToken(Auth::user());
        $baseUrl = BaseUrlResolver::resolve(request());
        $filePath = config('suratapp.uploads_path').'/'.$suratFile->file_name;
        $mtime = is_file($filePath) ? filemtime($filePath) : 0;
        $this->imageUrl = $baseUrl.'/uploads/'.$suratFile->file_name.'?token='.urlencode($token).'&v='.$mtime;

        $row = SuratFileAnnotation::query()->where('surat_file_id', $suratFile->id)->first();
        if ($row) {
            $data = json_decode($row->data, true) ?? [];
            // 'halaman'[1] (bukan ['1']) -- json_decode(..., true) otomatis meng-cast
            // key numerik-string jadi int key untuk array PHP, sama seperti perlakuan
            // PdfAnnotationDokumenJson.fromJson() di sisi Dart.
            $halaman = $data['halaman'][1] ?? null;
            if (is_array($halaman)) {
                $this->initialCoretan = array_values($halaman['coretan'] ?? []);
                $this->initialCatatan = array_values($halaman['catatan'] ?? []);
            }
        }
    }

    /**
     * Cermin SuratReview::authorizeAccess() -- akun bukan admin/pimpinan
     * hanya boleh membuka lampiran surat yang benar-benar melibatkan mereka
     * (salah satu tahap approval, tujuan disposisi, atau pernah memproses
     * tahapnya).
     */
    private function authorizeAccess(Surat $surat): void
    {
        abort_unless($surat->bolehDiaksesOleh(Auth::user()), 403, 'Anda tidak berhak mengakses lampiran surat ini.');
    }

    /**
     * Simpan markup -- cermin 1:1 SuratFileController::annotationSave()
     * (updateOrCreate on surat_file_id; hapus barisnya sama sekali kalau
     * SEMUA markup di SEMUA halaman sudah dihapus, lihat komentar method
     * aslinya soal bug json_encode array-vs-objek kosong).
     *
     * @param  array  $document  bentuk PdfAnnotationDokumen::toJson(), mis.
     *                           ['halaman' => ['1' => ['coretan' => [...], 'catatan' => [...]]]]
     */
    public function save(array $document): void
    {
        if (empty($document['halaman'])) {
            SuratFileAnnotation::query()->where('surat_file_id', $this->suratFile->id)->delete();

            return;
        }

        SuratFileAnnotation::query()->updateOrCreate(
            ['surat_file_id' => $this->suratFile->id],
            ['data' => json_encode($document), 'diubah_oleh' => Auth::user()->nama],
        );
    }

    public function render()
    {
        return view('livewire.surat-file-annotate');
    }
}
