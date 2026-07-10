<?php

use App\Livewire\Courses;
use App\Livewire\Library;
use App\Livewire\MyCourses;
use App\Livewire\ShowCourse;
use App\Livewire\ShowPage;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/library');

// Public learner frontend — the reader for published wiki pages.
Route::get('/library', Library::class)->name('library');
Route::get('/wiki/{slug}', ShowPage::class)->name('wiki.show');

// Courses — guided paths through the wiki.
Route::get('/courses', Courses::class)->name('courses');
Route::get('/courses/{slug}', ShowCourse::class)->name('courses.show');
Route::get('/my-courses', MyCourses::class)->middleware(['auth', 'verified'])->name('courses.mine');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
