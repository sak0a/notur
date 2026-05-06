<?php

use Illuminate\Support\Facades\Route;
use Notur\Http\Controllers\ExtensionApiController;
use Notur\Http\Controllers\ExtensionRemotePushController;

Route::prefix('api/client/notur')
    ->middleware(['client-api'])
    ->group(function () {
        Route::get('/slots', [ExtensionApiController::class, 'slots']);
        Route::get('/extensions', [ExtensionApiController::class, 'extensions']);
        Route::get('/extensions/{extensionId}/settings', [ExtensionApiController::class, 'settings'])
            ->where('extensionId', '[a-z0-9\-]+/[a-z0-9\-]+');
        Route::get('/config', [ExtensionApiController::class, 'config']);
    });

Route::prefix('api/notur/dev')
    ->middleware(['api', 'notur.remote-push'])
    ->group(function () {
        Route::post('/push', [ExtensionRemotePushController::class, 'push']);
    });
