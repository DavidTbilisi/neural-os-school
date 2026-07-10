<?php

use App\Http\Controllers\SketchpadController;
use App\Livewire\Courses;
use App\Livewire\Gyms;
use App\Livewire\Library;
use App\Livewire\MyCourses;
use App\Livewire\PlayGym;
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

// Per-course Excalidraw whiteboard (one saved scene per learner per course).
Route::middleware('auth')->group(function () {
    Route::get('/courses/{slug}/sketchpad', [SketchpadController::class, 'show'])->name('courses.sketchpad');
    Route::post('/courses/{slug}/drawing', [SketchpadController::class, 'save'])->name('courses.drawing.save');
});

// Gyms — timed recognition drills. Browsing is public; playing (which logs
// per-learner telemetry) requires an account.
Route::get('/gyms', Gyms::class)->name('gyms');
Route::get('/gyms/{slug}', PlayGym::class)->middleware('auth')->name('gyms.play');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
