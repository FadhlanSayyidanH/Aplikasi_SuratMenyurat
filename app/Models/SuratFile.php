<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surat_id', 'urutan', 'file_name', 'file_original_name'])]
class SuratFile extends Model
{
    protected $table = 'surat_file';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime:Y-m-d H:i:s'];
    }

    public function surat()
    {
        return $this->belongsTo(Surat::class, 'surat_id');
    }

    public function annotation()
    {
        return $this->hasOne(SuratFileAnnotation::class, 'surat_file_id');
    }
}
