# Valkuilen

Dingen die niet vanzelf spreken en tijd kosten als je ze niet weet.

## PHP-versie

- **Minimaal PHP 8.4.27.** Lagere 8.4-versies hebben PHP2Shell (CVE-2026-109618 en
  CVE-2026-109619). `vendor/composer/platform_check.php` stopt de app op zo'n versie met de melding
  "Your Composer dependencies require a PHP version >= 8.4.27". Oplossing: PHP bijwerken, niet de eis
  verlagen.
- **Lokaal PHP 8.5, doel PHP 8.4.** `config.platform.php` staat op 8.4.27, zodat Composer pakketten
  kiest die op 8.4 draaien, ook als je lokaal 8.5 hebt. PHPStan controleert met `phpVersion: 80400`
  dat de code geen syntaxis gebruikt die pas in 8.5 bestaat. CI draait op 8.4.
- **PHP-CS-Fixer waarschuwt op PHP 8.5** dat het project 8.4 als minimum heeft. Dat is een
  waarschuwing, geen fout; in CI (8.4) verschijnt hij niet.

## PHPStan

- **PHPStan leest de Symfony-container uit `var/cache/dev/`.** Na een nieuwe service of een gewijzigde
  config eerst `bin/console cache:warmup`; `composer check` doet dat zelf.
- **`config/reference.php` wordt door Symfony gegenereerd** bij het opwarmen van de cache. Het staat in
  Git voor de autocompletion van PHP-config, en valt buiten PHPStan, PHP-CS-Fixer en Rector.

## Rector

- **Een handler houdt zijn invoerparameter, ook als die leeg is.** Rector zou een ongebruikte
  parameter weghalen; `rector.php` slaat die regel over voor `src/*/UseCase/*`, omdat REST en MCP
  dezelfde invoer doorgeven.

## Symfony

- **`DEFAULT_URI`** heeft een standaardwaarde in `config/services.yaml` (`http://localhost`). Zonder
  die waarde faalt `cache:clear` met "Environment variable not found". Stel de echte basis-URL in
  `.env.local` in.
- **`bin/console debug:container --deprecations` eindigt altijd met exit code 0.**
  `composer deprecations` controleert daarom de tekst van de uitvoer.
