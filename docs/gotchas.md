# Gotchas

Things that are not obvious and cost time when you do not know them.

## PHP version

- **At least PHP 8.4.27.** Earlier 8.4 releases have PHP2Shell (CVE-2026-109618 and
  CVE-2026-109619). `vendor/composer/platform_check.php` stops the app on such a version with
  "Your Composer dependencies require a PHP version >= 8.4.27". The fix is upgrading PHP, never
  lowering the requirement.
- **PHP 8.5 locally, PHP 8.4 as the target.** `config.platform.php` is 8.4.27, so Composer picks
  packages that run on 8.4 even when you have 8.5 locally. PHPStan checks with `phpVersion: 80400`
  that the code uses no syntax that only exists in 8.5. CI runs on 8.4.

## PHPStan

- **PHPStan reads the Symfony container from `var/cache/dev/`.** After a new service or a config
  change, run `bin/console cache:warmup` first; `composer check` does that itself.
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

## Symfony

- **`DEFAULT_URI`** has a default in `config/services.yaml` (`http://localhost`). Without it,
  `cache:clear` fails with "Environment variable not found". Set the real base URL in `.env.local`.
- **`bin/console debug:container --deprecations` always exits with code 0.** `composer deprecations`
  therefore checks the text of its output.
