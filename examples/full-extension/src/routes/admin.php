<?php

use Illuminate\Support\Facades\Route;
use Notur\FullExtension\Http\Controllers\AdminController;

Route::get('/', [AdminController::class, 'index']);
