<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

/** Halaman depan publik -- migrasi dari lib/screens/landing_screen.dart. */
#[Layout('layouts.guest')]
class Landing extends Component
{
    public function render()
    {
        return view('livewire.landing');
    }
}
