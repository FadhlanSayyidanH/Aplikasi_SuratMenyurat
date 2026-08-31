<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama'])]
class BagKeluar extends Model
{
    protected $table = 'bag_keluar';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** Anggota datar (kasi_grup_id NULL) -- Kabag/Kabagtu/Turmin/Kasubditbinum dst. */
    public function anggotaDatar()
    {
        return $this->hasMany(BagMember::class, 'bag_id')->whereNull('kasi_grup_id')->orderBy('urutan');
    }

    public function kasiGrup()
    {
        return $this->hasMany(BagKasiGrup::class, 'bag_id')->orderBy('urutan');
    }

    public function anggota()
    {
        return $this->hasMany(BagMember::class, 'bag_id');
    }
}
