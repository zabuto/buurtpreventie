# Buurtpreventie

The plan lives in docs/plans/plan.md and is leading. Read it before every phase.

## Language and naming

- Code, API, MCP tools, database and commit messages: English.
- All folder and file names: English, also under docs/ (docs/admin-guide/, not docs/beheer/).
- AGENTS.md and CLAUDE.md: English.
- README: English, with a short Dutch summary. docs/, CHANGELOG.md and SECURITY.md: English,
  except docs/glossary.md (Dutch terms and their code names) and the Dutch plans
  (docs/plans/). UI texts: nl and en, through translation files.
- Domain terms follow docs/glossary.md (loopronde = Round, loopmaatje = buddy, ...).
- Every text (comments, test names, messages, docs, UI texts) follows docs/writing.md: UK English
  with US spelling for code vocabulary, no narrative, Dutch user texts at B1.
- Code follows docs/code-style.md: final (readonly) classes, native types, named arguments for
  literal flags, early return; composer check enforces the rest. Symfony recipe files
  (src/Kernel.php, public/index.php, config/preload.php, tests/bootstrap.php) stay as shipped.

## Structure

- Modules sit directly under src/, each with Domain/, UseCase/<Name>/ and Infrastructure/.
  Shared/ is not a module.
- Per use case: <Name>.php (input), <Name>Handler.php, <Name>Output.php (no entities),
  <Name>Controller.php (REST), <Name>Tool.php (MCP); only input and handler are mandatory.
  New use case: symfony console app:make:use-case <Module> <Name>.
- Domain knows nothing about HTTP or MCP. Controllers and tools only call the handler
  in their own folder. Modules use each other only through use cases or interfaces
  in Shared\Domain.
- The frontend lives only in frontend/ and talks only to /api/v1.
  Twig is used only for e-mails and the installer.
- PHPat rules must not fail; their messages point to docs/architecture.md.

## Definition of done for every task

- Tests first, all green; PHPStan, PHPat and Mago clean; no new deprecations.
  Tests follow Arrange-Act-Assert, check one behaviour each, carry #[Test] with a snake_case
  sentence (it_… for the class under test), never depend on another test, and stay in memory
  unless grouped integration or functional (docs/testing.md).
- While working, run only the tests a change touches, plus the cross-cutting ones it reaches: HttpMatrixTest
  for a route or Voter, the OpenAPI tests for an endpoint, ArchitectureRulesTest and ModuleStructureTest for a
  new class or folder.
- Run the full symfony composer check once before calling a task done, locally and not only in CI, and report
  any check that did not run.
- Every write action = audit entry + Voter + a row in the matrix test.
- New texts in nl and en; screens checked with keyboard only and on a narrow screen.
- Fixtures and glossary updated when the model changed.
- docs/ updated where behaviour or administration changed, plus a line in CHANGELOG.md.

## Data and secrets

- Only made-up sample data (symfony console app:fixtures:load demo).
  Never real personal data in the repo, in tests or in fixtures.
- Every session on the machine shares the development database buurtpreventie: only one session at a time loads
  sample data into it or runs the app against it, Hurl included.
- Never commit secrets; they belong in .env.local or shared/.env.local.
- The committed .env, .env.dev and .env.test are public and hold no secrets: agents may read and
  edit them. .env.local, .env.*.local and shared/.env.local hold secrets: never read or commit them.

## Way of working

- Work on a feature/<short-name> branch from develop (bugfix/<short-name> for a bug fix), in small
  commits, and merge it back into develop through a pull request, always as a squash merge: one commit per pull request,
  with the pull request title as its message.
- A branch for a GitHub issue starts its short name with the issue number, as GitHub names it
  (feature/26-<short-name>, bugfix/26-<short-name>, hotfix/26-<short-name>). Its pull request description carries
  "Fixes #26": the squash commit keeps only the title, so the description is what closes the issue.
- main only holds released versions: a release is develop merged into main (a merge commit, never a
  squash, so main keeps the commits of develop) with a tag vX.Y.Z. Urgent fixes go through hotfix/<short-name> from main, back into main and develop.
- ../buurtpreventie-legacy is the old repository (MIT): read only, never change it.
- Per phase, first write a task plan in docs/plans/phase-<n>.md and wait for approval.
- Ask first before: adding a dependency, an irreversible schema change, git push,
  or a release.

## Commands

- Minimum PHP is the `require.php` in composer.json: the oldest release without a known critical
  vulnerability, raised with each new one; CI runs on 8.4.
- Install: composer install
- Database (development): docker compose up -d --wait; run console commands as symfony console,
  which takes DATABASE_URL from the container.
- Start: symfony server:start -d (in the background), or php -S localhost:8000 -t public; check
  /api/v1/health.
- Test: composer test (all; database tests skip), symfony composer test (all, with the database),
  composer test:unit (in-memory unit tests only), composer test:coverage (coverage per class in
  var/coverage/coverage.txt; needs pcov or Xdebug)
- All CI checks: composer check (validate, audit, container lint, deprecations, Mago format,
  lint and analyze, Rector dry run, PHPStan level max with PHPat, PHPUnit). The Opengrep security
  scan runs only in CI (docs/testing.md).
- Fix style: composer fix (Rector, then Mago lint fixes and Mago format).
- Single tools: vendor/bin/phpstan analyse, vendor/bin/mago format --dry-run, vendor/bin/mago lint,
  vendor/bin/mago analyze,
  vendor/bin/rector process --dry-run. PHPStan reads var/cache/dev: run symfony console cache:warmup first.
- Load fixtures: symfony console app:fixtures:load demo|test|edge (empties the database first; dev and test only).
- Check the audit chain: symfony console app:audit:verify (exit code 1 when an entry was changed, removed or inserted).
- MCP over STDIO: MCP_TOKEN=<API token> symfony console mcp:server (with mcpAccess on).
- Database tests only: symfony php vendor/bin/phpunit --group database.
