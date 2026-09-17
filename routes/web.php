<?php

use App\Http\Controllers\Radar\SweepController;
use Illuminate\Support\Facades\Route;

// Sem cadastro público: '/' é a tela principal, autenticada. 'dashboard'
// fica como alias (Fortify usa esse path como redirect pós-login via
// config/fortify.php 'home') apontando para a mesma página até a T4/T5
// trocar o placeholder pela tela de verdade.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('/', 'radar/index')->name('home');
    Route::inertia('dashboard', 'radar/index')->name('dashboard');

    // docs/02-pipeline.md → "Endpoints". GET/{run} é o polling do front (T4).
    Route::post('sweeps', [SweepController::class, 'store'])->name('sweeps.store');
    Route::get('sweeps/{run}', [SweepController::class, 'show'])->name('sweeps.show');
});

require __DIR__.'/settings.php';
