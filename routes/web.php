<?php

use Illuminate\Support\Facades\Route;
use IsoDoss\Meridian\Http\Controllers\BatchesController;
use IsoDoss\Meridian\Http\Controllers\DashboardController;
use IsoDoss\Meridian\Http\Controllers\JobsController;
use IsoDoss\Meridian\Http\Controllers\LocaleController;
use IsoDoss\Meridian\Http\Controllers\MetricsController;

// Group attributes come from config("meridian.route.web"), names are prefixed with "meridian.".

// Dashboard...
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Language of the interface...
Route::post('locale', [LocaleController::class, 'update'])->name('locale');

// Jobs...
Route::prefix('jobs')->name('jobs.')->controller(JobsController::class)->group(function () {
    Route::get('{status?}', 'index')->whereIn('status', ['pending', 'processing', 'completed', 'failed'])->name('index');
    Route::get('{uuid}', 'show')->whereUuid('uuid')->name('show');
    Route::post('{uuid}/retry', 'retry')->whereUuid('uuid')->name('retry');
    Route::delete('{uuid}', 'destroy')->whereUuid('uuid')->name('destroy');
});

// Batches...
Route::prefix('batches')->name('batches.')->controller(BatchesController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('{id}', 'show')->name('show');
    Route::post('{id}/retry', 'retry')->name('retry');
});

// Metrics...
Route::prefix('metrics')->name('metrics.')->controller(MetricsController::class)->group(function () {
    Route::get('{type?}', 'index')->whereIn('type', ['jobs', 'queues'])->name('index');
    Route::get('{type}/{name}', 'show')->whereIn('type', ['jobs', 'queues'])->where('name', '.*')->name('show');
});
