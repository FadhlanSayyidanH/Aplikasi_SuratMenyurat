<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surat_id', 'waktu', 'username', 'nama', 'aksi', 'keterangan', 'ip_address', 'user_agent'])]
class ActivityLog extends Model
{
    protected $table = 'activity_log';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['waktu' => 'datetime'];
    }
}
