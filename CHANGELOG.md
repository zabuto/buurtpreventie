# Changelog

Alle noemenswaardige wijzigingen per versie. Opzet volgens [Keep a Changelog](https://keepachangelog.com/nl/1.1.0/),
versies volgens [Semantic Versioning](https://semver.org/lang/nl/).

## [Unreleased]

### Toegevoegd

- Symfony 7.4 LTS-basis op PHP 8.4.27 of hoger, met de modules `Patrol`, `Membership`, `Identity`,
  `Audit`, `Settings` en `Maintenance`.
- Gezondheidscheck `GET /api/v1/health`.
- Generator `bin/console app:make:use-case <Module> <Naam>`.
- Architectuurregels (PHPat) en een test van de mapindeling, met uitleg in `docs/architecture.md`.
- Controles met `composer check`: PHPStan level max, PHP-CS-Fixer, Rector, PHPUnit, `composer audit`
  en deprecations.
- GitHub Actions voor CI, een wekelijkse audit en een wekelijkse vooruitblik op Symfony 8 en PHP 8.5;
  Dependabot met automatisch samenvoegen van patch-updates.
- `SECURITY.md` en documentatie in `docs/`.
