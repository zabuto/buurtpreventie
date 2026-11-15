# Getting started

From a clean machine to a running local environment and a first change.

## Requirements

- PHP 8.4 at the minimum in `composer.json` or later, or PHP 8.5. The minimum rises with every
  critical PHP vulnerability, and Composer refuses to start the app below it. See
  [gotchas.md](gotchas.md). With the extensions
  `intl` and `mbstring`.
- Composer 2.11 or later, which applies the 24-hour cooldown in `composer.json` to every update.
- Git.
- Docker with Compose, for the development database.
- The [Symfony CLI](https://symfony.com/download) as a local web server; it hands the database
  settings from Docker to the app.

## Install

```bash
git clone git@github.com:zabuto/buurtpreventie.git
cd buurtpreventie
git switch develop
composer install
```

Local settings and secrets go in `.env.local`, which stays out of Git. See
[configuration.md](configuration.md).

## Database

MariaDB runs in a container; the app uses it from phase 1 on.

```bash
docker compose up -d --wait
```

Docker publishes the database on a free port, so it does not clash with other projects. Through the
Symfony CLI (`symfony server:start`, `symfony console`) the app gets `DATABASE_URL` from the running
container; `symfony var:export --multiline` shows it. `docker compose stop` stops the database and
keeps the data; `docker compose down --volumes` removes the data as well.

To look inside with a database client, connect to host `127.0.0.1` (not `localhost`, which makes
many clients look for a socket) on the port that `docker compose port database 3306` prints, with
the user, password and database from `compose.yaml`. The port changes when the container starts
again.

Create the tables, and bring them up to date after every pull that adds a migration:

```bash
symfony console doctrine:migrations:migrate
```

Tests use `buurtpreventie_test` on the same server. On an empty data volume,
`docker/database/grant-prefixed-databases.sh` gives the database user every database starting with
`buurtpreventie_`. A volume created before that script lacks the grant; `docker compose down --volumes` and starting again adds it.

## Start

With the Symfony CLI:

```bash
symfony server:start -d
```

`-d` runs the server in the background; `symfony server:log` shows its log and `symfony server:stop`
stops it.

Without the Symfony CLI:

```bash
php -S localhost:8000 -t public
```

Then open `http://localhost:8000/api/v1/health` (the Symfony CLI prints its own address). The answer
is `{"status":"ok"}`.

### Local domain

The Symfony CLI can serve the app on `https://buurtpreventie.wip` through its local proxy. Once per
machine:

```bash
symfony server:ca:install
symfony proxy:domain:attach buurtpreventie
symfony proxy:start
```

`server:ca:install` puts the local certificate authority in the macOS keychain, which Safari, Chrome
and Firefox trust. On macOS it can still end with "no Firefox security databases found": the
terminal may not read Firefox's profile folder, so its own copy fails. The site then works anyway;
check with the health check below.

The browser reaches `.wip` domains only when the system or browser proxy uses
`http://127.0.0.1:7080/proxy.pac`; see
[Setting up the local proxy](https://symfony.com/doc/current/setup/symfony_cli.html#setting-up-the-local-proxy).
After `symfony server:start -d`, the health check answers on `https://buurtpreventie.wip/api/v1/health`.

## Test and check

| Command | What it does |
| --- | --- |
| `composer test` | All tests; the database tests skip, since only the Symfony CLI knows the database |
| `symfony composer test` | All tests, the database tests included |
| `composer test:unit` | Only the in-memory unit tests |
| `composer check` | Everything CI checks except the security scan: Composer, security advisories, container, deprecations, code style, Rector, PHPStan with the architecture rules, tests |
| `composer fix` | Applies Rector, Mago lint fixes and Mago formatting |

What each check does: [testing.md](testing.md).

## Your first change

1. Read [architecture.md](architecture.md): the folder layout, the worked example and the rules.
2. Concepts such as round, buddy and round report are listed with their code names in
   [glossary.md](glossary.md). Code and texts follow [code-style.md](code-style.md) and
   [writing.md](writing.md).
3. Follow [Adding a feature](architecture.md#adding-a-feature).
4. Work on a `feature/<short-name>` branch from `develop` and deliver through a pull request.
   `composer check` must be green.
