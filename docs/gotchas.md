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
- **PHP-CS-Fixer warns on PHP 8.5** that the project's minimum is 8.4. That is a warning, not an
  error; it does not appear in CI (8.4).

## PHPStan

- **PHPStan reads the Symfony container from `var/cache/dev/`.** After a new service or a config
  change, run `bin/console cache:warmup` first; `composer check` does that itself.
- **Symfony generates `config/reference.php`** on every cache warm-up, with different contents per
  environment (`dev` adds `when@dev` blocks). It only helps autocompletion of PHP config, so it stays
  out of Git and out of PHPStan, PHP-CS-Fixer and Rector.

## Rector

- **A handler keeps its input parameter, even while the input is empty.** Rector would remove an
  unused parameter; `rector.php` skips that rule for `src/*/UseCase/*`, because REST and MCP pass the
  same input.

## Symfony

- **`DEFAULT_URI`** has a default in `config/services.yaml` (`http://localhost`). Without it,
  `cache:clear` fails with "Environment variable not found". Set the real base URL in `.env.local`.
- **`bin/console debug:container --deprecations` always exits with code 0.** `composer deprecations`
  therefore checks the text of its output.
