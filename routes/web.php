<?php

use Illuminate\Support\Facades\Route;
use Kakodjenou\Meridian\Http\Controllers\BatchesController;
use Kakodjenou\Meridian\Http\Controllers\DashboardController;
use Kakodjenou\Meridian\Http\Controllers\JobsController;
use Kakodjenou\Meridian\Http\Controllers\MetricsController;

Route::prefix('api')->name('api.')->group(function () {
    Route::get('stats', [DashboardController::class, 'apiStats'])->name('stats');
    Route::get('workload', [DashboardController::class, 'apiWorkload'])->name('workload');
    Route::get('jobs/{status?}', [JobsController::class, 'apiIndex'])->whereIn('status', ['pending', 'processing', 'completed', 'failed'])->name('jobs.index');
    Route::get('job/{uuid}', [JobsController::class, 'apiShow'])->name('jobs.show');
});

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('jobs/{status?}', [JobsController::class, 'index'])->whereIn('status', ['pending', 'processing', 'completed', 'failed'])->name('jobs.index');
Route::get('job/{uuid}', [JobsController::class, 'show'])->name('jobs.show');
Route::post('job/{uuid}/retry', [JobsController::class, 'retry'])->name('jobs.retry');
Route::delete('job/{uuid}', [JobsController::class, 'destroy'])->name('jobs.destroy');

Route::get('batches', [BatchesController::class, 'index'])->name('batches.index');
Route::get('batches/{id}', [BatchesController::class, 'show'])->name('batches.show');
Route::post('batches/{id}/retry', [BatchesController::class, 'retry'])->name('batches.retry');

Route::get('metrics/{type?}', [MetricsController::class, 'index'])->whereIn('type', ['jobs', 'queues'])->name('metrics.index');
Route::get('metrics/{type}/{name}', [MetricsController::class, 'show'])->whereIn('type', ['jobs', 'queues'])->where('name', '.*')->name('metrics.show');
