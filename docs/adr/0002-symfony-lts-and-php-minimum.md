# ADR 0002: Symfony 7.4 LTS on PHP 8.4.27 or later

- Status: accepted
- Date: 2026-11-15

## Context

Buurtpreventie v4 runs on ordinary shared hosting, installed and updated by a volunteer web
administrator. It has one maintainer. Every major framework upgrade costs that maintainer time and
reaches each installation as a release the administrator has to install.

Symfony 7.4 is a long-term support release: bug fixes until November 2028, security fixes until
November 2029. A Symfony 8 release outside LTS is maintained for a few months only. Shared hosting
offers PHP 8.4, which receives security fixes until the end of 2028. PHP 8.4.0 up to and including
8.4.26 carry PHP2Shell (CVE-2026-109618 and CVE-2026-109619), unauthenticated remote code execution.

## Decision

- **Symfony 7.4 LTS** (`extra.symfony.require: 7.4.*`). Dependabot skips major Symfony updates.
- **PHP 8.4.27 as the minimum**, in `require` and in `config.platform.php`. Composer's platform check
  stops the app on a vulnerable 8.4, and PHPStan analyses for PHP 8.4 (`phpVersion: 80400`), so
  syntax that exists only in 8.5 fails.
- **No deprecations:** PHPUnit fails on a deprecation, PHPStan runs the deprecation rules and
  `composer check` fails when building the container triggers one.
- **A weekly look-ahead job** runs every check on the newest Symfony 8 with PHP 8.5. It blocks nothing
  and opens an issue on failure.
- **Move to Symfony 8.4 LTS in early 2028**, once 8.4 has had a few patch releases, together with PHP
  8.5. Symfony 8 features the project needs before then sit behind a small interface of its own,
  marked `@upgrade-8.4`, so the move replaces each one in a single place.

## Consequences

- One framework upgrade in three years instead of one every six months.
- The move to 8.4 LTS is an ordinary release that administrators install with the update button,
  because the code holds no deprecations and the look-ahead job reports breakage early.
- A vulnerability in PHP itself is not covered by Dependabot: the maintainer raises the minimum in
  `composer.json` and publishes a patch release.
- Symfony 8 features such as sudo mode and encrypted Doctrine fields wait for the move or get a
  smaller replacement of the project's own.
- Development machines may run PHP 8.5; CI on PHP 8.4 is the binding check
  (see [gotchas.md](../gotchas.md)).
