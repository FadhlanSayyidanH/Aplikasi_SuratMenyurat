<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;

/** Panel admin "Manajemen Bag Surat Keluar" -- lihat BagManagementBase untuk seluruh logika. */
#[Layout('layouts.app', ['title' => 'Manajemen Bag Surat Keluar'])]
class BagManagementKeluar extends BagManagementBase
{
    protected function modeValue(): string
    {
        return 'keluar';
    }
}
