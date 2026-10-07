<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::inertia('/', 'home')->name('home');
});

require __DIR__.'/settings.php';
