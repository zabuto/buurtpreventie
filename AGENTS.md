# Buurtpreventie v4

The plan lives in docs/plan-v4.md and is leading. Read it before every phase.

## Language and naming

- Code, API, MCP tools, database and commit messages: English.
- All folder and file names: English, also under docs/ (docs/admin-guide/, not docs/beheer/).
- AGENTS.md and CLAUDE.md: English.
- README: Dutch, with a short English summary. docs/, CHANGELOG.md and SECURITY.md: English,
  except docs/glossary.md (Dutch terms and their code names) and the Dutch plans
  (docs/plan-v4.md, docs/plans/). UI texts: nl and en, through translation files.
- Domain terms follow docs/glossary.md (loopronde = Round, loopmaatje = buddy, ...).

## Structure

- Modules sit directly under src/, each with Domain/, UseCase/<Name>/ and Infrastructure/.
  Shared/ is not a module.
- Per use case: <Name>.php (input), <Name>Handler.php, <Name>Output.php (no entities),
  <Name>Controller.php (REST), <Name>Tool.php (MCP); only input and handler are mandatory.
  New use case: bin/console app:make:use-case <Module> <Name>.
- Domain knows nothing about HTTP or MCP. Controllers and tools only call the handler
  in their own folder. Modules use each other only through use cases or interfaces
  in Shared\Domain.
- The frontend lives only in frontend/ and talks only to /api/v1.
  Twig is used only for e-mails and the installer.
- PHPat rules must not fail; their messages point to docs/architecture.md.

## Definition of done for every task

- Tests first, all green; PHPStan, PHPat and PHP-CS-Fixer clean; no new deprecations.
- Every write action = audit entry + Voter + a row in the matrix test.
- New texts in nl and en; screens checked with keyboard only and on a narrow screen.
- Fixtures and glossary updated when the model changed.
- docs/ updated where behaviour or administration changed, plus a line in CHANGELOG.md.

## Data and secrets

- Only made-up sample data (bin/console app:fixtures:load demo).
  Never real personal data in the repo, in tests or in fixtures.
- Never commit secrets; they belong in .env.local or shared/.env.local.

## Way of working

- Work on a feature/<short-name> branch from develop, in small commits, and merge it
  back into develop through a pull request.
- main only holds released versions: a release is develop merged into main with a tag
  vX.Y.Z. Urgent fixes go through hotfix/<short-name> from main, back into main and develop.
- ../buurtpreventie-v3 is the old repository (v3, MIT): read only, never change it.
- Per phase, first write a task plan in docs/plans/phase-<n>.md and wait for approval.
- Ask first before: adding a dependency, an irreversible schema change, git push,
  or a release.

## Commands

- Minimum PHP is 8.4.27 (PHP2Shell, CVE-2026-109618 and CVE-2026-109619); CI runs on 8.4.
- Install: composer install
- Start: symfony server:start, or php -S localhost:8000 -t public; check /api/v1/health.
- Test: composer test
- All CI checks: composer check (validate, audit, container lint, deprecations, PHP-CS-Fixer,
  Rector dry run, PHPStan level max with PHPat, PHPUnit).
- Fix style: composer fix (Rector, then PHP-CS-Fixer).
- Single tools: vendor/bin/phpstan analyse, vendor/bin/php-cs-fixer check --diff,
  vendor/bin/rector process --dry-run. PHPStan reads var/cache/dev: run bin/console cache:warmup first.
- Load fixtures: bin/console app:fixtures:load demo (from phase 1).
