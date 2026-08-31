<?php

use App\Livewire\StorageMonitor;
use Illuminate\Support\Facades\Route;

// Pemantauan kapasitas disk VPS (admin saja) -- fitur baru, tidak ada
// padanannya di proyek Flutter/PHP lama.
Route::get('/storage-server', StorageMonitor::class)->name('storage.index')->middleware(['auth', 'role:admin']);
