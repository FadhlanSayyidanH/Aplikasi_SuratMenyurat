<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat', function (Blueprint $table) {
            $table->id();
            $table->enum('jenis', ['masuk', 'keluar']);
            $table->string('nomor_surat', 100)->nullable();
            $table->date('tanggal');
            // Khusus Surat Masuk -- lihat komentar di schema.sql lama untuk
            // beda dengan `tanggal` (tanggal surat itu sendiri diterbitkan).
            $table->date('tanggal_input_sistem')->nullable();
            $table->string('perihal', 255);
            $table->string('nama_pengaju', 150)->nullable();
            $table->string('klasifikasi', 50)->nullable();
            $table->text('disposisi')->nullable();
            $table->string('kabag_dituju', 64)->nullable();
            $table->string('asal_tujuan', 255)->nullable();
            $table->string('sifat', 50)->nullable();
            $table->text('keterangan')->nullable();
            $table->enum('status', ['menunggu', 'disetujui', 'ditolak'])->default('menunggu');
            $table->text('catatan_proses')->nullable();
            $table->string('diproses_oleh', 100)->nullable();
            $table->timestamp('diproses_at')->nullable();
            $table->timestamp('konfirmasi_kaur_at')->nullable();
            // Khusus Surat Masuk -- Kasubdit spesifik yang berhak melihat/
            // mengklaim gerbang 'Kasubdit', diisi otomatis lewat
            // deteksi_kasubdit_untuk_turmin(). Null = antrian bersama.
            $table->foreignId('kasubdit_gerbang_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['jenis', 'status'], 'idx_jenis_status');
            $table->index('kabag_dituju', 'idx_kabag_dituju');
            $table->index('created_at', 'idx_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat');
    }
};
