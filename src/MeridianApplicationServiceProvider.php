<?php

namespace Kakodjenou\Meridian;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MeridianApplicationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->gate();

        Meridian::auth(function ($request) {
            return app()->environment('local')
                || Gate::check('viewMeridian', [$request->user()]);
        });
    }

    /**
     * Register the Meridian gate.
     *
     * This gate determines who can access Meridian in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewMeridian', function ($user = null) {
            return false;
        });
    }
}
