# Operations

What exists next to the code and who maintains what. Hosting, cron, releases and backups follow in
phase 6.

## GitHub repository

- Repository: `zabuto/buurtpreventie`. The original developer owns it; a handover goes through a fork
  (see Overdraagbaarheid in [plans/plan.md](plans/plan.md)).
- Branches: `develop` is the default branch until the first release; work arrives there through pull requests
  from `feature/<short-name>`, each merged as one squash commit. `main` holds released versions only,
  tagged `vX.Y.Z`; a release merges `develop` into `main` with a merge commit, because a squash would
  leave `main` with commits `develop` lacks and make every next release conflict. Urgent fixes go
  through `hotfix/<short-name>` from `main`, back into `main` and `develop`.

### Settings made by hand

These are not stored in files and must be set again on a fork or a recreated repository:

| Setting | Where | Why |
| --- | --- | --- |
| Branch protection on `develop`: pull request required, checks "Checks (PHP 8.4)" and "Security scan" required | Settings → Branches | Auto-merge would not wait for green CI otherwise |
| Allow squash merging (default) and merge commits; no rebase merging | Settings → General | A pull request into `develop` lands as one commit; a release into `main` needs a merge commit |
| Squash merge message: pull request title and description | Settings → General | The squash commit on `develop` carries the pull request title, which the commit convention expects, instead of the title of its first commit |
| Allow auto-merge | Settings → General | Needed to merge Dependabot updates automatically |
| Automatically delete head branches | Settings → General | A branch disappears from GitHub once its pull request is merged |
| Private vulnerability reporting | Settings → Code security | Reports as described in `SECURITY.md` |
| Dependabot alerts and security updates | Settings → Code security | Security updates for Composer and Actions |

## CI

| Workflow | When | What |
| --- | --- | --- |
| `ci.yml`, checks job | Every pull request, every push to `develop` and `main` | `composer check` on PHP 8.4; see [testing.md](testing.md) |
| `ci.yml`, database jobs "Database (MariaDB 10.11)" and "Database (MySQL 8.4)" | Same | Migrations from an empty database, `doctrine:schema:validate`, every `down()` and up again, the database tests with `REQUIRE_DATABASE=1`, and every sample data set; see [testing.md](testing.md#database-tests) |
| `ci.yml`, security scan job | Same | Opengrep with the rules in `.opengrep/rules/`; see [testing.md](testing.md#security-scan) |
| `weekly.yml`, audit job | Monday 05:00 UTC and by hand | `composer audit` on `develop`; opens an issue on an advisory |
| `weekly.yml`, look-ahead job | Same | Newest Symfony 8 on PHP 8.5, all checks; opens an issue on failure. Blocks nothing |
| `weekly.yml`, Opengrep major job | Same | Opens an issue once a new Opengrep major is 14 days old, or when the check fails |
| `dependabot-automerge.yml` | Dependabot pull requests | Merges patch and minor updates once CI is green, except minor updates of packages below 1.0 |

The weekly jobs open one issue per kind and add a comment to that issue on a later failure. Actions
are pinned to a commit SHA; Dependabot keeps them up to date.

## Dependabot

`.github/dependabot.yml`: weekly for Composer and GitHub Actions, targeting `develop`. Security
updates target the default branch.

**Cooldown.** Dependabot proposes a Composer patch or minor only after 7 days and a major after 14;
actions wait 7 days. No dependency is exempt; `DependabotCooldownTest` keeps it that way. Security
updates are not subject to it. Composer itself withholds every release younger than 24 hours
(`config.policy.cooldown`), also for a security update and on a developer machine;
`ComposerCooldownTest` guards that. The reasons are in
[ADR 0004](adr/0004-supply-chain-protection.md).

| Update | Pull request | Merge |
| --- | --- | --- |
| Patch | All Composer patch updates together | Automatic once CI is green |
| Minor | One per package, so a red CI run names the package | Automatic once CI is green; for a package below 1.0 (such as `phpat/phpat`) by the developer, because there a minor release may break the API |
| Major | One per package | By the developer |

GitHub Actions minor and patch updates arrive together in one pull request and merge the same way.

Automatic merging relies on CI: a minor update that breaks a test, a PHPStan rule or the code style
stays open. The `SymfonyReleaseLineTest` fails when an update would put a Symfony component outside
7.4, so such an update never merges automatically.

Dependabot skips major updates of Symfony packages (`symfony/*`). The app stays on 7.4 LTS until the
planned move to 8.4 LTS; a single Symfony 8 release such as 8.1 is maintained for only a few months,
and one package on 8 next to the rest on 7.4 is a mix Symfony does not support. The look-ahead job
shows what breaks on Symfony 8.

## Secrets

Secrets never go into Git. Overview:

| Secret | Where | Since |
| --- | --- | --- |
| `APP_SECRET` | `.env.local` locally, `shared/.env.local` on the server | phase 0 |

Every new key (database, mail, tick, release signing) gets a row here.

## PHP version

The minimum PHP version is the oldest release without a known critical vulnerability, as set in
`require.php` of `composer.json`; Composer's runtime check refuses to start the app on an older
version. A new critical PHP vulnerability raises this minimum, in `require.php` and
`config.platform.php`, followed by a patch release. Dependabot does not watch PHP itself, so this
takes following the PHP security announcements.

The extensions `intl` and `mbstring` are required as well: search keys strip accents with
`Normalizer` and lower-case with `mb_strtolower`. Shared hosting enables both by default, but a
hosting panel can switch them off; Composer's runtime check then refuses to start the app.

With `expose_php` on, PHP names its exact version in an `X-Powered-By` header, which tells an
attacker which known vulnerabilities to try. Set `expose_php = Off` in `php.ini` where the host
allows it. The app replaces the header with `PHP Symfony`, without a version, on every response it
handles (`HidePhpVersion`), and `public/.htaccess` does the same on Apache with `mod_headers`, also
for a response that never reaches the app's response handling.

## Security headers

Every response under `/api/` carries (`ApiSecurityHeaders`):

| Header | Value | Why |
| --- | --- | --- |
| `Content-Security-Policy` | `default-src 'none'; frame-ancestors 'none'` | The API answers with JSON: a browser that renders a response loads, runs and frames nothing |
| `X-Content-Type-Options` | `nosniff` | A browser never reads JSON as HTML or script |
| `Referrer-Policy` | `no-referrer` | No API URL leaks to another site |
| `Cross-Origin-Opener-Policy` | `same-origin` | Another site that opens the API in a window cannot reach it |

In debug mode Symfony drops the policy from an error page, so its exception page can show; in `prod`
the error response keeps it.

## Symfony 8.4 LTS

The app runs on Symfony 7.4 LTS (bug fixes until November 2028, security fixes until November 2029), for
the reasons in [ADR 0002](adr/0002-symfony-lts-and-php-minimum.md). The move to 8.4 LTS is planned
for early 2028, together with PHP 8.5; the steps are in
[plans/plan.md](plans/plan.md#overstap-naar-symfony-84-lts). The look-ahead job shows every week what
breaks on Symfony 8.

## Opengrep

The security scan downloads Opengrep from the releases of `opengrep/opengrep` and runs it only after
`cosign verify-blob` accepts the signature of the release workflow for that exact version.
`tools/opengrep-release.php select` picks the newest release within the major in
`tools/opengrep-major` that is at least 7 days old.

A new major (for example 2.x) never arrives by itself. Once it is 14 days old, the weekly job opens
an issue and `composer update` prints a warning. To move to it:

1. Read its release notes for changes in rule syntax and results.
2. On a branch, change `tools/opengrep-major` and let the security scan run.
3. Fix rules or findings, and merge through a pull request.
