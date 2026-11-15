# Testen

## Indeling

`tests/` volgt de mappen van `src/`: de tests van `src/Maintenance/UseCase/CheckHealth/` staan in
`tests/Maintenance/UseCase/CheckHealth/`. Daarnaast:

| Map | Wat |
| --- | --- |
| `tests/Architecture/` | De PHPat-regels, de test die bewijst dat ze afgaan, en de test van de mapindeling |
| `tests/Architecture/Fixtures/` | Opzettelijke overtredingen; buiten de gewone PHPStan-run en de autoload |

## Soorten tests

| Soort | Basis | Waarvoor |
| --- | --- | --- |
| Unit | `PHPUnit\Framework\TestCase` | Handlers, domeinregels |
| Functioneel | `Symfony\Bundle\FrameworkBundle\Test\WebTestCase` | Endpoints: route, status, JSON |
| Architectuur | PHPUnit dat PHPStan of het bestandssysteem bekijkt | Regels uit [architecture.md](architecture.md#regels) |

`MakeUseCaseCommandTest` laat de generator een use case maken in een tijdelijke map en draait daar
PHPStan op level max met de architectuurregels. Zo breekt een fout in de sjablonen een test, en niet
pas de volgende use case.

Testnamen beschrijven het verwachte gedrag, zoals `testRefusesWritesBecauseTheCheckChangesNothing`.

## Controles

`composer check` draait alles wat CI draait, in deze volgorde:

| Controle | Faalt bij |
| --- | --- |
| `composer validate --strict` | Fout in `composer.json` of een verouderde `composer.lock` |
| `composer audit` | Een bekend beveiligingsadvies voor een geïnstalleerd pakket |
| `lint:container` | Services die niet passen bij de types van hun constructor |
| `lint:yaml config` | Ongeldige YAML |
| `composer deprecations` | Een deprecation bij het bouwen van de container |
| PHP-CS-Fixer | Afwijkende codestijl (`@PER-CS`, `@Symfony`, strict types) |
| Rector (dry run) | Code die een PHP 8.4-, Symfony- of PHPUnit-regel van Rector zou aanpassen |
| PHPStan | Fouten op level max, deprecations, PHPUnit- en Symfony-regels, architectuurregels |
| PHPUnit | Een falende test, en ook elke deprecation, notice, warning of risky test |

Lokaal kun je ook los draaien: `composer test`, `vendor/bin/phpstan analyse`,
`vendor/bin/php-cs-fixer check --diff`, `vendor/bin/rector process --dry-run`.

## Geen deprecations

De app moet zonder deprecations draaien, zodat de overstap naar Symfony 8.4 LTS klein blijft. PHPUnit
faalt op elke deprecation uit de eigen code (`failOnDeprecation`), PHPStan meldt het gebruik van
verouderde API's, en `composer deprecations` controleert de container. Deprecations die alleen binnen
pakketten in `vendor/` ontstaan, tellen niet mee.
