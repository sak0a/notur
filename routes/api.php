<?php

use Illuminate\Support\Facades\Route;
use Notur\Http\Controllers\ExtensionApiController;

Route::prefix('api/client/notur')
    ->middleware(['client-api'])
    ->group(function () {
        Route::get('/slots', [ExtensionApiController::class, 'slots']);
        Route::get('/extensions', [ExtensionApiController::class, 'extensions']);
        Route::get('/config', [ExtensionApiController::class, 'config']);
    });
