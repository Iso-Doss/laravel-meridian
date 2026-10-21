# Contributing

```bash
composer test
composer analyse
```

The interface lives in `resources/js` (React, TypeScript, Tailwind CSS v4, shadcn/ui) and is compiled into `dist/`, which is committed. The CI checks that `dist/` matches the sources.

```bash
npm ci
npm run build      # or npm run dev to rebuild on change
npm run typecheck
npm test
```

Texts go through `t('English text')` in React and `$t('English text')` in Blade; add their French translation to `lang/fr.json` (a test fails when one is missing).

## Security Vulnerabilities

Please report security vulnerabilities through the [security advisories](https://github.com/Iso-Doss/laravel-meridian/security/advisories/new) of the repository rather than in a public issue.

## Credits

- Inspired by [Laravel Horizon](https://github.com/laravel/horizon), whose tag extraction is adapted.
- Built on [spatie/package-skeleton-laravel](https://github.com/spatie/package-skeleton-laravel).

## License

Meridian is open-sourced software licensed under the [MIT license](https://github.com/Iso-Doss/laravel-meridian/blob/main/LICENSE.md).
