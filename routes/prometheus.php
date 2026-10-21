<?php

use Illuminate\Support\Facades\Route;
use IsoDoss\Meridian\Http\Controllers\PrometheusController;

// Metrics for Prometheus / OpenTelemetry, without session: scrapers use the "prometheus.token" bearer token.
Route::get('prometheus', PrometheusController::class)->name('prometheus');
