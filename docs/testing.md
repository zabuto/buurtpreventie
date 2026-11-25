# Testing

## Layout

`tests/` mirrors `src/`: the tests for `src/Maintenance/UseCase/CheckHealth/` live in
`tests/Maintenance/UseCase/CheckHealth/`. In addition:

| Folder | What |
| --- | --- |
| `tests/Architecture/` | The PHPat rules, the test that proves they fire, the folder layout test, and the test that every linked section of `architecture.md` exists |
| `tests/Architecture/Fixtures/` | Deliberate violations; outside the regular PHPStan run and the autoloader |
| `tests/<Module>/Fake/` | In-memory repositories and other small fakes of the interfaces in `Domain`, for the handler tests |
| `tests/Shared/Fake/` | Fakes of the interfaces in `Shared\Domain`: transaction, execution context, authorization, audit trail, notifier, member directory, recent authentication |
| `tests/Support/` | `ApiTestCase` and `InMemoryKernel` for endpoint tests without a database, and a probe controller that raises each kind of failure for the problem responses |
| `tests/Database/` | The PHPUnit extension that builds the test database before the database tests |
| `tests/Dependencies/` | Every Symfony component in `composer.json` and `composer.lock` is on the release line in `extra.symfony.require` (7.4 LTS); the Dependabot and Composer cooldowns stay at their minimums without exemptions |
| `tests/Tools/` | The development tools in `tools/`, such as the Opengrep version choice |

## Kinds of tests

| Kind | Group | Base | For |
| --- | --- | --- | --- |
| Unit | none | `PHPUnit\Framework\TestCase` | Handlers, domain rules, templates: in memory only, no files, network, database or real clock |
| Integration | `integration` | `PHPUnit\Framework\TestCase` | Code that needs files or a process: the generator, the architecture rules, the dependency checks |
| Functional | `functional` | `App\Tests\Support\ApiTestCase` (or `WebTestCase`) | Endpoints through the kernel: route, status, JSON; on the in-memory kernel, so without a database |
| Database | `database` | `Symfony\Bundle\FrameworkBundle\Test\KernelTestCase` | Doctrine types, transactions, repository queries, the schema, concurrency and the sample data against MariaDB or MySQL |

`composer test` runs all of them; `composer test:unit` runs only the unit tests, in about a second.
A test class that leaves memory carries `#[Group('integration')]`, `#[Group('functional')]` or
`#[Group('database')]`.

## Endpoint tests

`ApiTestCase` boots `InMemoryKernel`: the app with every repository, the transaction, the audit trail, the
notifier and the generators of secrets and login codes replaced by the fakes from `tests/<Module>/Fake/` and `tests/Shared/Fake/`, the clock stopped at
`InMemoryKernel::NOW` and a `RecordingLogger` that keeps what the app logs; everything else is real, security and
voters included. `InMemoryServices` makes the swap as a compiler pass, because `config/services.yaml` loads after
anything registered next to it. Two probe controllers exist only in this kernel: one raises each kind of failure,
the other answers who a request runs as and through which channel. Endpoint tests therefore run without a database, also in `composer test` when none is
reachable. The kernel keeps its container in `var/cache/test_in_memory/`. A test arranges its data in the fakes it
takes from the container (`self::service()`), then makes one request; the client boots a fresh kernel per request, so state does not
leak between tests.

The test client sends `Accept-Language: en-us` unless told otherwise (`Request::create()`). A test that expects
Dutch says so with its own header. `ApiTestCase::createClient()` adds `Sec-Fetch-Site: same-origin`, so a changing
request passes the CSRF check; a test passes another value, or `null` to leave the header out.
`ApiTestCase::actingAs()` logs the client in on the browser firewall as a new member with the given roles. That
member lives in the fakes of the running kernel, so the session holds for one request, or for all of them after
`$client->disableReboot()`. That session is not recent; a test that needs a recent one logs in through an
authenticator. The session cookie of the test environment is `MOCKSESSID`, so a test ends a session by expiring that
cookie. The identity probe also answers whether the proof is recent. Mail goes to the null transport in `test`
(`config/packages/mailer.yaml`), where Symfony's mailer assertions see it; the kernel gives `MailNotifier` a fixed
sender and `AppUrl` a base under a path, so every mail test proves a link keeps it. The kernel counts login attempts in a plain `ArrayAdapter` of its own: a cache pool is
emptied between two requests of one client, and a pool in files would carry attempts from one test to the next.

A few endpoint tests take the whole path through Doctrine, in the groups `database` and `functional`: they extend
`ApiTestCase` with `App\Kernel` as kernel class, store their member through the repository and flush, and stop the app
clock with `Clock::set()` at `InMemoryKernel::NOW`, putting the previous clock back in `tearDown()`. The `clock`
service of the app is Symfony's global clock, so that is the clock the handlers read. `ClockChangeDatabaseTest` is
one: the rounds around both clock changes of 2027, planned and read back through the API. `AuditLogDatabaseTest` is
another: a failed login through the API, then the log of the admin, which shows the shortened IP address only.
`SignUpWithApiTokenDatabaseTest` is the curl scenario: a member logs in, makes a token and signs up with that token
alone; a token that may only read gets 403 `insufficient_scope`.

## Database tests

Every run has a test database of its own: the database name from `DATABASE_URL` plus `_test` and the
`TEST_TOKEN`, such as `buurtpreventie_test_run1a2b3c4d`. Two runs on one machine, from two terminals or
two agent sessions, never drop each other's tables. Locally, start the database as in [getting-started.md](getting-started.md#database) and run the
tests through the Symfony CLI, which sets `DATABASE_URL`: `symfony composer test`, or only the
database tests with `symfony php vendor/bin/phpunit --group database`.

- **Schema from the migrations.** Before the first test of the group `database`,
  `tests/Database/DatabaseSchemaExtension` drops and creates the test database and runs the
  migrations, once per run. It uses `bin/console` in separate processes, because MariaDB and MySQL
  commit every DDL statement at once, which would end the transaction of the test bundle.
- **One database per run.** Without a `TEST_TOKEN`, `DatabaseSchemaExtension` picks one
  (`_run` and eight hex digits) and drops the database when the run ends, failed tests or not. A
  `TEST_TOKEN` you set keeps its database after the run, to look into:
  `TEST_TOKEN=_keep symfony php vendor/bin/phpunit --group database` leaves `buurtpreventie_test_keep`.
- **A run that was killed** leaves its database behind, and may leave a connection open on the
  server that blocks dropping it. List and remove leftovers with
  `docker compose exec database mariadb -uroot -proot -e "SHOW DATABASES LIKE 'buurtpreventie\_test\_%'"`
  and ``DROP DATABASE `buurtpreventie_test_run1a2b3c4d` ``; `SHOW PROCESSLIST` and `KILL <id>` end
  a connection that holds it.
- **Every test rolls back.** `dama/doctrine-test-bundle` wraps each test in a transaction and rolls
  it back afterwards. A test that needs a table of its own creates a `TEMPORARY` table, which
  commits nothing.
- **Skip or fail.** A database test calls `TestDatabase::require()` first in its `setUp()`. Without
  a reachable database the test skips, with the reason; with `REQUIRE_DATABASE=1`, as in CI, it
  fails instead. A failing migration, a database user without the right to create the test
  database, or a test that lacks the group always fails.
- **Without the Symfony CLI**, `.env.test` points at a port that never answers, so the kernel boots
  and the database tests skip.
- **Every query has a test.** Each repository method with a query (DQL, query builder or SQL) has an
  integration test in `tests/<Module>/Infrastructure/Persistence/`, which CI runs on MariaDB and MySQL;
  `find()` and `findBy()` need none. A test filters on the ids it made itself.
- **The schema.** `UuidColumnTest` keeps every UUID column `BINARY(16)`, also on MariaDB, which has a
  type of its own; `UuidColumnIndexTest` requires an index on every UUID column that is not a primary
  key, since no foreign key between modules adds one ([ADR 0005](adr/0005-database-approach.md)).
- **The audit chain.** The chain runs over the whole log, so a test that checks it empties the log and sets head and
  anchor to the start first, inside the rolled-back transaction. A test that locks the head opens a transaction of
  its own: the test bundle keeps its transaction out of sight of the ORM, which refuses a locking read without one.
  `AuditChainMigrationTest` migrates back to the version before the chain, stores entries as they were, migrates up
  and checks their positions, head and anchor; it commits, so it leaves the schema at the latest version itself.
- **The sample data.** `LoadFixturesCommandTest` loads every set and checks what it holds: the same
  people and pattern on any day, the limits of the `demo` set, every hard case in `edge`, among them the start note
  and display name that try to instruct an AI client, for trying the plugin by hand.
  `LoadedSettingsAndChainTest` checks the branding of `demo`, the names of `edge` and an intact audit chain after
  every set.

### Concurrency

`ConcurrentParticipationTest` covers two sign-ups for the last spot, the same member signing up
twice at once, and a sign-up while the last walker leaves. It switches the test bundle off for itself
(`#[SkipDatabaseRollback]`), commits for real and removes its rows in `tearDown()`.

One PHP process cannot wait on two connections. The test therefore runs the first action through the
real handler in a transaction it keeps open, starts the second with `proc_open()` in a process of its
own (`sign-up.php`), waits until that process waits for the lock on the round, commits, and reads the
outcome the process prints. It sees the wait in `information_schema.PROCESSLIST`: a `FOR UPDATE`
query of another session that stays there waits for a lock. The process list needs no `PROCESS`
privilege for the sessions of the own user, unlike the InnoDB lock tables. When the second process
never waits within ten seconds, the test fails: without the lock on the round, or with a count that
does not lock, the second sign-up joins next to the first.

`ConcurrentAuditChainTest` uses the same order for the audit chain: two sign-ups for different rounds take
consecutive positions in an intact chain, as the second waits for the lock on the head; and a sign-up that waits for
the round held by the first finishes without a deadlock, as the first takes the head only as its last step. It empties
the log before and after itself.

`MakeUseCaseCommandTest` lets the generator create a use case in a temporary folder, checks that
`mago format` would not change it, and runs PHPStan at level max with the architecture rules on it. A mistake in the templates thus breaks a test, not
the next use case. What the templates contain is unit-tested in memory in `UseCaseTemplatesTest`.

`OpenApiCoverageTest` reads `/api/v1/docs.json` on the regular test kernel: every route under `/api/v1` is in it with
its method and nothing else is; every operation names its successful answer (204 without a body, any other with a
schema for each media type) and the problem answers it can give (401 and 403 off the public
paths, 404 with an id in the path, 422 with a body or query parameters); and every request in `hurl/`
to `{{baseUrl}}` matches a path and method of the spec, a `{parameter}` standing for one segment (`HurlRequests`). `OpenApiNamingTest`
holds every property and query parameter of the spec to camelCase, and `OutputKeyNamingTest` (in memory) the keys of
the array shapes in the outputs.

## MCP tests

`McpTestCase` (an `ApiTestCase`) talks to the MCP server through the client of the SDK, as an AI client does:
`KernelHttpClient` is a PSR-18 client that hands each request to the in-memory kernel, so switch, firewall, scope,
rate limit and voters run for real. It switches MCP on, gives the member of the test a token, and connects in
revision `2026-07-28` or, when a test asks, an older one with the handshake. The kernel takes `example.org` as its host
for the DNS rebinding protection and counts MCP requests in an `ArrayAdapter`, like login attempts.

- Every tool has a test next to its handler test with a parity check: the same question over REST with the same
  token gives the same answer (`get_round` without full name and phone of the walkers, the note of the report and the comments), and a write tool gives the
  same audit action, on the channel `mcp` against `api`. `RecordingAuditTrail` keeps the channel of every action.
- `tests/Shared/McpToolGuardTest.php` holds every `…Tool` class to `#[McpTool]` with a `readOnlyHint` and to a test
  of its own with a parity check.
- `tests/Shared/McpMatrixTest.php` checks that the server serves the tools of the plan, that every argument of every
  tool has a single type, that a read-only token reaches exactly the tools that only read, and that an administrator
  reaches only the tools for every member.
- `tests/Shared/McpContactDetailsTest.php` calls every tool, the resource and both prompts as a member who sees the
  most, with walkers who consented to sharing contact details and a note and comment that hold a phone number and an
  e-mail address, and finds none of them (review focus 3).
- `tests/Identity/Infrastructure/Mcp/` covers the switch, the token-only firewall, both eras, the rate limit, the
  host and origin checks, and STDIO.
- `tests/Identity/Infrastructure/Security/TokenRateLimitTest.php` proves that reads and writes of one token count
  toward the same limits over REST and MCP, and that a browser session does not count.

## Rights and write actions

`tests/Identity/Infrastructure/Security/TokenScopeTest.php` sets every scope against a reading request, a changing
request and one under `/api/v1/admin/`.

`tests/Shared/HttpMatrixTest.php` runs every route against nobody logged in, a member without a role and a member
with exactly one role, over HTTP on the in-memory kernel. Each request goes to data that exists, with a valid body,
so only the rights can refuse it: a principal that may not act gets 401 (`unauthorized`) or 403 (`access_denied`),
one that may gets anything else, the 403 `reauthentication_required` of a login that is not recent included. Where a
right depends on a relation, the row takes the data of someone else. A guard test in the same class requires a row
for every route of the app, and no row without a route.

- `tests/Shared/AuthorizationMatrixTest.php` runs every voter attribute against nobody signed in, the
  system, and a member with no role or exactly one role, with a row per relation where a right depends
  on one (oneself, the author, a walker of the round).
- `tests/Shared/HandlerGuardTest.php` reads every handler: it carries exactly one of `#[WriteAction]`
  and `#[ReadAction]`, the attribute of every write action has rows in the matrix, the test of every
  write handler has a test starting with `it_records_`, and the matrix asks every voter in `src/`.

## Mail, images and the audit chain

- **Every message has a mail.** `MailNotifierTest` holds every notification type in `src/` against the composers, and
  `RoundMailsTest` renders each mail about a round in `nl` and `en` with the real Twig setup: the link stays under the
  path of the base URL, and no name of another walker, phone number, address, e-mail address or note appears.
  `TranslationKeysTest` requires the same keys in `nl` and `en` in every translation file.
- **Nothing leaves before the commit.** `AfterCommitDeliveryTest` runs the notifier of the app against the real
  `DoctrineTransaction`: a mail after the commit, none after a rollback.
- **Images are made in the test.** `Settings\Fake\TestImages` draws PNGs and JPEGs with GD, a JPEG with a GPS location
  and a PNG with a script after its end included, so no binary file sits in `tests/`.
- **Tampering in memory.** `Audit\Fake\TamperedEntry` copies an entry with stored values replaced, through reflection,
  as someone with access to the database would leave it; `ChainVerifierTest` points at every such change.
  `VerifyAuditChainDatabaseTest` does the same with SQL.

## Sample data in CI

The database jobs in `ci.yml` load every set with `app:fixtures:load <set> --no-interaction`, outside
the test transaction, run `app:audit:verify` after each, and fail when a set leaves no members or no audit entries,
or a chain that does not hold. Locally,
`symfony console app:fixtures:load demo` empties the development database; see
[getting-started.md](getting-started.md#sample-data).

## Writing tests

- **Arrange, Act, Assert.** Every test sets up its inputs, performs one action, and checks the result,
  in that order, with a blank line between the three parts.
- **One behaviour per test.** A test checks a single scenario, so its name says what broke when it
  fails. Several assertions are fine when together they describe that one outcome.
- **`#[Test]` and a sentence in snake_case**, stating the expected behaviour, without a `test` prefix.
  When the subject is the class under test, the sentence starts with `it_`:
  `it_refuses_writes_because_the_check_changes_nothing`; otherwise it names its subject:
  `every_module_has_exactly_domain_use_case_and_infrastructure`. Mago enforces the attribute.
- **Independent tests.** A test builds everything it needs in its own Arrange step or in `setUp()`,
  and cleans up in `tearDown()`. No static properties, no cache filled by an earlier test, no
  `#[Depends]`: every test passes when it runs alone and in any order.
- **Exact outcomes.** Assert the value the behaviour produces (an id, a status code, a count), not
  only that something exists: `assertNotEmpty()` or a count above zero also passes on a wrong result.
- **`$this->assert…()`** for assertions; Mago enforces the style.
- **Real objects over test doubles.** Use the real class wherever it runs in memory. A mock or stub
  belongs only at a boundary the test cannot run, such as a third-party service.
- **Descriptive over DRY.** A test may repeat a line of setup so it reads on its own; a helper is for
  infrastructure (temporary folders, a PHPStan run), not for hiding what the test does.

## Reliable results

A test passes or fails for one reason only, every time:

- **Random order.** PHPUnit runs the tests in a random order and prints the seed, so a test that
  depends on another one fails early. Repeat a failing order with `vendor/bin/phpunit --random-order-seed=<seed>`.
- **No shared state.** Tests that write files use their own temporary folder and remove it.
  `tests/Architecture/PhpStanProcess.php` gives every PHPStan run a fresh cache folder, so an old
  result cache cannot hide a changed rule, and shows stdout and stderr when a run fails.
- **No noise.** The test environment uses a null logger, so expected errors (such as a 405) do not
  print between the results.
- **No clock, network or randomness in assertions.** Time-dependent code takes a clock that tests set
  (`MockClock`); nothing in a test reaches the network.

## Checks

`composer check` runs everything CI runs except the [security scan](#security-scan), in this order:

| Check | Fails on |
| --- | --- |
| `composer validate --strict` | An error in `composer.json` or an outdated `composer.lock` |
| `composer audit` | A known security advisory for an installed package |
| `lint:container` | Services that do not match the types of their constructor |
| `lint:yaml config` | Invalid YAML |
| `composer deprecations` | A deprecation while building the container |
| Mago format, lint, analyze | Deviating layout, lint warnings, analyzer errors; see [code-style.md](code-style.md) |
| Rector (dry run) | Code a PHP 8.4, Symfony or PHPUnit rule of Rector would change |
| PHPStan | Errors at level max, deprecations, PHPUnit and Symfony rules, architecture rules |
| PHPUnit | A failing test, and also any deprecation, notice, warning or risky test |

Locally you can also run them one by one: `composer test`, `vendor/bin/phpstan analyse`,
`vendor/bin/mago format --dry-run`, `vendor/bin/mago lint`, `vendor/bin/mago analyze`,
`vendor/bin/rector process --dry-run`.

### Coverage

The CI job "Checks" also runs `composer test:coverage` with pcov and puts the totals and the coverage per class in
the job summary. It is information only: no threshold, and a failure there never fails the run. That job has no
database, so the Doctrine repositories count low. Locally the same script needs pcov or Xdebug (`XDEBUG_MODE=coverage`)
and writes `var/coverage/coverage.txt`.

## Hurl

The files in `hurl/` check a running app from the outside, one subject per file. Every file logs in by itself and
needs no other, so Hurl runs them in parallel by default, and in any order with `--jobs 1`.

| File | Checks |
| --- | --- |
| `public.hurl` | Health, the OpenAPI description, `/config` of a visitor with contact and group empty, the app icon it names, the web app manifest |
| `refusals.hurl` | The 401 problems of the login endpoints |
| `account.hurl` | A login with a code from Mailpit, the own profile and its 422, a password too short, an API token created, listed without its secret, used, refused under `/me/tokens` and revoked, activity, export, logout, and the same code once more |
| `rounds.hurl` | The rounds of the coming week, one round with walkers, the gaps, the own rounds to report, the 422 for an open or too long period, the 404s, and the 403s of the admin for signing up, reports and history |
| `members.hurl` | The member list without e-mail addresses, the admin list, one member, and the 422s and 404 |
| `meeting-points-and-outcomes.hurl` | The lists for members and the admin, the 409 for a name that is taken, the 422 for half a position, the 404 for archiving |
| `settings.hurl` | The settings, a patch without fields, the 422 for a weak colour and for text as a logo, the 404 for an image no setting names |
| `audit-log.hurl` | The log as JSON and as CSV, and the 406 for another format |

Nothing changes data that a later run needs: no valid password is set, the refused patches change nothing, and the
token made in `account.hurl` is revoked. Run them against the app under the Symfony CLI with sample data and Mailpit:

```sh
hurl --test --variables-file hurl/local.variables --insecure --proxy http://127.0.0.1:7080 \
  --header 'Sec-Fetch-Site: same-origin' --header 'Accept-Language: nl' \
  --variable "mailpitUrl=$(symfony var:export MAILER_WEB_URL)" \
  --variable "inAWeek=$(php -r 'echo gmdate("Y-m-d\TH:i:s\Z", time() + 7 * 86400);')" \
  hurl/*.hurl
```

- **Variables.** `hurl/local.variables` holds `baseUrl`, `email` and `password`: `https://buurtpreventie.wip` and
  `beheer@example.org` with the sample password, the first admin of every sample set. `mailpitUrl` points at Mailpit;
  `inAWeek` ends the period of the rounds and the life of the token. Every variable is required: one that is missing
  fails its file rather than skipping a step. Without Mailpit, run every file but `account.hurl`.
- **Headers.** The app takes a changing request of the browser only from its own origin, which a browser states in
  `Sec-Fetch-Site`; without that header every changing request answers 403.
- **Proxy.** `buurtpreventie.wip` is the domain of the Symfony CLI's local proxy (`symfony proxy:status`), which stays
  the same whatever port the server got; `--proxy`, `https_proxy` or `HTTPS_PROXY` reaches it. Without the proxy,
  set `--variable baseUrl=https://127.0.0.1:<port>` with the port `symfony server:list` shows.
- **`--insecure`** skips the check of the TLS certificate. The Symfony CLI serves HTTPS with a certificate from its own
  local certificate authority, which the browser trusts through the system keychain and Hurl's libcurl does not.
  Locally that check protects nothing: the connection never leaves the machine. Never pass `--insecure` for a server
  elsewhere, where the check is what proves the server is the real one.
- **Codes.** The app takes 3 requests for a code per address in an hour, the same limit as in production, and only
  `account.hurl` asks for one, so a fourth run within the hour answers 429 there. Locally
  `symfony console cache:pool:clear cache.login_attempts` resets that count, and every count of failed logins with it.
- **Hurl 7.1 or later** (`brew install hurl`), for `isList` and the filters on lists. It is no part of `composer check`
  or CI, as it needs the running app and Mailpit.

Two checks stay with PHPUnit. Hurl cannot drop one cookie of a session and keep the stay-logged-in cookie, so the 403
`reauthentication_required` of a session that is not recent is covered by `ApiTokenEndpointsTest` and
`SetPasswordControllerTest`. Hurl checks that every member and every walker has each key and the first has exactly
these; that each one has exactly these keys is covered by `GetMemberSummariesHandlerTest` and `GetRoundHandlerTest`.

**Sending a request by hand.** One request through stdin, with `-i` for the headers:
`echo 'GET https://buurtpreventie.wip/api/v1/health' | hurl --insecure --proxy http://127.0.0.1:7080 -i`. Part of a
file runs with `--to-entry <n>`, and `--verbose` shows every request and answer. To keep a session over separate
commands, save the cookies of a run with `-c "$TMPDIR/session.txt"` (for example the login of `members.hurl` with
`--to-entry 2`) and send them with `-b "$TMPDIR/session.txt"`. A changing request needs
`-H 'Sec-Fetch-Site: same-origin'`. The cookie file holds a valid session: keep it in `$TMPDIR`, never in the repository.

## Security scan

The "Security scan" job in `ci.yml` runs Opengrep with the rules in `.opengrep/rules/`. It runs only
in CI, because verifying the downloaded binary needs Cosign. It first runs the rule tests, then scans
`src/`, `config/`, `templates/`, `public/`, `bin/` and `tools/`; any finding fails the job.

A rule is a YAML file with a test file of the same name next to it: `.php`, `.twig` for templates, or
`.test.yaml` for configuration (Opengrep would read a plain `.yaml` as a rule). A rule that covers both templates and
configuration has one of each, and Opengrep runs both. In the test file, a comment
`ruleid: <id>` marks the next line as one the rule must report and `ok: <id>` as one it must leave alone. Twig test
files use HTML comments (`<!-- ruleid: <id> -->`), because Opengrep does not read `{# #}` as an annotation. Rules for
functions also match the fully qualified call (`\exec()`), the form this code base writes. A `metavariable-regex`
matches from the start of the text, so a pattern that may match further on starts with `^.*`.

| Rule | Refuses |
| --- | --- |
| `command-execution` | Shell commands and generated PHP (`exec()`, `eval()`, backticks, ...) |
| `unserialize` | `unserialize()` on anything but a literal string, unless `allowed_classes` is `false` |
| `request-value-as-file-path` | A request value that reaches a file path |
| `twig-raw-filter` | The `raw` filter in a template |
| `sql-built-from-strings` | SQL or DQL built with concatenation, interpolation or `sprintf()`, also in the parts of the query builder (`select`, `from`, `join`, `where`, `groupBy`, the expression of `set`, ...). A `sprintf()` that fills in only class names (`Round::class`) stays a fixed text and is allowed, as is the `set()` of a response's headers |
| `request-value-in-query` | A request value (`get`, `query`, `request`, `attributes`, `getContent`, `toArray`, `getPayload`, the superglobals) that reaches the text of a query, also through the query builder; a value passed as a parameter, or cast to a number, is allowed. Only a receiver whose name ends in `request` counts as the request |
| `secret-compared-with-equals` | `==`, `===`, `!=`, `!==` and `strcmp()` (and its variants) on a variable or property with `token`, `secret` or `hash` in its name; compare with `hash_equals()`. A check against `null`, a boolean, a literal or a constant is allowed |
| `security-disabled` | `security: false` on any firewall but `dev`, and `csrf_protection: false` or `enabled: false` under it |
| `insecure-session-cookie` | `cookie_secure: false` and `cookie_samesite: none` outside `when@test` |
| `sql-outside-repository` | `executeQuery`, `executeStatement`, `fetch*`, `createQuery`, `createNativeQuery` and `createQueryBuilder` outside `src/*/Infrastructure/Persistence/` |
| `weak-random-or-hash` | `md5`, `sha1`, `rand`, `mt_rand`, `uniqid`, `lcg_value` and `str_shuffle`, also `hash()`, `hash_file()` and `hash_hmac()` with `md5` or `sha1`; secrets come from `random_bytes()` and are hashed with SHA-256 |
| `byte-lowercase` | `strtolower()` and `strtoupper()`, which leave `É` as it is; use `mb_strtolower()` or `LookupKey` |
| `client-file-metadata` | `getClientOriginalName()`, `getClientOriginalExtension()`, `getClientMimeType()` and `guessClientExtension()`: the client picks name and type of an upload, so the format comes from the content |
| `twig-autoescape-disabled` | `{% autoescape false %}` in a template and `autoescape: false` in the configuration; its tests are a `.twig` and a `.test.yaml` file |
| `mailer-outside-notifier` | `send()` on a property or variable whose name holds `mailer`, outside `src/Shared/Infrastructure/Notification/`, so no mail goes around the queue that waits for the commit |

## No deprecations

The app runs without deprecations, so the move to Symfony 8.4 LTS stays small. PHPUnit fails on any
deprecation from our own code (`failOnDeprecation`), PHPStan reports use of deprecated APIs, and
`composer deprecations` checks the container. Deprecations that arise only inside packages in
`vendor/` do not count.
