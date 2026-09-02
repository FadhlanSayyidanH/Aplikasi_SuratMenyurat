<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `surat_disposisi.ditambah_oleh` -- nama akun yang MENERUSKAN baris
 * disposisi ini (Kabag lewat BagService::teruskanDisposisiKabag(), atau
 * sesama Penerima Disposisi lewat teruskanDisposisiAntarAnggota()). NULL
 * untuk baris yang dibuat langsung oleh gerbang Kasubdit (SuratReview::simpan()).
 *
 * Dipakai untuk menegakkan aturan: seorang anggota hanya boleh MEMBATALKAN
 * (uncheck) terusan yang DIA sendiri buat dan yang penerimanya belum
 * mengisi disposisi -- lihat docs/features/surat-masuk.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_disposisi', function (Blueprint $table) {
            $table->string('ditambah_oleh', 128)->nullable()->after('diproses_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('surat_disposisi', function (Blueprint $table) {
            $table->dropColumn('ditambah_oleh');
        });
    }
};
