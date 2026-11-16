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
sender and base address. The kernel counts login attempts in a plain `ArrayAdapter` of its own: a cache pool is
emptied between two requests of one client, and a pool in files would carry attempts from one test to the next.

A few endpoint tests take the whole path through Doctrine, in the groups `database` and `functional`: they extend
`ApiTestCase` with `App\Kernel` as kernel class, store their member through the repository and flush, and stop the
app clock with `Clock::set()` at `InMemoryKernel::NOW`, putting the previous clock back in `tearDown()`. The `clock`
service of the app is Symfony's global clock, so that is the clock the handlers read. `ClockChangeDatabaseTest` is
one: the rounds around both clock changes of 2027, planned and read back through the API.

## Database tests

Database tests run in `buurtpreventie_test`: the database name from `DATABASE_URL` plus `_test`.
Locally, start the database as in [getting-started.md](getting-started.md#database) and run the
tests through the Symfony CLI, which sets `DATABASE_URL`: `symfony composer test`, or only the
database tests with `symfony php vendor/bin/phpunit --group database`.

- **Schema from the migrations.** Before the first test of the group `database`,
  `tests/Database/DatabaseSchemaExtension` drops and creates the test database and runs the
  migrations, once per run. It uses `bin/console` in separate processes, because MariaDB and MySQL
  commit every DDL statement at once, which would end the transaction of the test bundle.
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
- **The sample data.** `LoadFixturesCommandTest` loads every set and checks what it holds: the same
  people and pattern on any day, the limits of the `demo` set, every hard case in `edge`.

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

`MakeUseCaseCommandTest` lets the generator create a use case in a temporary folder, checks that
`mago format` would not change it, and runs PHPStan at level max with the architecture rules on it. A mistake in the templates thus breaks a test, not
the next use case. What the templates contain is unit-tested in memory in `UseCaseTemplatesTest`.

## Rights and write actions

- `tests/Shared/AuthorizationMatrixTest.php` runs every voter attribute against nobody signed in, the
  system, and a member with no role or exactly one role, with a row per relation where a right depends
  on one (oneself, the author, a walker of the round).
- `tests/Shared/HandlerGuardTest.php` reads every handler: it carries exactly one of `#[WriteAction]`
  and `#[ReadAction]`, the attribute of every write action has rows in the matrix, the test of every
  write handler has a test starting with `it_records_`, and the matrix asks every voter in `src/`.

## Sample data in CI

The database jobs in `ci.yml` load every set with `app:fixtures:load <set> --no-interaction`, outside
the test transaction, and fail when a set leaves no members or no audit entries. Locally,
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

## Postman collection

`postman/buurtpreventie.postman_collection.json` checks a running app from the outside: health, the OpenAPI
description, the refusals of the login endpoints, a login with a code and, when a password is set, a login with the
password. After the login with a code it walks the own account: profile, a change that changes nothing, an API token
created, listed, used, refused under `/me/tokens` and revoked, activity, export, and the 403 for a new token or
password once only the stay-logged-in cookie is left. After the login with a password it reads the rounds of the
coming week, one of them, the gaps and the own walked rounds, and checks the 422 for a period of more than 93 days and
the 404 for signing up for a round that does not exist. It never sets a valid new password, which would replace the one
of the member in the local database, and the token it makes lives one day. It is no part of `composer check` or CI and adds no dependency: import it with
`postman/local.postman_environment.json` into the Postman app, or run it with Newman:

```sh
HTTPS_PROXY=http://127.0.0.1:7080 npx newman run postman/buurtpreventie.postman_collection.json \
  -e postman/local.postman_environment.json --insecure \
  --env-var "mailpitUrl=$(symfony var:export MAILER_WEB_URL)"
```

The local environment reaches the app at `https://buurtpreventie.wip`, the domain of the Symfony CLI's local proxy
(`symfony proxy:status`), which stays the same whatever port the server got. Newman does not read the proxy setting of
the system, hence `HTTPS_PROXY`; the Postman app does when it uses the system proxy. Without the proxy, pass the
address `symfony server:list` shows for this directory: `--env-var baseUrl=https://127.0.0.1:<port>`. The local
environment logs in as `beheer@example.org` with the sample password, the first admin of every sample set; add
`--env-var email=…` for another sample member.

`--insecure` skips the check of the TLS certificate. The Symfony CLI serves HTTPS with a certificate from its own
local certificate authority, which the browser trusts through the system keychain; Newman runs on Node.js, which
reads its own list of authorities and not the keychain, so it refuses that certificate. Locally that check protects
nothing: the connection never leaves the machine. Never pass `--insecure` for a server elsewhere, where the check is
what proves the server is the real one. The Postman app has the same switch: Settings → SSL certificate verification.

It needs the app running under the Symfony CLI, the database with sample data, and Mailpit. Only the
local environment sets `mailpitUrl`, for the request "Read the code from Mailpit (local only)", which takes the code
from the newest mail; without it that request is skipped, and a step that needs the code runs only once the variable
`code` is set by hand. A step that needs a password is skipped without one. The app takes 3
requests for a code per address in an hour, the same limit as in production, so a fourth run within the hour answers
429. Locally `symfony console cache:pool:clear cache.login_attempts` resets that count, and every count of failed
logins with it.

## Security scan

The "Security scan" job in `ci.yml` runs Opengrep with the rules in `.opengrep/rules/`. It runs only
in CI, because verifying the downloaded binary needs Cosign. It first runs the rule tests, then scans
`src/`, `config/`, `templates/`, `public/`, `bin/` and `tools/`; any finding fails the job.

A rule is a YAML file with a test file of the same name next to it: `.php`, or `.twig` for templates.
In the test file, a comment `ruleid: <id>` marks the next line as one the rule must report and
`ok: <id>` as one it must leave alone. Twig test files use HTML comments (`<!-- ruleid: <id> -->`),
because Opengrep does not read `{# #}` as an annotation. Rules for functions also match the fully
qualified call (`\exec()`), the form this code base writes.

| Rule | Refuses |
| --- | --- |
| `command-execution` | Shell commands and generated PHP (`exec()`, `eval()`, backticks, ...) |
| `unserialize` | `unserialize()` on anything but a literal string, unless `allowed_classes` is `false` |
| `request-value-as-file-path` | A request value that reaches a file path |
| `twig-raw-filter` | The `raw` filter in a template |
| `sql-built-from-strings` | SQL or DQL built with concatenation, interpolation or `sprintf()`, also in the parts of the query builder (`select`, `from`, `join`, `where`, `groupBy`, the expression of `set`, ...). A `sprintf()` that fills in only class names (`Round::class`) stays a fixed text and is allowed |
| `sql-outside-repository` | `executeQuery`, `executeStatement`, `fetch*`, `createQuery`, `createNativeQuery` and `createQueryBuilder` outside `src/*/Infrastructure/Persistence/` |
| `weak-random-or-hash` | `md5`, `sha1`, `rand`, `mt_rand`, `uniqid`, `lcg_value` and `str_shuffle`, also `hash()`, `hash_file()` and `hash_hmac()` with `md5` or `sha1`; secrets come from `random_bytes()` and are hashed with SHA-256 |
| `byte-lowercase` | `strtolower()` and `strtoupper()`, which leave `É` as it is; use `mb_strtolower()` or `LookupKey` |

## No deprecations

The app runs without deprecations, so the move to Symfony 8.4 LTS stays small. PHPUnit fails on any
deprecation from our own code (`failOnDeprecation`), PHPStan reports use of deprecated APIs, and
`composer deprecations` checks the container. Deprecations that arise only inside packages in
`vendor/` do not count.
