<?php

namespace IsoDoss\Meridian\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Support\PrometheusExporter;

class PrometheusController extends Controller
{
    /**
     * The metrics in the Prometheus text format. Scrapers authenticate with
     * the "prometheus.token" bearer token; without a token, the dashboard
     * authorization applies.
     */
    public function __invoke(Request $request, PrometheusExporter $exporter): Response
    {
        abort_unless(config('meridian.prometheus.enabled', false), 404);

        $token = (string) config('meridian.prometheus.token', '');

        if ($token !== '') {
            abort_unless(hash_equals($token, (string) $request->bearerToken()), 401);
        } else {
            abort_unless(Meridian::check($request), 403);
        }

        return response($exporter->render(), 200, ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8']);
    }
}
