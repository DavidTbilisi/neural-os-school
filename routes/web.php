<?php

use App\Livewire\Library;
use App\Livewire\ShowPage;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/library');

// Public learner frontend — the reader for published wiki pages.
Route::get('/library', Library::class)->name('library');
Route::get('/wiki/{slug}', ShowPage::class)->name('wiki.show');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
