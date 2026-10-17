<?php

use Illuminate\Support\Facades\Route;
use IsoDoss\Meridian\Http\Controllers\AssetsController;

// Compiled assets of the Inertia interface, served without session nor authorization.
Route::get('assets/{path}', [AssetsController::class, 'show'])->where('path', '.*')->name('assets');
