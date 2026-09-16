<?php

use Illuminate\Support\Facades\Route;

// Sem cadastro público: '/' é a tela principal, autenticada. 'dashboard'
// fica como alias (Fortify usa esse path como redirect pós-login via
// config/fortify.php 'home') apontando para a mesma página até a T4/T5
// trocar o placeholder pela tela de verdade.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('/', 'radar/index')->name('home');
    Route::inertia('dashboard', 'radar/index')->name('dashboard');
});

require __DIR__.'/settings.php';
