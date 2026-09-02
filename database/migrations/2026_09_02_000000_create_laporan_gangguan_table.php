<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan gangguan/kendala dari user (widget mengambang, menggantikan
 * tombol WhatsApp "Kontak Admin" lama). Ditinjau admin lewat
 * App\Livewire\LaporanGangguanAdmin. Pola tabel mengikuti `pengumuman`
 * (tanpa updated_at) -- lihat 2026_08_28_075950_create_pengumuman_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_gangguan', function (Blueprint $table) {
            $table->id();
            $table->string('pelapor_username', 100);
            $table->string('pelapor_nama', 100);
            $table->string('kategori', 20);           // bug | kendala | saran
            $table->text('pesan');
            $table->string('halaman', 255)->nullable();     // path halaman saat lapor
            $table->string('user_agent', 255)->nullable();
            $table->string('status', 20)->default('baru');  // baru | selesai
            $table->string('ditangani_oleh', 100)->nullable();
            $table->timestamp('ditangani_pada')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_gangguan');
    }
};
