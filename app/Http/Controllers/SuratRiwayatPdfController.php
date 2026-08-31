<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Surat;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Unduh PDF ringkasan riwayat satu surat (metadata + rantai approval +
 * disposisi + log aktivitas) -- migrasi dari lib/utils/riwayat_pdf.dart
 * (dulu dirender di klien Flutter lewat syncfusion_flutter_pdf, dari
 * tombol "Unduh PDF" pada dialog Riwayat Aktivitas di surat_review_screen.
 * dart). Di web dirender SERVER-SIDE lewat barryvdh/laravel-dompdf dari
 * Blade view resources/views/pdf/riwayat.blade.php, lalu di-stream sebagai
 * unduhan -- tidak ada state/komponen Livewire, cukup satu route GET.
 */
class SuratRiwayatPdfController extends Controller
{
    public function __invoke(Request $request, Surat $surat)
    {
        $this->authorizeAccess($surat);

        // Diambil lewat DB::table langsung (bukan relasi Eloquent $surat->
        // approval()/disposisi()) supaya konsisten dengan SuratReview::
        // approvalRows()/disposisiRows() -- PENTING untuk kolom `disposisi`
        // khususnya: tabel `surat` sendiri punya kolom mentah bernama sama
        // ("disposisi", string gabungan-koma warisan lama), yang akan
        // menutupi/collide dengan relasi Eloquent $surat->disposisi kalau
        // dipanggil sebagai properti magic getter.
        $approval = DB::table('surat_approval')->where('surat_id', $surat->id)->orderBy('urutan')->get();
        $disposisi = DB::table('surat_disposisi')->where('surat_id', $surat->id)->orderBy('id')->get();
        $riwayat = ActivityLog::where('surat_id', $surat->id)
            ->orderBy('waktu')->orderBy('id')
            ->get(['waktu', 'username', 'nama', 'aksi', 'keterangan']);

        $pdf = Pdf::loadView('pdf.riwayat', [
            'surat' => $surat,
            'approval' => $approval,
            'disposisi' => $disposisi,
            'riwayat' => $riwayat,
        ])->setPaper('a4');

        $namaFile = 'Riwayat-'.($surat->nomor_surat ?: 'Surat-'.$surat->id);
        $namaFile = preg_replace('/[^A-Za-z0-9._-]+/', '-', $namaFile).'.pdf';

        return $pdf->download($namaFile);
    }

    /**
     * Batas keamanan sama seperti SuratController::list() &
     * SuratReview::authorizeAccess() -- akun bukan admin/pimpinan hanya
     * boleh mengunduh riwayat surat yang benar-benar melibatkan mereka
     * (salah satu tahap approval, tujuan disposisi, atau pernah memproses
     * tahapnya).
     */
    private function authorizeAccess(Surat $surat): void
    {
        $user = Auth::user();
        if (in_array($user->role, ['admin', 'pimpinan'], true)) {
            return;
        }

        $nama = $user->nama;
        $authorized = DB::table('surat_disposisi')->where('surat_id', $surat->id)->where('role', $nama)->exists()
            || DB::table('surat_approval')->where('surat_id', $surat->id)->where('role', $nama)->exists()
            || DB::table('surat_approval')->where('surat_id', $surat->id)->where('diproses_oleh', $nama)->exists();

        abort_unless($authorized, 403, 'Anda tidak berhak mengunduh riwayat surat ini.');
    }
}
