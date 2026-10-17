<?php

namespace IsoDoss\Meridian\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use IsoDoss\Meridian\Meridian;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Meridian::check($request), 403);

        return $next($request);
    }
}
