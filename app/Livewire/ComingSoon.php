<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

/** Placeholder sementara untuk halaman yang belum selesai dimigrasi. */
#[Layout('layouts.app')]
class ComingSoon extends Component
{
    public string $label = 'Halaman ini';

    public function render()
    {
        return view('livewire.coming-soon');
    }
}
