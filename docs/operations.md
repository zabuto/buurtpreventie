# Operations

What exists next to the code and who maintains what. Hosting, cron, releases and backups follow in
phase 6.

## GitHub repository

- Repository: `zabuto/buurtpreventie`. The original developer owns it; a handover goes through a fork
  (see Overdraagbaarheid in [plan-v4.md](plan-v4.md)).
- Branches: `develop` is the default branch until v4.0.0; work arrives there through pull requests
  from `feature/<short-name>`. `main` holds released versions only, tagged `vX.Y.Z`. Urgent fixes go
  through `hotfix/<short-name>` from `main`, back into `main` and `develop`.
- The predecessor lives in `zabuto/buurtpreventie-v3` (MIT, read only).

### Settings made by hand

These are not stored in files and must be set again on a fork or a recreated repository:

| Setting | Where | Why |
| --- | --- | --- |
| Branch protection on `develop`: pull request required, check "Checks (PHP 8.4)" required | Settings → Branches | Auto-merge would not wait for green CI otherwise |
| Allow auto-merge | Settings → General | Needed to merge Dependabot updates automatically |
| Automatically delete head branches | Settings → General | A branch disappears from GitHub once its pull request is merged |
| Private vulnerability reporting | Settings → Code security | Reports as described in `SECURITY.md` |
| Dependabot alerts and security updates | Settings → Code security | Security updates for Composer and Actions |

## CI

| Workflow | When | What |
| --- | --- | --- |
| `ci.yml` | Every pull request, every push to `develop` and `main` | `composer check` on PHP 8.4; see [testing.md](testing.md) |
| `weekly.yml`, audit job | Monday 05:00 UTC and by hand | `composer audit` on `develop`; opens an issue on an advisory |
| `weekly.yml`, look-ahead job | Same | Newest Symfony 8 on PHP 8.5, all checks; opens an issue on failure. Blocks nothing |
| `dependabot-automerge.yml` | Dependabot pull requests | Merges patch and minor updates once CI is green, except minor updates of packages below 1.0 |

The weekly jobs open one issue per kind and add a comment to that issue on a later failure. Actions
are pinned to a commit SHA; Dependabot keeps them up to date.

## Dependabot

`.github/dependabot.yml`: weekly for Composer and GitHub Actions, targeting `develop`. Security
updates target the default branch.

| Update | Pull request | Merge |
| --- | --- | --- |
| Patch | All Composer patch updates together | Automatic once CI is green |
| Minor | One per package, so a red CI run names the package | Automatic once CI is green; for a package below 1.0 (such as `phpat/phpat`) by the developer, because there a minor release may break the API |
| Major | One per package | By the developer |

GitHub Actions minor and patch updates arrive together in one pull request and merge the same way.

Automatic merging relies on CI: a minor update that breaks a test, a PHPStan rule or the code style
stays open. The `SymfonyReleaseLineTest` fails when an update would put a Symfony component outside
7.4, so such an update never merges automatically.

Dependabot skips major updates of Symfony packages (`symfony/*`). v4 stays on 7.4 LTS until the
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

At least PHP 8.4.27. PHP 8.4.0 to 8.4.26 have PHP2Shell (CVE-2026-109618 and CVE-2026-109619):
unauthenticated remote code execution, exploited in the wild. `composer.json` therefore requires
`>=8.4.27`, and Composer's runtime check refuses to start the app on an older version. A new critical
PHP vulnerability raises this minimum in `require.php` and `config.platform.php`.

## Symfony 8.4 LTS

v4 runs on Symfony 7.4 LTS (bug fixes until November 2028, security fixes until November 2029). The
move to 8.4 LTS is planned for early 2028, together with PHP 8.5; the steps are in
[plan-v4.md](plan-v4.md#overstap-naar-symfony-84-lts). The look-ahead job shows every week what
breaks on Symfony 8.
