<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Surat;
use App\Models\SuratFile;
use App\Models\SuratFileAnnotation;
use App\Services\ActivityLogger;
use App\Services\BaseUrlResolver;
use App\Services\SuratFileService;
use App\Services\TokenAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Migrasi dari backend/public/surat_file_add.php, surat_file_delete.php,
 * surat_file_rename.php, surat_file_annotation_get.php,
 * surat_file_annotation_save.php (proyek PHP lama) -- kelola file lampiran
 * satu Surat Keluar (tambah/hapus/ganti nama) dan markup "coret-coret"
 * (annotation) tersimpan untuk satu file PDF.
 */
class SuratFileController extends Controller
{
    public function __construct(
        private SuratFileService $suratFileService,
        private TokenAuthService $tokenAuth,
    ) {}

    public function add(Request $request)
    {
        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT);
        if (!$id) {
            throw new ApiException('ID surat tidak valid', 400);
        }

        $surat = Surat::query()->find($id);
        if (!$surat) {
            throw new ApiException('Surat tidak ditemukan', 404);
        }
        if ($surat->jenis !== 'keluar') {
            throw new ApiException('Hanya Surat Keluar yang bisa ditambah filenya di sini', 400);
        }
        if ($surat->status === 'disetujui') {
            throw new ApiException('Dokumen sudah disetujui sepenuhnya, lampirannya tidak bisa diubah lagi', 403);
        }

        $authUser = $request->user();

        if (!$this->suratFileService->bolehKelolaFileSuratKeluar($id, $surat->status, $authUser)) {
            throw new ApiException(
                'Hanya pengaju pertama (Kaur) atau pejabat pemegang giliran saat ini yang boleh menambah filenya',
                403,
            );
        }

        $filesInput = $this->normalisasiFilesUpload($request->file('files'));
        if (!$filesInput) {
            throw new ApiException('Minimal satu file DOCX/PPTX/XLSX/PDF/JPG/PNG wajib diunggah', 400);
        }

        $displayNames = $request->input('file_display_names', []);
        if (!is_array($displayNames)) {
            $displayNames = [];
        }

        $urutanBerikutnya = (int) (SuratFile::query()->where('surat_id', $id)->max('urutan') ?? -1) + 1;

        $mimeWhitelist = config('suratapp.mime_keluar');
        $uploadsPath = config('suratapp.uploads_path');

        $stored = [];
        try {
            foreach ($filesInput as $index => $file) {
                $saved = $this->suratFileService->simpanFileSurat($file, $mimeWhitelist);
                $customName = trim((string) ($displayNames[$index] ?? ''));
                if ($customName !== '') {
                    $saved['file_original_name'] = $this->suratFileService->terapkanNamaTampilan($saved, $customName);
                }
                $stored[] = $saved;
            }
        } catch (\RuntimeException $e) {
            foreach ($stored as $s) {
                @unlink($uploadsPath.'/'.$s['file_name']);
            }
            throw new ApiException($e->getMessage(), 400);
        }

        foreach ($stored as $s) {
            SuratFile::query()->create([
                'surat_id' => $id,
                'urutan' => $urutanBerikutnya,
                'file_name' => $s['file_name'],
                'file_original_name' => $s['file_original_name'],
            ]);
            $urutanBerikutnya++;
        }

        ActivityLogger::log(
            $request,
            null,
            $authUser->nama,
            'update',
            'Tambah '.count($stored)." file baru ke Surat Keluar ({$surat->perihal}) sebelum diajukan ulang",
            $id,
        );

        return response()->json($this->suratFileService->suratDetailDenganFiles($id, $authUser));
    }

    public function delete(Request $request)
    {
        $fileId = filter_var($request->input('file_id'), FILTER_VALIDATE_INT);
        if (!$fileId) {
            throw new ApiException('ID file tidak valid', 400);
        }

        $file = SuratFile::query()->find($fileId);
        if (!$file) {
            throw new ApiException('File tidak ditemukan', 404);
        }

        $surat = Surat::query()->find($file->surat_id);
        $id = (int) $file->surat_id;

        if (!$surat || $surat->jenis !== 'keluar') {
            throw new ApiException('Hanya file Surat Keluar yang bisa dihapus di sini', 400);
        }
        if ($surat->status === 'disetujui') {
            throw new ApiException('Dokumen sudah disetujui sepenuhnya, lampirannya tidak bisa diubah lagi', 403);
        }

        $authUser = $request->user();

        if (!$this->suratFileService->bolehKelolaFileSuratKeluar($id, $surat->status, $authUser)) {
            throw new ApiException(
                'Hanya pengaju pertama (Kaur) atau pejabat pemegang giliran saat ini yang boleh menghapus filenya',
                403,
            );
        }

        $jumlah = SuratFile::query()->where('surat_id', $id)->count();
        if ($jumlah <= 1) {
            throw new ApiException('Surat harus punya minimal satu file', 400);
        }

        @unlink(config('suratapp.uploads_path').'/'.$file->file_name);
        $baseName = pathinfo($file->file_name, PATHINFO_FILENAME);
        @unlink(config('suratapp.preview_cache_path').'/'.$baseName.'.pdf');

        $file->delete();

        ActivityLogger::log(
            $request,
            null,
            $authUser->nama,
            'update',
            "Hapus file dari Surat Keluar ({$surat->perihal}) sebelum diajukan ulang",
            $id,
        );

        return response()->json($this->suratFileService->suratDetailDenganFiles($id, $authUser));
    }

    public function rename(Request $request)
    {
        $fileId = filter_var($request->input('file_id'), FILTER_VALIDATE_INT);
        $namaBaru = trim((string) $request->input('file_original_name', ''));

        if (!$fileId) {
            throw new ApiException('ID file tidak valid', 400);
        }
        if ($namaBaru === '') {
            throw new ApiException('Nama file tidak boleh kosong', 400);
        }

        $file = SuratFile::query()->find($fileId);
        if (!$file) {
            throw new ApiException('File tidak ditemukan', 404);
        }

        $surat = Surat::query()->find($file->surat_id);
        $authUser = $request->user();

        if ($surat && $surat->jenis === 'keluar') {
            if ($surat->status === 'disetujui') {
                throw new ApiException('Dokumen sudah disetujui sepenuhnya, lampirannya tidak bisa diubah lagi', 403);
            }
            if (!$this->suratFileService->bolehKelolaFileSuratKeluar((int) $file->surat_id, $surat->status, $authUser)) {
                throw new ApiException(
                    'Hanya pengaju pertama (Kaur) atau pejabat pemegang giliran saat ini yang boleh mengganti nama filenya',
                    403,
                );
            }
        }

        // Ekstensi tetap dipertahankan sama seperti fitur ganti nama saat
        // unggah (lihat SuratFileService::simpanFileSurat/terapkanNamaTampilan)
        // -- pengguna cuma boleh mengganti nama, bukan format filenya.
        $extAsli = pathinfo($file->file_original_name, PATHINFO_EXTENSION);
        $extBaru = pathinfo($namaBaru, PATHINFO_EXTENSION);
        $namaFinal = strcasecmp($extAsli, $extBaru) === 0 ? $namaBaru : $namaBaru.'.'.$extAsli;

        $namaLama = $file->file_original_name;
        $file->file_original_name = $namaFinal;
        $file->save();

        ActivityLogger::log(
            $request,
            null,
            $authUser->nama,
            'update',
            "Ganti nama file \"{$namaLama}\" menjadi \"{$namaFinal}\"",
            (int) $file->surat_id,
        );

        $baseUrl = BaseUrlResolver::resolve($request);
        // file_token (BUKAN token sesi login penuh) -- sama seperti
        // SuratFileService::getSuratFiles(), supaya file_url yang
        // dikembalikan konsisten valid lewat router file (memverifikasi
        // file_token, bukan token sesi).
        $fileToken = $this->tokenAuth->getOrCreateFileToken($authUser);

        $filePath = config('suratapp.uploads_path').'/'.$file->file_name;
        $mtime = is_file($filePath) ? filemtime($filePath) : 0;

        return response()->json([
            'id' => $file->id,
            'file_name' => $file->file_name,
            'file_original_name' => $namaFinal,
            'file_url' => $baseUrl.'/uploads/'.$file->file_name.'?token='.urlencode($fileToken).'&v='.$mtime,
        ]);
    }

    public function annotationGet(Request $request)
    {
        $fileId = filter_var($request->query('file_id'), FILTER_VALIDATE_INT);
        if (!$fileId) {
            throw new ApiException('ID file tidak valid', 400);
        }

        $row = SuratFileAnnotation::query()->where('surat_file_id', $fileId)->first();

        return response()->json(['data' => $row ? json_decode($row->data) : null]);
    }

    public function annotationSave(Request $request)
    {
        $fileId = filter_var($request->input('file_id'), FILTER_VALIDATE_INT);
        $data = $request->input('data');

        if (!$fileId) {
            throw new ApiException('ID file tidak valid', 400);
        }
        if (!is_array($data)) {
            throw new ApiException('Data markup tidak valid', 400);
        }

        $file = SuratFile::query()->find($fileId);
        if (!$file) {
            throw new ApiException('File tidak ditemukan', 404);
        }

        // Kalau SEMUA markup di SEMUA halaman sudah dihapus, data['halaman']
        // jadi kosong -- hapus barisnya sama sekali alih-alih menyimpan
        // struktur kosong (lihat surat_file_annotation_save.php lama untuk
        // alasan lengkapnya soal bug json_encode array-vs-objek kosong).
        if (empty($data['halaman'])) {
            SuratFileAnnotation::query()->where('surat_file_id', $fileId)->delete();

            return response()->json(['success' => true]);
        }

        $authUser = $request->user();

        SuratFileAnnotation::query()->updateOrCreate(
            ['surat_file_id' => $fileId],
            ['data' => json_encode($data), 'diubah_oleh' => $authUser->nama],
        );

        return response()->json(['success' => true]);
    }

    /**
     * Normalisasi input upload banyak file sekaligus (field `files`) jadi
     * array of UploadedFile berurutan, sama seperti normalisasi_files_upload()
     * di surat_files.php lama (bentuk $_FILES['files'] array-of-arrays).
     *
     * @return UploadedFile[]
     */
    private function normalisasiFilesUpload(UploadedFile|array|null $filesRaw): array
    {
        if (!$filesRaw) {
            return [];
        }
        if (!is_array($filesRaw)) {
            $filesRaw = [$filesRaw];
        }

        return array_values(array_filter($filesRaw, fn ($f) => $f instanceof UploadedFile));
    }
}
