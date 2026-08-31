<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surat_file_id', 'data', 'diubah_oleh'])]
class SuratFileAnnotation extends Model
{
    protected $table = 'surat_file_annotation';

    const CREATED_AT = null;

    protected function casts(): array
    {
        return ['updated_at' => 'datetime'];
    }

    public function suratFile()
    {
        return $this->belongsTo(SuratFile::class, 'surat_file_id');
    }
}
