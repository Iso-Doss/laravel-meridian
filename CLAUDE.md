# Règles du projet Meridian

## Nettoyage après les tests (très important)

Après chaque série de tests, de builds ou de vérifications, supprimer les fichiers temporaires créés dans le dossier du projet :

- `build/` (rapport JUnit et cache PHPStan)
- `.phpunit.cache/`
- `docs/.vitepress/dist/` et `docs/.vitepress/cache/` (build local de la documentation)
- tout autre fichier ou dossier créé pour un test ou un essai

```bash
rm -rf build .phpunit.cache docs/.vitepress/dist docs/.vitepress/cache
```

Ne jamais supprimer : `vendor/`, `node_modules/`, `docs/node_modules/`, `composer.lock`, `.idea/`, ni `dist/` (les assets compilés, versionnés).

Les scripts, applications de démo et fichiers d'essai vont dans le dossier temporaire de la session, jamais dans le projet. Vérifier avec `git status --short --ignored` qu'il ne reste rien avant de rendre la main.

## Migrations

- Toujours vérifier `Schema::hasTable()` avant de créer une table et `Schema::hasColumn()` avant d'ajouter une colonne, ainsi qu'avant de les supprimer dans `down()`.
- La clé primaire des tables passe par `PrimaryKey::define($table)` (UUID par défaut, configurable avec `meridian.primary_key`).
- Les écritures passent par `PrimaryKey::fill()` / `fillMany()` pour générer la clé quand la table n'est pas auto-incrémentée.

## Commits et releases

- Commiter, pousser ou publier uniquement quand l'utilisateur le demande.
- Une release se prépare en brouillon (« prépare la release vX ») et ne se publie que sur demande explicite (« publie la release vX »).

## Vérifications avant de rendre la main

```bash
vendor/bin/pest
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
npx tsc --noEmit -p .
npm run build   # dist/ doit correspondre aux sources
```

Puis appliquer la règle de nettoyage ci-dessus.
