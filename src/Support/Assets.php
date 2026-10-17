<?php

namespace IsoDoss\Meridian\Support;

/**
 * The compiled assets of the Inertia interface (dist/), served by Meridian.
 */
class Assets
{
    public const ENTRY = 'resources/js/app.tsx';

    /**
     * @var array<string, array<string, mixed>>|null
     */
    protected static ?array $manifest = null;

    public static function directory(): string
    {
        return dirname(__DIR__, 2).'/dist';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function manifest(): array
    {
        if (static::$manifest !== null) {
            return static::$manifest;
        }

        $path = static::directory().'/.vite/manifest.json';

        return static::$manifest = is_file($path)
            ? (array) json_decode((string) file_get_contents($path), true)
            : [];
    }

    /**
     * Changes whenever the assets are rebuilt (Inertia asset version).
     */
    public static function version(): ?string
    {
        $path = static::directory().'/.vite/manifest.json';

        return is_file($path) ? hash_file('xxh128', $path) : null;
    }

    /**
     * Files of the entry point, relative to dist/.
     *
     * @return array{js: array<int, string>, css: array<int, string>}
     */
    public static function entry(): array
    {
        $entry = static::manifest()[static::ENTRY] ?? null;

        if ($entry === null) {
            return ['js' => [], 'css' => []];
        }

        return [
            'js' => [$entry['file']],
            'css' => array_values($entry['css'] ?? []),
        ];
    }

    /**
     * Absolute path of a compiled asset (a file of dist/assets/), or null when
     * it does not exist or tries to leave that directory.
     */
    public static function path(string $file): ?string
    {
        $directory = realpath(static::directory().'/assets');
        $path = realpath(static::directory().'/assets/'.ltrim($file, '/'));

        if ($directory === false || $path === false || ! str_starts_with($path, $directory.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            return null;
        }

        return $path;
    }

    public static function contentType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'js', 'mjs' => 'application/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'svg' => 'image/svg+xml',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'png' => 'image/png',
            'json', 'map' => 'application/json',
            default => 'application/octet-stream',
        };
    }
}
