<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_disposisi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_id')->constrained('surat')->cascadeOnDelete();
            $table->string('role', 64);
            $table->text('catatan')->nullable();
            $table->string('diproses_oleh', 128)->nullable();
            $table->dateTime('diproses_at')->nullable();
            $table->unique(['surat_id', 'role'], 'uniq_surat_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_disposisi');
    }
};
