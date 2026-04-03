<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Notur\Cs2Modframework\Controllers\ModFrameworkController;

// Auto-prefixed: /api/client/notur/notur/cs2-modframework/

Route::prefix('servers/{server}')->group(function () {
    Route::get('/status', [ModFrameworkController::class, 'status']);
    Route::get('/versions', [ModFrameworkController::class, 'versions']);
    Route::post('/install', [ModFrameworkController::class, 'install']);
    Route::post('/uninstall', [ModFrameworkController::class, 'uninstall']);
});
