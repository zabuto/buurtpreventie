# Testing

## Layout

`tests/` mirrors `src/`: the tests for `src/Maintenance/UseCase/CheckHealth/` live in
`tests/Maintenance/UseCase/CheckHealth/`. In addition:

| Folder | What |
| --- | --- |
| `tests/Architecture/` | The PHPat rules, the test that proves they fire, and the folder layout test |
| `tests/Architecture/Fixtures/` | Deliberate violations; outside the regular PHPStan run and the autoloader |

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
