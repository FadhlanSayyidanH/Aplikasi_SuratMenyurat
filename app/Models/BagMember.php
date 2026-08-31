<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['bag_id', 'user_id', 'urutan', 'kasi_grup_id'])]
class BagMember extends Model
{
    protected $table = 'bag_member';

    public $timestamps = false;

    public function bag()
    {
        return $this->belongsTo(BagKeluar::class, 'bag_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function kasiGrup()
    {
        return $this->belongsTo(BagKasiGrup::class, 'kasi_grup_id');
    }
}
