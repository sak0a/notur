<?php

use Illuminate\Support\Facades\Route;

// These routes are prefixed with: /api/client/notur/notur/hello-world/

Route::get('/greet', function () {
    return response()->json([
        'message' => 'Hello from Notur!',
        'extension' => 'notur/hello-world',
        'version' => '1.0.0',
    ]);
});

Route::get('/greet/{name}', function (string $name) {
    return response()->json([
        'message' => "Hello, {$name}!",
    ]);
});
