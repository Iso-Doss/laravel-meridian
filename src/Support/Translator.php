<?php

namespace IsoDoss\Meridian\Support;

use Illuminate\Http\Request;

/**
 * Translations of the Meridian interface.
 *
 * Kept apart from the application translations (lang/*.json of the package
 * are never merged into the application ones): keys are the English texts.
 */
class Translator
{
    public const COOKIE = 'meridian_locale';

    /**
     * @var array<string, array<string, string>>
     */
    protected static array $loaded = [];

    /**
     * @return array<int, string>
     */
    public static function locales(): array
    {
        $locales = array_values(array_filter((array) config('meridian.locales', ['en'])));

        return $locales === [] ? ['en'] : $locales;
    }

    /**
     * The language of the current visitor: their choice, else the
     * application locale when offered, else the first offered language.
     */
    public static function locale(?Request $request = null): string
    {
        $locales = static::locales();
        $chosen = $request?->cookie(self::COOKIE);

        if (is_string($chosen) && in_array($chosen, $locales, true)) {
            return $chosen;
        }

        $application = app()->getLocale();

        if (in_array($application, $locales, true)) {
            return $application;
        }

        $language = substr($application, 0, 2);

        return in_array($language, $locales, true) ? $language : $locales[0];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $locale): array
    {
        if (isset(static::$loaded[$locale])) {
            return static::$loaded[$locale];
        }

        $path = dirname(__DIR__, 2)."/lang/{$locale}.json";

        return static::$loaded[$locale] = is_file($path)
            ? (array) json_decode((string) file_get_contents($path), true)
            : [];
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= static::locale(request());
        $text = static::messages($locale)[$key] ?? $key;

        // Longest names first, so that ":to" never eats the beginning of ":total".
        uksort($replace, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        foreach ($replace as $name => $value) {
            $text = str_replace(':'.$name, (string) $value, $text);
        }

        return $text;
    }
}
