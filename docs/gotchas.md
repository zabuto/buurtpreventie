# Gotchas

Things that are not obvious and cost time when you do not know them.

## PHP version

- **A PHP minimum that moves up.** `require.php` in `composer.json` is the oldest PHP release without
  a known critical vulnerability, and rises with each new one. `vendor/composer/platform_check.php`
  stops the app on an older version with "Your Composer dependencies require a PHP version >= …".
  The fix is upgrading PHP, never lowering the requirement.
- **PHP 8.5 locally, PHP 8.4 as the target.** `config.platform.php` is the minimum 8.4, so Composer picks
  packages that run on 8.4 even when you have 8.5 locally. PHPStan checks with `phpVersion: 80400`
  that the code uses no syntax that only exists in 8.5. CI runs on 8.4.

## PHPStan

- **PHPStan reads the Symfony container from `var/cache/dev/`.** After a new service or a config
  change, run `symfony console cache:warmup` first; `composer check` does that itself.
- **Symfony generates `config/reference.php`** on every cache warm-up, with different contents per
  environment (`dev` adds `when@dev` blocks). It only helps autocompletion of PHP config, so it stays
  out of Git and out of PHPStan, Mago and Rector.

## Mago

- **The Composer package downloads the Mago binary** for the platform on first use, from the Mago
  releases on GitHub. Without network access on a fresh install, the Mago checks cannot run.
- **Recipe files are excluded** from Mago, PHPStan and Rector; see
  [code-style.md](code-style.md#symfony-recipe-files).
- **Global functions and constants are written fully qualified**: `\sprintf()`, `\PHP_BINARY`. Mago
  fails the check on an unqualified call, because PHP first looks for the name in the current
  namespace.

## Rector

- **A handler keeps its input parameter, even while the input is empty.** Rector would remove an
  unused parameter; `rector.php` skips that rule for `src/*/UseCase/*`, because REST and MCP pass the
  same input.
- **The Rector cache can hide a finding.** Rector caches its result per file and only throws the cache
  away when that file, its configuration or the Symfony container XML (`var/cache/dev/...Container.xml`)
  changes. A rule that newly applies because of a change elsewhere stays silent until then. When a
  result looks too clean, run `vendor/bin/rector process --dry-run --clear-cache`.

## Doctrine

- **A refreshed entity cannot hold a readonly object.** Doctrine sets a readonly property only to the
  value it has, and compares objects by identity: a `Uuid` or `DateTimeImmutable` read back is
  another object, so the refresh fails with "Attempting to change readonly property". A query with
  `Query::HINT_REFRESH`, such as `RoundRepository::getForUpdate()`, refreshes the round and its
  walkers, so `Round` and `Participation` keep writable properties.
- **A failed flush closes the entity manager.** After a unique index refuses a row, the entity manager
  accepts no further query; a repository that needs to know which row holds the value reads it through
  the connection. `DoctrineTransaction` resets the manager for the next use case.

## Database

- **`bin/console` does not see the database container.** Only the Symfony CLI reads the port
  Docker picked and sets `DATABASE_URL`, so run console commands as `symfony console`. A fixed
  `DATABASE_URL` in `.env.local` breaks when the container gets a new port after a restart.
- **Creating or dropping a database needs `serverVersion`.** `doctrine:database:create` and `drop` ask for
  the platform first; without `serverVersion` in `DATABASE_URL`, DBAL connects to the named database to
  read the version, and fails with "Unknown database" while it does not exist. The Symfony CLI adds
  the version locally and CI sets it per image. On a server, leave it out, as
  [configuration.md](configuration.md) explains.
- **Docker Compose reads `.env`, not `.env.local`,** for the variables in `compose.yaml`. Override
  them in the shell, never in the committed `.env`.
- **InnoDB fixes the snapshot at the first plain read.** In `REPEATABLE READ`, the default, every plain
  `SELECT` in a transaction sees the data as it was at the first one. A count after an earlier read
  misses a row another transaction committed in the meantime, even after taking a lock. Count with a
  locking read (`FOR UPDATE`), which always sees the latest commit; see
  [ADR 0005](adr/0005-database-approach.md).
- **A locking read without an index locks every row it reads.** `FOR UPDATE` locks the rows InnoDB
  scans, not only the rows that match. The member roles have no index, so
  `MemberRepository::activeAdminsForUpdate()` locks every member row until the transaction ends.

## Symfony

- **`DEFAULT_URI`** has a default in `config/services.yaml` (`http://localhost`). Without it,
  `cache:clear` fails with "Environment variable not found". Set the real base URL in `.env.local`.
- **The Symfony CLI sets `DEFAULT_URI` and `MAILER_DSN`** when it runs a command, also for tests: its local domain
  and Mailpit. The test environment pins the mailer to `null://null`, and a test that checks a link sets the base
  address itself.
- **A kernel without debug does not check its cache.** With `APP_DEBUG=0` the kernel keeps using the
  container in `var/cache/<env>/`, however much the code changed since. An outdated container in
  `var/cache/test/` refused every voter attribute, so every handler threw `AccessDenied`. Clear it with
  `symfony console cache:clear --env=test` after pulling changes to services or voters.
- **`bin/console debug:container --deprecations` always exits with code 0.** `composer deprecations`
  therefore checks the text of its output.
- **`zircote/swagger-php` stays below 7.** NelmioApiDocBundle extends `OpenApi\Generator`, which swagger-php 7
  deprecates, so `composer deprecations` fails on 7. The `conflict` entry in `composer.json` holds the 6 line,
  which still gets releases; drop it once Nelmio builds on `OpenApi\Builder`.
- **Nelmio adds request bodies and query parameters in swagger-php processors**, after every describer. A describer
  therefore sees no input; `ProblemResponsesProcessor` is a processor for that reason.
- **Nelmio does not read the output of a controller** that returns a `JsonResponse`: the spec lists no success
  answer unless the controller carries an `#[OA\Response]`, which every controller here does.
- **Nelmio finds a response by its status with `===`**: an `#[OA\Response]` carries an integer, a lookup by
  `'404'` misses it and adds a second 404. `ProblemResponsesProcessor` looks for the documented answer itself.
- **`#[MapQueryString]` answers a validation failure with 404** unless the controller sets
  `validationFailedStatusCode: 422`, as every controller here does.
- **A `+` in a query string reads as a space**, so `?from=2026-11-16T00:00:00+01:00` fails validation. A client sends
  `%2B` or an offset `Z`.
- **A route parameter does not reach `#[MapRequestPayload]`.** For an id in the path, the controller fills the id of
  the input with the serializer context `default_constructor_arguments` and replaces it with the one from the path,
  so an id in the body counts for nothing.
