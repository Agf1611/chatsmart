<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('generate', function () {
        return Artisan::call('storage:link');
    })->name('generate');

    Route::get('/clear-cache', function () {
        return Artisan::call('optimize:clear');
    })->name('cache.clear');
});
?>
