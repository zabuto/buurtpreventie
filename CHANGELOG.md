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
- Checks through `composer check`: PHPStan level max, Mago (format, lint, analyze), Rector, PHPUnit, `composer audit`
  and deprecations.
- GitHub Actions for CI, a weekly audit and a weekly look-ahead on Symfony 8 and PHP 8.5;
  Dependabot with automatic merging of patch and minor updates once CI is green (minor updates of
  packages below 1.0 excepted). Dependabot skips major Symfony updates, so v4 stays on 7.4 LTS.
- Development database: MariaDB 10.11 in `compose.yaml` on a free port, handed to the app by the
  Symfony CLI.
- Test suite in unit, integration and functional groups; `composer test:unit` runs the unit tests.
- `SECURITY.md` and documentation in `docs/`, including the code style and writing guidelines.
- Cooldowns against hijacked releases: Dependabot waits 7 days for a patch or minor and 14 for a
  major (actions 7), Composer withholds releases younger than 24 hours. Security updates skip the
  Dependabot cooldown.
- Security scan in CI: Opengrep, verified with Cosign, with rules and rule tests in
  `.opengrep/rules/`. A new Opengrep major is reported weekly and after `composer update`.
- ADRs on Symfony 7.4 LTS and the PHP minimum, the licence, and supply-chain protection.
- Doctrine ORM and Migrations on MariaDB and MySQL, with a second connection to the v3 database for
  the conversion; the PHP extensions `intl` and `mbstring` are required.
- Shared building blocks for handlers: `Transaction`, `ExecutionContext`, `AuditTrail`,
  `Authorization` and `Notifier`, the value objects `EmailAddress` and `LookupKey`, the Doctrine
  type `utc_datetime_immutable`, and authorization through Symfony's voters.
- Architecture rules: the domain may use Symfony Uid; input and handlers may not use Doctrine.
- Database tests in the group `database`, on a test database built from the migrations once per
  run; they skip without a database, unless `REQUIRE_DATABASE=1`.
- Patrol domain: rounds with a staffing rule fixed at creation, derived status, open spots and
  daypart; signing up (idempotent, refused when full or started), withdrawing, round reports, outcomes,
  meeting points with coordinates, comments, reminders once per walker per day, and who may change a
  round or delete a report or comment.
- The first migration, with the Patrol tables, and storage of rounds through Doctrine.
- Membership: members with one e-mail address for mail and login, a unique display name and an
  erasure that keeps the member; adding, changing, roles, consent, (de)activating, erasing and a
  member list that shows full names only to coordinators and admins.
- Identity: accounts with a password that accepts v3 hashes, and API tokens stored as a hash with
  scopes, expiry and revocation. Settings: the default minimum and maximum walkers of a new round.
- Responses do not name the PHP version: the app and `public/.htaccess` set `X-Powered-By` to `PHP Symfony`.
- API responses carry a Content-Security-Policy that allows nothing, `nosniff`, `Referrer-Policy: no-referrer`
  and `Cross-Origin-Opener-Policy: same-origin`.
