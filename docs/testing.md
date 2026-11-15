# Testing

## Layout

`tests/` mirrors `src/`: the tests for `src/Maintenance/UseCase/CheckHealth/` live in
`tests/Maintenance/UseCase/CheckHealth/`. In addition:

| Folder | What |
| --- | --- |
| `tests/Architecture/` | The PHPat rules, the test that proves they fire, the folder layout test, and the test that every linked section of `architecture.md` exists |
| `tests/Architecture/Fixtures/` | Deliberate violations; outside the regular PHPStan run and the autoloader |
| `tests/Dependencies/` | Every Symfony component in `composer.json` and `composer.lock` is on the release line in `extra.symfony.require` (7.4 LTS) |

## Kinds of tests

| Kind | Base | For |
| --- | --- | --- |
| Unit | `PHPUnit\Framework\TestCase` | Handlers, domain rules |
| Functional | `Symfony\Bundle\FrameworkBundle\Test\WebTestCase` | Endpoints: route, status, JSON |
| Architecture | PHPUnit that inspects PHPStan output or the file system | The rules in [architecture.md](architecture.md#rules) |

`MakeUseCaseCommandTest` lets the generator create a use case in a temporary folder and runs PHPStan
at level max with the architecture rules on it. A mistake in the templates thus breaks a test, not
the next use case.

Test names describe the expected behaviour, such as `testRefusesWritesBecauseTheCheckChangesNothing`.

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

`composer check` runs everything CI runs, in this order:

| Check | Fails on |
| --- | --- |
| `composer validate --strict` | An error in `composer.json` or an outdated `composer.lock` |
| `composer audit` | A known security advisory for an installed package |
| `lint:container` | Services that do not match the types of their constructor |
| `lint:yaml config` | Invalid YAML |
| `composer deprecations` | A deprecation while building the container |
| PHP-CS-Fixer | Deviating code style (`@PER-CS`, `@Symfony`, strict types) |
| Rector (dry run) | Code a PHP 8.4, Symfony or PHPUnit rule of Rector would change |
| PHPStan | Errors at level max, deprecations, PHPUnit and Symfony rules, architecture rules |
| PHPUnit | A failing test, and also any deprecation, notice, warning or risky test |

Locally you can also run them one by one: `composer test`, `vendor/bin/phpstan analyse`,
`vendor/bin/php-cs-fixer check --diff`, `vendor/bin/rector process --dry-run`.

## No deprecations

The app runs without deprecations, so the move to Symfony 8.4 LTS stays small. PHPUnit fails on any
deprecation from our own code (`failOnDeprecation`), PHPStan reports use of deprecated APIs, and
`composer deprecations` checks the container. Deprecations that arise only inside packages in
`vendor/` do not count.
