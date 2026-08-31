<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_file', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_id')->constrained('surat')->cascadeOnDelete();
            $table->integer('urutan')->default(0);
            $table->string('file_name', 255);
            $table->string('file_original_name', 255);
            $table->timestamp('created_at')->useCurrent();

            $table->index('surat_id', 'idx_surat_file_surat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_file');
    }
};
