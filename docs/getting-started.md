# Getting started

From a clean machine to a running local environment and a first change.

## Requirements

- PHP 8.4 at the minimum in `composer.json` or later, or PHP 8.5. The minimum rises with every
  critical PHP vulnerability, and Composer refuses to start the app below it. See
  [gotchas.md](gotchas.md). With the extensions
  `intl`, `mbstring`, `gd` and `fileinfo`.
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
[configuration.md](configuration.md). The app needs `APP_SECRET` there before anything signs or hashes a secret, such
as a login code, a stay-logged-in cookie or loading the sample data. Make one if `.env.local` has none:

```bash
echo "APP_SECRET=$(php -r 'echo bin2hex(random_bytes(32));')" >> .env.local
```

## Database

MariaDB and Mailpit, which catches every mail the app sends, run in containers.

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

Mail never leaves the machine: Mailpit keeps it, and `symfony open:local:webmail` opens its web view. Through the
Symfony CLI the app gets `MAILER_DSN` from the `mailer` container, as it gets `DATABASE_URL`. Without the CLI,
`MAILER_DSN` is `null://null`, which drops every mail.

Create the tables, and bring them up to date after every pull that adds a migration:

```bash
symfony console doctrine:migrations:migrate
```

Tests use a database of their own per run, such as `buurtpreventie_test_run1a2b3c4d`, on the same
server. On an empty data volume, `docker/database/grant-prefixed-databases.sh` gives the database
user every database starting with `buurtpreventie_`. A volume created before that script lacks the grant; `docker compose down --volumes` and starting again adds it.

## Sample data

Fill the development database with a made-up association, its members, rounds and reports:

```bash
symfony console app:fixtures:load demo
```

The command empties the database first and asks before it does; `--no-interaction` skips the
question. Besides `demo` there are `test` (the least an installation needs) and `edge` (the hard
cases); see [Sample data](architecture.md#sample-data). Every date follows today, so the calendar is
never empty. The command exists only in `dev` and `test`. The first admin of every set, `beheer@example.org`, logs in
with the made-up password `voorbeeld-wachtwoord-buurt`, which the command shows. In `demo` so do the analyst
(`thomas.visser@example.org`), the coordinator (`fatima.elamrani@example.org`) and three walkers: `jan.bakker`, who
shares his first name, `priya.sharma`, who uses the API, and `willem.dekker`, who stopped and is refused. Any other
member logs in with a code by mail. In `demo`, `priya.sharma` also has an API token that reads and writes for 90
days; the command shows its secret once, after loading. Loading empties the audit log too, so its chain starts afresh
and `symfony console app:audit:verify` reports it intact.

### Sign up with curl

With that token a script signs Priya up for a round, without a session. First the rounds of a period of at most 93
days, read with the same token; fill in two dates as `2026-01-31T00:00:00Z` (`Z` needs no encoding in a query
string, a `+` would read as a space):

```bash
TOKEN=bpt_…   # the secret the command showed
curl -s -H "Authorization: Bearer $TOKEN" \
  "https://buurtpreventie.wip/api/v1/rounds?from=<today>T00:00:00Z&to=<a week later>T00:00:00Z"
```

Take the `id` of a round with open spots and sign up; the answer is `204`, also when she was signed up already:

```bash
curl -s -o /dev/null -w '%{http_code}\n' -X PUT -H "Authorization: Bearer $TOKEN" \
  "https://buurtpreventie.wip/api/v1/rounds/<id>/walkers/me"
```

`DELETE` on the same address withdraws her. A token that may only read gets `403` with `insufficient_scope`; a
token that is revoked or expired gets `401`. curl reaches the local domain only through the local proxy:
`export HTTPS_PROXY="$(symfony proxy:url)"` first. Without the local domain, use the address the Symfony CLI prints,
and `-k` when curl refuses its certificate.

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

### An AI client on the demo

The MCP server answers only for the host of `DEFAULT_URI`, which the Symfony CLI sets to its local domain, so this
runs on `https://buurtpreventie.wip`. Load the demo (see [Sample data](#sample-data)) and note the secret of the token
of `priya.sharma`, a walker whose token reads and writes.

Outside a browser the local domain resolves only through the local proxy, and Node (the MCP Inspector and Claude
Code) knows neither that proxy nor the certificate authority of the Symfony CLI. In the shell for every step below:

```bash
export HTTPS_PROXY="$(symfony proxy:url)"
export NODE_EXTRA_CA_CERTS="$HOME/Library/Application Support/symfony-cli/certs/rootCA.pem"
```

That path holds on macOS; elsewhere the certificate authority is `rootCA.pem` in the `certs` folder of the Symfony
CLI. Without the proxy, curl stops with "Could not resolve host"; `-sS` shows that error, `-s` alone hides it.

MCP is off on every installation until an admin switches it on; until then `/mcp` answers 404:

```bash
B=https://buurtpreventie.wip
curl -sS -c /tmp/bp.jar -H 'Sec-Fetch-Site: same-origin' -H 'Content-Type: application/json' \
  -d '{"email":"beheer@example.org","password":"voorbeeld-wachtwoord-buurt"}' "$B/api/v1/auth/login"
curl -sS -b /tmp/bp.jar -X PATCH -H 'Sec-Fetch-Site: same-origin' -H 'Content-Type: application/json' \
  -d '{"mcpAccess":true}' -w '%{http_code}\n' "$B/api/v1/admin/settings"
```

The second command prints `204`.

**MCP Inspector.** Start it with `npx @modelcontextprotocol/inspector`. In its page, pick the transport "Streamable
HTTP", the address `https://buurtpreventie.wip/mcp` and the header `Authorization: Bearer <secret>`, and connect. It
lists 13 tools, the resource `buurtpreventie://config` and the prompts `monthly_report` and `plan_week`, and shows the
protocol revision it speaks. `staffing_gaps` with `days` 7 answers the rounds of the coming week that need walkers.

**Claude Code.** In a folder outside this project, so its MCP configuration stays out of the repository:

```bash
claude mcp add --transport http buurtpreventie https://buurtpreventie.wip/mcp \
  --header "Authorization: Bearer <secret>"
claude
```

Ask "Which rounds still need a buddy this week?", which calls `staffing_gaps`, then "Sign me up for the first one",
which calls `sign_up_for_round` after Claude asks to confirm. The audit log then shows the sign-up on the channel
`mcp`:

```bash
curl -sS -b /tmp/bp.jar "$B/api/v1/admin/audit?action=participation.signed_up"
```

`claude mcp remove buurtpreventie` removes the server again. What the server offers, and how a member connects to an
installation, is in [the user guide](user-guide/mcp.md).

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
4. Work on a `feature/<short-name>` branch from `develop`, or `bugfix/<short-name>` for a bug fix,
   and deliver through a pull request.
   `composer check` must be green.
