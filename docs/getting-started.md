# Getting started

From a clean machine to a running local environment and a first change.

## Requirements

- PHP 8.4.27 or later, or PHP 8.5. Earlier 8.4 releases have a critical vulnerability (PHP2Shell);
  Composer refuses to start the app on them. See [gotchas.md](gotchas.md).
- Composer 2.
- Git.
- Optional: the [Symfony CLI](https://symfony.com/download) as a local web server.

Phase 0 needs no database; it arrives in phase 1.

## Install

```bash
git clone git@github.com:zabuto/buurtpreventie.git
cd buurtpreventie
git switch develop
composer install
```

Local settings and secrets go in `.env.local`, which stays out of Git. See
[configuration.md](configuration.md).

## Start

With the Symfony CLI:

```bash
symfony server:start
```

Without the Symfony CLI:

```bash
php -S localhost:8000 -t public
```

Then open `http://localhost:8000/api/v1/health` (the Symfony CLI prints its own address). The answer
is `{"status":"ok"}`.

## Test and check

| Command | What it does |
| --- | --- |
| `composer test` | All tests |
| `composer check` | Everything CI checks: Composer, security advisories, container, deprecations, code style, Rector, PHPStan with the architecture rules, tests |
| `composer fix` | Applies Rector and PHP-CS-Fixer |

What each check does: [testing.md](testing.md).

## Your first change

1. Read [architecture.md](architecture.md): the folder layout, the worked example and the rules.
2. Concepts such as round, buddy and round report are listed with their code names in
   [glossary.md](glossary.md).
3. Follow [Adding a feature](architecture.md#adding-a-feature).
4. Work on a `feature/<short-name>` branch from `develop` and deliver through a pull request.
   `composer check` must be green.
