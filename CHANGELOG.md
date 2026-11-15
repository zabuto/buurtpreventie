# Changelog

All notable changes per version. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Symfony 7.4 LTS base on PHP 8.4.27 or later, with the modules `Patrol`, `Membership`, `Identity`,
  `Audit`, `Settings` and `Maintenance`.
- Health check `GET /api/v1/health`.
- Generator `symfony console app:make:use-case <Module> <Name>`.
- Architecture rules (PHPat) and a folder layout test, explained in `docs/architecture.md`.
- Checks through `composer check`: PHPStan level max, Mago (format, lint, analyze), Rector, PHPUnit, `composer audit`
  and deprecations.
- GitHub Actions for CI, a weekly audit and a weekly look-ahead on Symfony 8 and PHP 8.5;
  Dependabot with automatic merging of patch and minor updates once CI is green (minor updates of
  packages below 1.0 excepted). Dependabot skips major Symfony updates, so the app stays on 7.4 LTS.
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
- Doctrine ORM and Migrations on MariaDB and MySQL; the PHP extensions `intl` and `mbstring` are
  required.
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
- Migrations and Doctrine repositories for every module, each query with an integration test on MariaDB and MySQL;
  no foreign key between modules, and an index on every member reference.
- Signing up and withdrawing lock the round and count its walkers with a locking read: two walkers on the last spot
  leave one signed up and the other refused, and a sign-up never vanishes with a deleted round. A test races two
  real connections.
- A clash on a unique e-mail address, display name or catalogue name between two requests gives the same error as
  the check before it.
- Membership: members with one e-mail address for mail and login, a unique display name and an
  erasure that keeps the member; adding, changing, roles, consent, (de)activating, erasing and a
  member list that shows full names only to coordinators and admins.
- Deactivating or erasing a member withdraws it from every round that has not started and revokes its
  tokens, in one transaction. A round left without walkers is deleted; walkers of a round that drops
  below its minimum get a notification, which the log records.
- Audit log: every write action stores time, actor with their display name at that moment or the
  system, channel and changed fields, never a field holding a password, token or other secret.
  Erasing a member wipes its name from the log and keeps its actions.
- Who acts comes from Symfony's token storage, for handlers and voters alike: `RunAs` sets a member or
  the system identity (`ROLE_SYSTEM`) with the channel it acts through, for entry points without HTTP authentication. A
  failed transaction resets the entity manager, so the next use case in the same process works.
- `MemberDirectory` lets other modules ask whether a member is active, who wants to hear about open
  rounds, and a member's names and phone number (only with consent).
- The last active admin keeps the admin role and cannot be deactivated or erased.
- Patrol use cases: create (also on behalf of an active member), change and delete rounds; sign up and
  withdraw, also on behalf; report a walked round and delete the report; comment and delete a comment;
  manage meeting points and outcomes; list rounds, show a round and find staffing gaps; send reminders
  and buddy calls as the system. A round starts after it is planned and cannot change once started.
- A started round that was not walked, such as after a no-show, can be cancelled with a reason by a
  walker of the round or a coordinator; its status is `cancelled` and it takes no report.
- Rights: a voter per subject on the policy of its module (rounds, participations, reports, comments,
  catalogues, members, API tokens, accounts, audit log), an authorization matrix over every attribute,
  and a guard test that holds every handler to its voter, matrix rows and audit test.
- A meeting point where an upcoming round starts cannot be archived; archived meeting points and outcomes
  are not offered for a new choice.
- Identity: accounts with a password that accepts the hashes of the legacy app, and API tokens stored as a hash with
  scopes, expiry and revocation; a token secret is always generated, and any other value is refused. Settings: the default minimum and maximum walkers of a new round.
- Responses do not name the PHP version: the app and `public/.htaccess` set `X-Powered-By` to `PHP Symfony`.
- API responses carry a Content-Security-Policy that allows nothing, `nosniff`, `Referrer-Policy: no-referrer`
  and `Cross-Origin-Opener-Policy: same-origin`.
- `AddFirstAdmin`: the system adds the first admin, only while no active admin exists.
- A member chooses to hear about rounds that seek a buddy (`ChangeOpenRoundNotifications`), only for themselves.
- A round list covers at most 93 days.
- Sample data: `app:fixtures:load demo|test|edge` in `dev` and `test`, built through the use cases with a fixed seed
  and dates from today.
- CI jobs on MariaDB 10.11 and MySQL 8.4: migrations from an empty database, schema validation, every `down()`,
  the database tests and every sample data set.
- Opengrep rules against queries outside a repository, query builder parts built from strings, predictable random
  functions and broken hashes, and byte-wise case changes.
- ADR 0005 on the database approach; fixed limits in `docs/configuration.md`.
