<?php

use Illuminate\Support\Facades\Route;
use Notur\FullExtension\Http\Controllers\ApiController;

Route::get('/status', [ApiController::class, 'status']);
