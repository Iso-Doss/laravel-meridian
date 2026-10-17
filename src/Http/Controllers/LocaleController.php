<?php

namespace IsoDoss\Meridian\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use IsoDoss\Meridian\Support\Translator;

class LocaleController extends Controller
{
    /**
     * Remember the language chosen in the dashboard (one year).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(Translator::locales())],
        ]);

        return back()->withCookie(cookie(Translator::COOKIE, $validated['locale'], 60 * 24 * 365));
    }
}
