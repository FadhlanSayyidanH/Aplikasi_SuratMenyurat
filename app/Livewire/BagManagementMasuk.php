<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;

/** Panel admin "Manajemen Bag Surat Masuk" -- lihat BagManagementBase untuk seluruh logika. */
#[Layout('layouts.app', ['title' => 'Manajemen Bag Surat Masuk'])]
class BagManagementMasuk extends BagManagementBase
{
    protected function modeValue(): string
    {
        return 'masuk';
    }
}
