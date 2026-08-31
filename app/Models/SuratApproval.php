<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surat_id', 'urutan', 'role', 'status', 'instruksi', 'catatan', 'diproses_oleh', 'diproses_at'])]
class SuratApproval extends Model
{
    protected $table = 'surat_approval';

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
