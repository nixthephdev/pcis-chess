<?php

use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuestController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

// Students
Route::get('/join', [JoinController::class, 'code'])->name('join');
Route::post('/join', [JoinController::class, 'lookup'])->name('join.lookup');
Route::get('/join/{code}', [JoinController::class, 'pick'])->name('join.class');
Route::post('/join/{code}', [JoinController::class, 'login'])->name('join.login');
Route::post('/student/logout', [JoinController::class, 'logout'])->name('student.logout');

// Guests can play too; their stars stay on the device.
Route::get('/quest', [QuestController::class, 'show'])->name('quest');
Route::post('/quest/progress', [QuestController::class, 'save'])
    ->middleware(['auth:student', 'throttle:60,1'])
    ->name('quest.progress');

// Teachers
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ClassroomController::class, 'index'])->name('dashboard');
    Route::post('/classrooms', [ClassroomController::class, 'store'])->name('classrooms.store');
    Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show'])->name('classrooms.show');
    Route::patch('/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update');
    Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy');
    Route::get('/classrooms/{classroom}/cards', [ClassroomController::class, 'cards'])->name('classrooms.cards');
    Route::post('/classrooms/{classroom}/students', [ClassroomController::class, 'addStudents'])->name('classrooms.students.store');

    Route::scopeBindings()->group(function () {
        Route::post('/classrooms/{classroom}/students/{student}/pin', [ClassroomController::class, 'resetPin'])->name('classrooms.students.pin');
        Route::delete('/classrooms/{classroom}/students/{student}', [ClassroomController::class, 'removeStudent'])->name('classrooms.students.destroy');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
