<?php

namespace IsoDoss\Meridian\Http\Controllers\Concerns;

use Closure;
use Illuminate\Contracts\View\View;
use Inertia\Inertia;
use Inertia\Response;
use IsoDoss\Meridian\Meridian;

trait RendersPages
{
    /**
     * Render a page with the configured interface: the Blade view receives
     * $data as is, the Inertia component receives $props($data).
     *
     * @param  array<string, mixed>  $data
     * @param  Closure(array<string, mixed>): array<string, mixed>  $props
     */
    protected function page(string $view, string $component, array $data, Closure $props): View|Response
    {
        if (Meridian::ui() === 'blade') {
            return view("meridian::{$view}", $data);
        }

        return Inertia::render($component, $props($data));
    }
}
