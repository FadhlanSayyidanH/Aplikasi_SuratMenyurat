<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Markup "coret-coret" (gambar bebas + catatan tempel) di atas file PDF --
// satu baris per surat_file (upsert, bukan riwayat versi).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_file_annotation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_file_id')->unique('uniq_surat_file_annotation_file')
                ->constrained('surat_file')->cascadeOnDelete();
            $table->longText('data');
            $table->string('diubah_oleh', 128)->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_file_annotation');
    }
};
