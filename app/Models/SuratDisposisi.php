<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surat_id', 'role', 'catatan', 'diproses_oleh', 'diproses_at', 'ditambah_oleh'])]
class SuratDisposisi extends Model
{
    protected $table = 'surat_disposisi';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['diproses_at' => 'datetime:Y-m-d H:i:s'];
    }

    public function surat()
    {
        return $this->belongsTo(Surat::class, 'surat_id');
    }
}
