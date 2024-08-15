<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::post('/test', function () {
    return ['Laravel' => app()->version()];
});

require __DIR__.'/auth.php';
