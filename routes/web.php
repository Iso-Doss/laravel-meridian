<?php

use Illuminate\Support\Facades\Route;
use IsoDoss\Meridian\Http\Controllers\AuditLogController;
use IsoDoss\Meridian\Http\Controllers\BatchesController;
use IsoDoss\Meridian\Http\Controllers\DashboardController;
use IsoDoss\Meridian\Http\Controllers\ErrorsController;
use IsoDoss\Meridian\Http\Controllers\JobsController;
use IsoDoss\Meridian\Http\Controllers\LocaleController;
use IsoDoss\Meridian\Http\Controllers\MetricsController;
use IsoDoss\Meridian\Http\Controllers\MonitoringController;
use IsoDoss\Meridian\Http\Controllers\ScheduleController;
use IsoDoss\Meridian\Models\MonitoredJob;

// Group attributes come from config("meridian.route.web"), names are prefixed with "meridian.".

// Dashboard...
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Language of the interface...
Route::post('locale', [LocaleController::class, 'update'])->name('locale');

// Jobs...
Route::prefix('jobs')->name('jobs.')->controller(JobsController::class)->group(function () {
    Route::get('export', 'export')->name('export');
    Route::get('{status?}', 'index')->whereIn('status', MonitoredJob::STATUSES)->name('index');
    Route::post('retry', 'retryAll')->name('retry-all');
    Route::get('{uuid}', 'show')->whereUuid('uuid')->name('show');
    Route::post('{uuid}/retry', 'retry')->whereUuid('uuid')->name('retry');
    Route::post('{uuid}/retry-edited', 'retryEdited')->whereUuid('uuid')->name('retry-edited');
    Route::delete('{uuid}', 'destroy')->whereUuid('uuid')->name('destroy');
});

// Monitored tags...
Route::prefix('monitoring')->name('monitoring.')->controller(MonitoringController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::delete('/', 'destroy')->name('destroy');
});

// Failed jobs grouped by cause...
Route::get('errors', [ErrorsController::class, 'index'])->name('errors.index');
Route::get('errors/export', [ErrorsController::class, 'export'])->name('errors.export');

// Scheduled tasks...
Route::get('schedule', [ScheduleController::class, 'index'])->name('schedule.index');
Route::get('schedule/{id}', [ScheduleController::class, 'show'])->whereNumber('id')->name('schedule.show');

// Audit log...
Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');

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
