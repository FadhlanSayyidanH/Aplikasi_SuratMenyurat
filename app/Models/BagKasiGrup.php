<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['bag_id', 'kasi_user_id', 'urutan'])]
class BagKasiGrup extends Model
{
    protected $table = 'bag_kasi_grup';

    public $timestamps = false;

    public function bag()
    {
        return $this->belongsTo(BagKeluar::class, 'bag_id');
    }

    public function kasi()
    {
        return $this->belongsTo(User::class, 'kasi_user_id');
    }

    public function kaur()
    {
        return $this->hasMany(BagMember::class, 'kasi_grup_id')->orderBy('urutan');
    }
}
