<?php

use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuestController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

// School chess club: puzzles, lessons, register and leaderboard by grade tier
Route::get('/club', [ClubController::class, 'show'])->name('club');
Route::post('/club/progress', [ClubController::class, 'progress'])
    ->middleware(['auth:student', 'throttle:60,1'])
    ->name('club.progress');

// Students
Route::get('/join', [JoinController::class, 'code'])->name('join');
Route::post('/join', [JoinController::class, 'lookup'])->name('join.lookup');
Route::get('/join/{code}', [JoinController::class, 'pick'])->name('join.class');
Route::post('/join/{code}', [JoinController::class, 'login'])->name('join.login');
Route::post('/student/logout', [JoinController::class, 'logout'])->name('student.logout');

// Players with their own account
Route::get('/signup', [PlayerController::class, 'create'])->name('player.register');
Route::post('/signup', [PlayerController::class, 'store'])->middleware('throttle:10,1');
Route::get('/signin', [PlayerController::class, 'signIn'])->name('player.login');
Route::post('/signin', [PlayerController::class, 'authenticate']);
Route::get('/@/{username}', [PlayerController::class, 'show'])->name('player.show');

Route::middleware('auth:student')->group(function () {
    Route::get('/account', [PlayerController::class, 'edit'])->name('player.edit');
    Route::patch('/account', [PlayerController::class, 'update'])->name('player.update');
    Route::put('/account/password', [PlayerController::class, 'updatePassword'])->name('player.password');
    Route::delete('/account', [PlayerController::class, 'destroy'])->name('player.destroy');
});

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
    Route::post('/club/students/{student}/attendance', [ClubController::class, 'mark'])->name('club.attendance');
    Route::patch('/club/students/{student}', [ClubController::class, 'updateStudent'])->name('club.students.update');

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
