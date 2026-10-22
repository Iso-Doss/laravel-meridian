<?php

use Illuminate\Support\Facades\Schema;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Support\Migrations;

beforeEach(function () {
    config()->set('cache.default', 'array');
});

it('reports a healthy installation', function () {
    $this->artisan('meridian:snapshot')->assertSuccessful();

    $this->artisan('meridian:doctor')
        ->expectsOutputToContain('up to date')
        ->expectsOutputToContain('UUID')
        ->expectsOutputToContain('table jobs, default connection')
        ->expectsOutputToContain('meridian:snapshot ran')
        ->expectsOutputToContain('8 key(s) redacted')
        ->assertSuccessful();
});

it('tells which migrations have not run', function () {
    Schema::table('meridian_jobs', function ($table) {
        $table->dropIndex(['parent_uuid']);
        $table->dropColumn(['origin_type', 'origin', 'origin_user', 'parent_uuid']);
    });
    Migrations::flush();

    $this->artisan('meridian:doctor')
        ->expectsOutputToContain('the origin of the jobs (v1.12.0)')
        ->assertSuccessful();
});

it('fails when the tables are missing', function () {
    Schema::drop('jobs');

    $this->artisan('meridian:doctor')
        ->expectsOutputToContain('The table jobs is missing')
        ->expectsOutputToContain('1 problem(s) to fix')
        ->assertFailed();
});

it('warns about the risky settings', function () {
    config()->set('meridian.redact', []);
    config()->set('meridian.prometheus', ['enabled' => true, 'token' => null]);
    app()->detectEnvironment(fn () => 'production');
    Meridian::$authUsing = null;

    $this->artisan('meridian:doctor')
        ->expectsOutputToContain('Stored without redaction')
        ->expectsOutputToContain('Only reachable in the local environment')
        ->expectsOutputToContain('Enabled without MERIDIAN_PROMETHEUS_TOKEN')
        ->expectsOutputToContain('meridian:snapshot never ran')
        ->assertSuccessful();
});
