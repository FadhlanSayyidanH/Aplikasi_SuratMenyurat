<?php

use App\Livewire\ActivityLog\Index as ActivityLogIndex;
use Illuminate\Support\Facades\Route;

// Log Aktivitas (admin saja) -- migrasi dari
// lib/screens/activity_log_screen.dart.
Route::get('/log-aktivitas', ActivityLogIndex::class)->name('activity-log.index')->middleware(['auth', 'role:admin']);
