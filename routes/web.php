<?php

use App\Http\Controllers\Radar\ArchiveController;
use App\Http\Controllers\Radar\CalendarController;
use App\Http\Controllers\Radar\DismissController;
use App\Http\Controllers\Radar\DismissedContestsController;
use App\Http\Controllers\Radar\EnrollmentController;
use App\Http\Controllers\Radar\ExamResultController;
use App\Http\Controllers\Radar\PinController;
use App\Http\Controllers\Radar\PreferencesController;
use App\Http\Controllers\Radar\RadarPageController;
use App\Http\Controllers\Radar\SweepController;
use Illuminate\Support\Facades\Route;

// Sem cadastro público: '/' é a tela principal, autenticada. 'dashboard'
// fica como alias (Fortify usa esse path como redirect pós-login via
// config/fortify.php 'home') apontando para a mesma página.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [RadarPageController::class, 'index'])->name('home');
    Route::get('dashboard', [RadarPageController::class, 'index'])->name('dashboard');

    // docs/02-pipeline.md → "Endpoints". GET/{run} é o polling do front (T4).
    Route::post('sweeps', [SweepController::class, 'store'])->name('sweeps.store');
    Route::get('sweeps/{run}', [SweepController::class, 'show'])->name('sweeps.show');

    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::patch('preferences/states', [PreferencesController::class, 'updateStates'])->name('preferences.states');

    Route::post('contests/{contest}/pin', [PinController::class, 'store'])->name('contests.pin.store');
    Route::delete('contests/{contest}/pin', [PinController::class, 'destroy'])->name('contests.pin.destroy');
    Route::patch('pins', [PinController::class, 'reorder'])->name('pins.reorder');

    // docs/00-spec.md → "Minha situação no concurso" e "Descartado".
    Route::patch('contests/{contest}/enrollment', [EnrollmentController::class, 'update'])->name('contests.enrollment.update');
    Route::post('contests/{contest}/dismiss', [DismissController::class, 'store'])->name('contests.dismiss.store');
    Route::post('contests/{contest}/restore', [DismissController::class, 'restore'])->name('contests.dismiss.restore');
    Route::get('contests/dismissed', [DismissedContestsController::class, 'index'])->name('contests.dismissed');

    // Meu resultado na prova (T7, docs/03 → "Resultado").
    Route::put('contests/{contest}/result', [ExamResultController::class, 'update'])->name('contests.result.update');
    Route::delete('contests/{contest}/result', [ExamResultController::class, 'destroy'])->name('contests.result.destroy');
    Route::get('results/subjects', [ExamResultController::class, 'subjects'])->name('results.subjects');

    // Tudo que saiu da mesa: descartados + expirados pela regra das 24h.
    Route::get('archive', [ArchiveController::class, 'index'])->name('archive.index');
});

require __DIR__.'/settings.php';
