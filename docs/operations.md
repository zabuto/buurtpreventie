# Operations

What exists next to the code and who maintains what. Hosting, cron, releases and backups follow in
phase 6.

## GitHub repository

- Repository: `zabuto/buurtpreventie`. The original developer owns it; a handover goes through a fork
  (see Overdraagbaarheid in [plans/plan.md](plans/plan.md)).
- Branches: `develop` is the default branch until the first release; work arrives there through pull requests
  from `feature/<short-name>`, or `bugfix/<short-name>` for a bug fix, each merged as one squash commit. `main` holds released versions only,
  tagged `vX.Y.Z`; a release merges `develop` into `main` with a merge commit, because a squash would
  leave `main` with commits `develop` lacks and make every next release conflict. Urgent fixes go
  through `hotfix/<short-name>` from `main`, back into `main` and `develop`.

### Settings made by hand

These are not stored in files and must be set again on a fork or a recreated repository:

| Setting | Where | Why |
| --- | --- | --- |
| Branch protection on `develop`: pull request required, checks "Checks (PHP 8.4)", "Security scan", "Database (MariaDB 10.11)", "Database (MySQL 8.4)" and "Conventional Commit title" required | Settings → Branches | Auto-merge would not wait for green CI otherwise, an update that breaks the migrations or a query on one of the two databases would merge, and a title outside Conventional Commits would become the commit message on `develop` |
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
| `ci.yml`, database jobs "Database (MariaDB 10.11)" and "Database (MySQL 8.4)" | Same | A fresh `APP_SECRET` and `AUDIT_SECRET` per run, migrations from an empty database, `doctrine:schema:validate`, every `down()` and up again, the database tests with `REQUIRE_DATABASE=1`, and every sample data set; see [testing.md](testing.md#database-tests) |
| `ci.yml`, security scan job | Same | Opengrep with the rules in `.opengrep/rules/`; see [testing.md](testing.md#security-scan) |
| `pull-request-title.yml` | A pull request opened, edited, reopened or pushed to | The title is a Conventional Commit (`type(scope): summary`), because the squash merge makes it the commit message on `develop` |
| `weekly.yml`, audit job | Monday 05:00 UTC and by hand | `composer audit` on `develop`; opens an issue on an advisory |
| `weekly.yml`, look-ahead job | Same | Newest Symfony 8 on PHP 8.5, all checks; opens an issue on failure. Blocks nothing |
| `weekly.yml`, Opengrep major job | Same | Opens an issue once a new Opengrep major is 14 days old, or when the check fails |
| `dependabot-automerge.yml` | Dependabot pull requests | Merges patch and minor updates once CI is green, except minor updates of packages below 1.0 |

The weekly jobs open one issue per kind and add a comment to that issue on a later failure. Actions
are pinned to a commit SHA; Dependabot keeps them up to date.

## Dependabot

`.github/dependabot.yml`: weekly for Composer, GitHub Actions and the images in `compose.yaml` (Mailpit; the
database image is set through a variable, which Dependabot skips), targeting `develop`. Security updates target the
default branch.

**Cooldown.** Dependabot proposes a Composer patch or minor only after 7 days and a major after 14;
actions and images wait 7 days. No dependency is exempt; `DependabotCooldownTest` keeps it that way. Security
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
| `AUDIT_SECRET` | `.env.local` locally, `shared/.env.local` on the server; in the backup, apart from the database backup, as without it the audit chain cannot be checked | phase 3 |

Every new key (database, mail, tick, release signing) gets a row here.

## Audit chain

`symfony console app:audit:verify` checks that no entry in the audit log was changed, removed or inserted
([ADR 0007](adr/0007-audit-hash-chain.md)). It reports the chain intact through an entry, or the first entry that does
not hold with the reason, and exits with 1 then, so a cron job can mail a failure. On hosting without SSH, Maintenance
(phase 6b) shows the same.

- **Note the last position** the command prints, outside the server: someone who can change the database can remove
  the newest entries and set the head back, which the chain itself cannot show.
- **Keep `AUDIT_SECRET` in the backup**, apart from the database backup: without it the chain cannot be checked, and
  with both in one place, whoever takes the backup can rebuild a chain that fits.
- **Replacing the key is not built.** After a leak of `shared/.env.local`, the chain detects changes by anyone who
  does not have the leaked key; replacing it needs a key id per entry or a new anchor.
- Loading sample data empties the log and starts the chain afresh; that happens only in `dev` and `test`.

## PHP version

The minimum PHP version is the oldest release without a known critical vulnerability, as set in
`require.php` of `composer.json`; Composer's runtime check refuses to start the app on an older
version. A new critical PHP vulnerability raises this minimum, in `require.php` and
`config.platform.php`, followed by a patch release. Dependabot does not watch PHP itself, so this
takes following the PHP security announcements.

The extensions `intl`, `mbstring`, `gd` and `fileinfo` are required as well: search keys strip accents
with `Normalizer` and lower-case with `mb_strtolower`, and an uploaded logo or icon is checked with
`fileinfo` and redrawn with `gd`. Shared hosting enables all four by default, but a hosting panel can
switch them off; Composer's runtime check then refuses to start the app.

With `expose_php` on, PHP names itself and its exact version in an `X-Powered-By` header, which
tells an attacker which known vulnerabilities to try. Set `expose_php = Off` in `php.ini` where the
host allows it. The app removes the header from every response it handles (`RemovePoweredByHeader`),
and `public/.htaccess` does the same on Apache with `mod_headers`, also for a response that never
reaches the app's response handling.

Apache names itself, its version and its modules (`PHP/8.x` with `mod_php`) in the `Server` header
and on its own error pages. `.htaccess` cannot change either: set `ServerTokens Prod` and
`ServerSignature Off` in the server configuration where the host allows it, or ask the host to.

## Security headers

Every response under `/api/` carries (`ApiSecurityHeaders`):

| Header | Value | Why |
| --- | --- | --- |
| `Content-Security-Policy` | `default-src 'none'; frame-ancestors 'none'` | The API answers with JSON: a browser that renders a response loads, runs and frames nothing |
| `X-Content-Type-Options` | `nosniff` | A browser never reads JSON as HTML or script |
| `Referrer-Policy` | `no-referrer` | No API URL leaks to another site |
| `Cross-Origin-Opener-Policy` | `same-origin` | Another site that opens the API in a window cannot reach it |
| `Strict-Transport-Security` | `max-age=<HSTS_MAX_AGE>`, over HTTPS only, and only when an installation opts in | The browser keeps to HTTPS for this host, so an `http://` address typed on a hostile network cannot be intercepted before the redirect |

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

## Common passwords

`src/Identity/Infrastructure/Password/common-passwords.txt` is built, never edited: the Dutch passwords of
`tools/common-passwords-nl.txt`, then `10k-most-common.txt` of SecLists at the commit the file names.
`php tools/common-passwords.php` rebuilds it from that commit, after a change to the Dutch passwords;
`php tools/common-passwords.php <commit>` moves it to another SecLists commit. Either way the change is a diff to
review in a pull request. `CommittedListTest` fails when a Dutch password is missing from the built file.

SecLists rarely changes this list, and at 15 characters it holds one password long enough to matter, so a move to a
newer commit needs no schedule. The Dutch passwords do the work; add a phrase members are likely to pick, never one
from a leaked list, which holds names of real people.
