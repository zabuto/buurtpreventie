# Fase 0: taakplan

Status: opgeleverd in pull request #1, aangevuld in #7 tot en met #14.

Bron: `docs/plan-v4.md` (Samenvatting, Uitgangspunten, Licentie, Codestructuur, Overdraagbaarheid,
Overstap naar Symfony 8.4 LTS, Fase 0) en `AGENTS.md`.

## Doel en klaar-criterium

Een lege Symfony 7.4 LTS-app op PHP 8.4 met de modulaire mapindeling, bewaakte architectuurregels,
volledige kwaliteitscontroles in CI en de documentatie waarmee een nieuwe ontwikkelaar zelfstandig
start.

Klaar als:

- `composer check` lokaal groen is (Mago, PHPStan met PHPat, Rector, PHPUnit, deprecations);
- dezelfde controles op GitHub Actions groen zijn op de branch `feature/phase-0`;
- één voorbeeld-use case, gemaakt met `bin/console app:make:use-case`, alle PHPat-regels doorstaat;
- `docs/getting-started.md` een nieuwe ontwikkelaar in één keer tot een draaiende lokale omgeving
  brengt;
- de pull request naar `develop` groen is en gemergd.

## Al gedaan (eerste punt van Fase 0 in het plan)

Oude repo afgerond en hernoemd, nieuwe repo `zabuto/buurtpreventie` met `main` en `develop`,
`LICENSE` vanaf de eerste commit. `LICENSE` blijft ongewijzigd.

## Voorgestelde beslissingen

1. **Geen database in fase 0.** Doctrine en `app:fixtures:load` komen in fase 1, samen met de
   eerste entiteiten; MariaDB in `compose.yaml` staat vóór fase 1 klaar. Fase 0 heeft geen
   persistente data. De PHPat-regels noemen Doctrine-namespaces al wel, zodat ze in fase 1 meteen
   werken.
2. **Voorbeeld-use case: `Maintenance/CheckHealth`**, `GET /api/v1/health`, antwoord
   `{"status": "ok"}`. Klein, echt bruikbaar (de updater in fase 6b heeft een gezondheidscheck nodig)
   en zonder domeinmodel, zodat fase 1 de domeinregels nog steeds test-eerst bouwt. Bewust zonder
   versie- of PHP-informatie in het antwoord: dat endpoint is publiek. `docs/architecture.md` werkt
   dit voorbeeld volledig uit, zodat de uitleg uit bestaande code komt. Fase 1 kan het voorbeeld
   vervangen door `SignUpForRound`.
3. **Uitvoer-DTO als vijfde vast bestand: `<Naam>Output.php`.** Het plan zegt dat controller en tool
   een uitvoer-DTO teruggeven, maar noemt geen bestand. Met een vaste naam kan regel 3 (geen
   entiteiten in de uitvoer) worden bewaakt. `<Naam>Result` valt af omdat `ReportRoundResult` dan
   `ReportRoundResultResult` wordt. `AGENTS.md` (Structure) en Codestructuur in het plan krijgen
   die regel erbij.
4. **`<Naam>Tool.php` zonder MCP SDK tot fase 5.** De generator maakt de tool als gewone klasse
   die alleen de eigen handler aanroept, zodat regel 3 voor tools nu al getest wordt. Fase 5 voegt
   het MCP-attribuut toe aan het sjabloon en aan bestaande tools. Alternatief: de tool pas in fase 5
   genereren; dan is regel 3 voor tools tot die tijd onbewaakt.
5. **De regel "elke module heeft Domain, UseCase en Infrastructure" wordt een PHPUnit-test, geen
   PHPat-regel.** PHPat controleert afhankelijkheden tussen klassen; lege mappen bevatten geen
   klassen en zijn voor PHPat onzichtbaar. De test controleert ook dat elke map onder `UseCase/`
   alleen de vijf vaste bestanden met de mapnaam bevat, waarvan invoer en handler verplicht zijn.
   De foutmelding verwijst net als de PHPat-regels naar `docs/architecture.md`.
6. **De generator staat in `src/Shared/Infrastructure/Generator/`** en wordt alleen in `dev` en
   `test` geregistreerd (`#[When]`), zodat hij niet in productie draait. Sjablonen zijn gewone
   PHP-strings, geen Twig (Twig alleen voor e-mails en installer).
7. **Lokaal PHP 8.5, in CI PHP 8.4.** Op deze machine staat alleen PHP 8.5.12. `config.platform.php`
   staat op 8.4, zodat Composer pakketten kiest die op 8.4 draaien, en PHPStan krijgt
   `phpVersion: 80400`, zodat syntaxis die alleen in 8.5 bestaat een fout geeft. De PHP 8.4-run op
   GitHub is de echte toets. Geen `.php-version`: de Symfony CLI zou dan een PHP-versie zoeken die
   hier niet staat.
   **Minimum is PHP 8.4.27** (`require` en `config.platform.php`): 8.4.0 tot en met 8.4.26 hebben
   PHP2Shell (CVE-2026-109618 en CVE-2026-109619, onaangemelde remote code execution, misbruikt in
   het wild). Composer's runtime-controle (`vendor/composer/platform_check.php`) laat de app dan niet
   starten op een kwetsbare 8.4. De installer (fase 6a) en Onderhoud (fase 6b) controleren hetzelfde
   minimum.
8. **De docs uit het plan zijn leidend.** Naast wat de prompt noemt, komen ook `configuration.md`,
   `testing.md` en `gotchas.md` erin (Fase 0 in het plan noemt ze), kort en alleen met wat nu waar
   is. Verder `CHANGELOG.md`, omdat de klaar-afspraak er per taak een regel in vraagt.
9. **SECURITY.md in het Engels**, zoals de rest van `docs/`, zodat ook melders die geen Nederlands
   lezen hem begrijpen.

## Nieuwe afhankelijkheden (goedgekeurd)

Runtime (via het `symfony/skeleton` 7.4):

- `symfony/framework-bundle`, `console`, `dotenv`, `runtime`, `yaml`, `flex` (skelet).
- `symfony/serializer`, `symfony/validator`, `symfony/property-access`, `symfony/property-info`:
  nodig voor `#[MapRequestPayload]` in de gegenereerde controllers.

Ontwikkeling:

- `phpunit/phpunit` `^13` (nieuwste major die op PHP 8.4 draait), `symfony/browser-kit` (functionele test
  van het voorbeeld-endpoint).
- `phpstan/phpstan`, `phpat/phpat`, `phpstan/phpstan-symfony`, `phpstan/phpstan-deprecation-rules`,
  `phpstan/phpstan-phpunit`. Extensies worden expliciet in de config geladen; geen
  `phpstan/extension-installer`, dus een Composer-plugin minder.
- `carthage-software/mago` (opmaak, lint en analyse), `rector/rector`.

Bewust niet: `roave/security-advisories` (Composer blokkeert onveilige versies zelf en `composer
audit` draait in CI), `symfony/phpunit-bridge` (PHPUnit vangt deprecations zelf via
`deprecationTrigger`), `symfony/maker-bundle` (eigen generator).

## Taken

Elke taak is één of enkele kleine commits. Tests komen in elke taak vóór de code.

### T1. Branch

`feature/phase-0` vanaf `develop`.

### T2. Symfony-skelet

- `symfony/skeleton` 7.4 aanmaken in een tijdelijke map en overzetten (de repo is niet leeg).
- `composer.json`: naam `zabuto/buurtpreventie`, licentie `PolyForm-Noncommercial-1.0.0`,
  `php: >=8.4.27`, `config.platform.php: 8.4.27`, `extra.symfony.require: 7.4.*`, `sort-packages`.
- `.editorconfig` (zoals v3), `.gitignore` aangevuld met cachemappen van de tools.
- Routes: alle controllers onder `src/*/UseCase/` via attributen, met prefix `/api/v1`.

### T3. Modulemappen

`src/Patrol`, `Membership`, `Identity`, `Audit`, `Settings`, `Maintenance`, elk met `Domain/`,
`UseCase/` en `Infrastructure/` (`.gitkeep` waar nog leeg), plus `src/Shared/Domain/` en
`src/Shared/Infrastructure/`. `tests/` volgt dezelfde indeling.

### T4. Kwaliteitstools

- `phpstan.dist.neon`: level max, `phpVersion: 80400`, paden `src`, `tests`, `config`, `public`,
  `bin`; Symfony-extensie met de container-XML, deprecation- en PHPUnit-regels, PHPat.
- `mago.toml`: opmaak, lint en analyse; de regels staan in `docs/code-style.md`.
- `rector.php`: PHP 8.4-set, Symfony-sets op basis van `composer.json`, attributen-sets,
  dead code en type declarations.
- `phpunit.dist.xml`: `failOnDeprecation`, `failOnNotice`, `failOnWarning`,
  `failOnPhpunitDeprecation`, `deprecationTrigger` voor `trigger_deprecation` en Doctrine.
- Composer-scripts: `composer test` (PHPUnit), `composer test:unit` (alleen unit-tests),
  `composer check` (alles, zoals CI), `composer fix` (Rector, dan Mago lint-fixes en Mago-opmaak).

### T5. Architectuurregels (test eerst)

Tests eerst, in `tests/Architecture/`:

- `ArchitectureRulesTest` draait PHPStan met alleen de PHPat-regels op een map met opzettelijke
  overtredingen (`tests/Architecture/Fixtures/`, buiten de autoload en buiten de gewone
  PHPStan-run). Per regel één overtreding; de test eist dat elke regel afgaat en dat de melding naar
  `docs/architecture.md` verwijst.
- `ModuleStructureTest` (beslissing 5).

Daarna de PHPat-regels, elk met een `because()`-tekst met de reden en een link naar de sectie in
`docs/architecture.md`:

1. `*\Domain` hangt alleen af van de eigen module, `Shared\Domain`, PHP zelf en
   Doctrine-mapping-attributen.
2. Invoerklassen en handlers hangen niet af van HttpFoundation, Routing, het MCP SDK of
   `*\Infrastructure`.
3. `*Controller` en `*Tool` hangen alleen af van de eigen handler, invoer en uitvoer in hun map,
   en van Symfony's HTTP-laag (controller) of het MCP SDK (tool); geen Doctrine, geen `*\Domain`.
   `*Output` hangt niet af van `*\Domain`, zodat er geen entiteiten in de uitvoer komen.
4. Een module gebruikt een andere module alleen via diens `UseCase`-handlers (met invoer en
   uitvoer) of via interfaces in `Shared\Domain`.
5. Twig alleen in de mailinfrastructuur en de installer.

Regel 6 uit het plan (frontend roept alleen endpoints uit de OpenAPI-spec aan) hoort bij fase 4.

### T6. Generator `app:make:use-case` (test eerst)

Tests eerst (`CommandTester`, schrijft naar een tijdelijke map):

- maakt de vijf bestanden met juiste namespace, klassenamen en de route
  `POST /api/v1/<naam-in-kebab-case>` als startpunt;
- weigert een onbekende module, `Shared`, een naam die geen PascalCase is en een bestaande map,
  zonder iets te overschrijven;
- een vers gegenereerde use case doorstaat PHPStan level max met alle PHPat-regels.

Daarna de command en sjablonen.

### T7. Voorbeeld-use case `CheckHealth`

`bin/console app:make:use-case Maintenance CheckHealth`, daarna invullen. Unit-test voor de
handler, functionele test voor `GET /api/v1/health`. Daarmee is het klaar-criterium "één voorbeeld
via de generator doorstaat alle PHPat-regels" aantoonbaar in CI.

### T8. GitHub

- `.github/workflows/ci.yml` (pull requests en pushes naar `develop` en `main`, PHP 8.4) draait
  `composer check`: `composer validate --strict`, `composer audit`, container- en YAML-lint,
  `bin/console debug:container --deprecations` dat faalt bij meldingen, Mago (opmaak, lint,
  analyse), Rector (`--dry-run`), PHPStan en PHPUnit.
  Minimale `permissions`, actions vastgepind op commit-SHA.
- `.github/workflows/weekly.yml`:
  - `composer audit` op `develop`; opent of werkt een issue bij als er een advies is;
  - vooruitkijk-job met PHP 8.5 en de nieuwste Symfony 8: zet de `7.4.*`-eisen in de job om naar
    `8.*`, `composer update`, dan dezelfde controles; niet-blokkerend, opent een issue bij falen.
- `.github/dependabot.yml`: Composer en GitHub Actions, wekelijks, `target-branch: develop`,
  patch-updates gegroepeerd, geen major-updates van Symfony (v4 blijft op 7.4 LTS).
- `.github/workflows/dependabot-automerge.yml`: zet automatisch samenvoegen aan voor patch- en
  minor-updates van Dependabot, behalve minor-updates van pakketten onder 1.0. Werkt pas als `develop` beschermd is met verplichte CI-checks (zie
  Afspraken); anders zou GitHub zonder groene CI samenvoegen.
- `SECURITY.md`: privé melden via GitHub, welke versies ondersteund worden, reactietermijn als
  inspanning, geen garantie (past bij "as is").

### T9. Documentatie

Alles in het Engels, behalve `docs/glossary.md` (Nederlandse begrippen en hun codenamen) en de
README (Nederlands met een korte Engelse samenvatting).

- `docs/getting-started.md`: vereisten, installeren, starten, testen, controles, het recept voor een
  nieuwe use case.
- `docs/configuration.md`: de omgevingsvariabelen die nu bestaan en waar geheimen horen.
- `docs/architecture.md`: modules, de vijf bestanden per use case, `CheckHealth` volledig
  uitgewerkt, het recept "nieuwe functie toevoegen", en per regel een sectie (de anchors waar de
  PHPat-meldingen naar verwijzen).
- `docs/glossary.md`: Nederlandse begrippen naar codenamen, uit Domeinmodel en Codestructuur in het
  plan (ronde = `Round`, loopmaatje = buddy, aanmelding = `Participation`, terugmelding =
  `RoundReport`, ...).
- `docs/operations.md`: GitHub-repo en branches, CI en de wekelijkse jobs, Dependabot, privé
  melden van kwetsbaarheden, de instellingen op GitHub die met de hand gezet worden, de geheimen die
  er zijn en waar ze staan, en de overstap naar Symfony 8.4 LTS in 2028. Hosting, cron en back-ups
  volgen in fase 6.
- `docs/testing.md`: testindeling, wat elke controle doet en hoe je hem lokaal draait.
- `docs/gotchas.md`: lokaal PHP 8.5 tegenover 8.4 als doel, de PHPStan-container-XML die een
  opgewarmde cache vraagt, en wat er verder tijdens deze fase opvalt.
- `docs/adr/0001-modular-monolith-with-use-case-folders.md`.
- `docs/admin-guide/` en `docs/user-guide/` leeg (`.gitkeep`).
- `CHANGELOG.md` met een sectie "Unreleased".
- `README.md`: de licentiezinnen uit het plan (vrij te gebruiken voor niet-commerciële doelen,
  bedoeld voor buurt- en bewonersverenigingen, as is zonder support) en een verwijzing naar
  `docs/getting-started.md`.
- `AGENTS.md`: sectie Commands invullen, en de uitvoerregel uit beslissing 3 bij Structure.

### T10. Verifiëren en opleveren

- Lokaal `composer check` en `composer validate --strict`, uitvoer in het verslag.
- Push van `feature/phase-0`, GitHub Actions groen, pull request naar `develop` (zie Afspraken).

## Afspraken

1. **Commits en push.** Per taak komen er een commitbericht en de commando's;
   de ontwikkelaar commit en pusht. Daarna volgt de pull request met `gh pr create`.
2. **Instellingen op GitHub.** Branch protection op `develop` met verplichte CI-checks, "Allow
   auto-merge" en privé melden van kwetsbaarheden worden na de eerste groene CI-run gezet met
   `gh api`, en staan beschreven in `docs/operations.md`.
3. Beslissingen 3 en 4 en de afhankelijkheden zijn goedgekeurd.

## Opgemerkt, buiten deze fase

- Fase 0 in het plan noemt docs (`configuration`, `testing`, `gotchas`) die niet in de prompt staan;
  ze zitten in dit taakplan (beslissing 8).
