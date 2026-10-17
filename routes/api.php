<?php

use Illuminate\Support\Facades\Route;
use IsoDoss\Meridian\Http\Controllers\DashboardController;
use IsoDoss\Meridian\Http\Controllers\JobsController;
use IsoDoss\Meridian\Http\Controllers\MonitoringController;

// Group attributes come from config("meridian.route.api"), names are prefixed with "meridian.api.".

// Dashboard...
Route::controller(DashboardController::class)->group(function () {
    Route::get('stats', 'apiStats')->name('stats');
    Route::get('workload', 'apiWorkload')->name('workload');
});

// Jobs...
Route::prefix('jobs')->name('jobs.')->controller(JobsController::class)->group(function () {
    Route::get('{status?}', 'apiIndex')->whereIn('status', ['pending', 'processing', 'completed', 'failed'])->name('index');
    Route::get('{uuid}', 'apiShow')->whereUuid('uuid')->name('show');
});

// Monitored tags...
Route::get('monitoring', [MonitoringController::class, 'apiIndex'])->name('monitoring.index');
