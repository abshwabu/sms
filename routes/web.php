<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Fallback route to serve the Vue 3 Single Page Application (SPA).
|
*/

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
