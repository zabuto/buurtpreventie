# Changelog

All notable changes per version. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Symfony 7.4 LTS base on PHP 8.4.27 or later, with the modules `Patrol`, `Membership`, `Identity`,
  `Audit`, `Settings` and `Maintenance`.
- Health check `GET /api/v1/health`.
- Generator `bin/console app:make:use-case <Module> <Name>`.
- Architecture rules (PHPat) and a folder layout test, explained in `docs/architecture.md`.
- Checks through `composer check`: PHPStan level max, PHP-CS-Fixer, Rector, PHPUnit, `composer audit`
  and deprecations.
- GitHub Actions for CI, a weekly audit and a weekly look-ahead on Symfony 8 and PHP 8.5;
  Dependabot with automatic merging of patch updates. Dependabot skips major Symfony updates, so v4
  stays on 7.4 LTS.
- `SECURITY.md` and documentation in `docs/`.
