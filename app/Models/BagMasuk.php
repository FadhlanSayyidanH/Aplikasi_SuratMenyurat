<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'turmin_user_id', 'kasubdit_user_id', 'kabag_user_id'])]
class BagMasuk extends Model
{
    protected $table = 'bag_masuk';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function turmin()
    {
        return $this->belongsTo(User::class, 'turmin_user_id');
    }

    public function kasubdit()
    {
        return $this->belongsTo(User::class, 'kasubdit_user_id');
    }

    public function kabag()
    {
        return $this->belongsTo(User::class, 'kabag_user_id');
    }

    public function anggotaMasuk()
    {
        return $this->hasMany(BagDisposisiAnggota::class, 'bag_id')->orderBy('urutan');
    }
}
