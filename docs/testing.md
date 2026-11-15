# Testing

## Layout

`tests/` mirrors `src/`: the tests for `src/Maintenance/UseCase/CheckHealth/` live in
`tests/Maintenance/UseCase/CheckHealth/`. In addition:

| Folder | What |
| --- | --- |
| `tests/Architecture/` | The PHPat rules, the test that proves they fire, the folder layout test, and the test that every linked section of `architecture.md` exists |
| `tests/Architecture/Fixtures/` | Deliberate violations; outside the regular PHPStan run and the autoloader |
| `tests/Database/` | The PHPUnit extension that builds the test database before the database tests |
| `tests/Dependencies/` | Every Symfony component in `composer.json` and `composer.lock` is on the release line in `extra.symfony.require` (7.4 LTS); the Dependabot and Composer cooldowns stay at their minimums without exemptions |
| `tests/Tools/` | The development tools in `tools/`, such as the Opengrep version choice |

## Kinds of tests

| Kind | Group | Base | For |
| --- | --- | --- | --- |
| Unit | none | `PHPUnit\Framework\TestCase` | Handlers, domain rules, templates: in memory only, no files, network, database or real clock |
| Integration | `integration` | `PHPUnit\Framework\TestCase` | Code that needs files or a process: the generator, the architecture rules, the dependency checks |
| Functional | `functional` | `Symfony\Bundle\FrameworkBundle\Test\WebTestCase` | Endpoints through the kernel: route, status, JSON |
| Database | `database` | `Symfony\Bundle\FrameworkBundle\Test\KernelTestCase` | Doctrine types, transactions and repository queries against MariaDB or MySQL |

`composer test` runs all of them; `composer test:unit` runs only the unit tests, in about a second.
A test class that leaves memory carries `#[Group('integration')]`, `#[Group('functional')]` or
`#[Group('database')]`.

## Database tests

Database tests run in `buurtpreventie_test`: the database name from `DATABASE_URL` plus `_test`.
Locally, start the database as in [getting-started.md](getting-started.md#database) and run the
tests through the Symfony CLI, which sets `DATABASE_URL`: `symfony composer test`.

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
  (from phase 1); nothing in a test reaches the network.

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

## Security scan

The "Security scan" job in `ci.yml` runs Opengrep with the rules in `.opengrep/rules/`. It runs only
in CI, because verifying the downloaded binary needs Cosign. It first runs the rule tests, then scans
`src/`, `config/`, `templates/`, `public/`, `bin/` and `tools/`; any finding fails the job.

A rule is a YAML file with a test file of the same name next to it: `.php`, or `.twig` for templates.
In the test file, a comment `ruleid: <id>` marks the next line as one the rule must report and
`ok: <id>` as one it must leave alone. Twig test files use HTML comments (`<!-- ruleid: <id> -->`),
because Opengrep does not read `{# #}` as an annotation. Rules for functions also match the fully
qualified call (`\exec()`), the form this code base writes.

## No deprecations

The app runs without deprecations, so the move to Symfony 8.4 LTS stays small. PHPUnit fails on any
deprecation from our own code (`failOnDeprecation`), PHPStan reports use of deprecated APIs, and
`composer deprecations` checks the container. Deprecations that arise only inside packages in
`vendor/` do not count.
