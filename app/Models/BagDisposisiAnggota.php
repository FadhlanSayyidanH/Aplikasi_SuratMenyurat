<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['bag_id', 'user_id', 'urutan'])]
class BagDisposisiAnggota extends Model
{
    protected $table = 'bag_disposisi_anggota';

    public $timestamps = false;

    public function bag()
    {
        return $this->belongsTo(BagMasuk::class, 'bag_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
