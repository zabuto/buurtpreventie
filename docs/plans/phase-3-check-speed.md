# Fase 3, aanvulling: waar `symfony composer check` zijn tijd laat

Status: meten goedgekeurd op 17 november 2026; de maatregelen wachten op de uitkomst.

Bron: `composer.json` (script `check`), `phpunit.dist.xml`, `phpstan.dist.neon`, `rector.php`, `mago.toml`,
`.github/workflows/ci.yml`, `.phpunit.cache/test-run-history` (laatste lokale run), de stappen van de laatste groene
CI-run (16 november 2026), `tests/Architecture/PhpStanProcess.php`, `config/packages/nelmio_api_doc.yaml` en
`php -i` op de ontwikkelmachine.

## Doel en klaar-criterium

Weten waar de ruim tien minuten van een lokale `symfony composer check` heen gaan, en per maatregel wat die oplevert
en kost, voordat er iets verandert. Dit plan meet en stelt voor; de maatregelen zelf komen na goedkeuring in een
tweede deel van dit plan.

Klaar als:

- er een tabel is met de tijd per stap van `check`, lokaal gemeten zoals de trage run draaide, en dezelfde stappen
  in de varianten uit T2 en T3;
- elke stap die meer dan 10% van de totale tijd kost een verklaring heeft die met een meting is bevestigd;
- elke kandidaat-maatregel een gemeten of afgeleide winst, een kost en een risico heeft;
- de keuze uit de maatregelen is goedgekeurd.

## Vastgesteld

1. **CI doet hetzelfde werk in anderhalve minuut.** In de job Checks duurde `composer check` 87 seconden; daar slaan
   de databasetests over. Die draaien in de job Database in 29 seconden per database. Lokaal draait
   `symfony composer check` beide, dus ongeveer twee minuten werk kost lokaal vijf keer zoveel.
2. **Lokaal is Xdebug geladen met `xdebug.mode=develop`**; CI laadt geen Xdebug (`coverage: pcov`). OPcache staat op
   de CLI uit, op beide.
3. **De laatste lokale PHPUnit-run telt 2514 tests en 190 seconden testtijd** (zonder het opstarten van PHPUnit en
   de extensies). 50 tests van een seconde of langer nemen 125 seconden, twee derde; 1655 tests samen 1 seconde.
4. **De zwaarste testklassen**:

   | Klasse | Tests | Tijd |
   | --- | --- | --- |
   | `HttpMatrixTest` | 385 | 45 s |
   | `LoadFixturesCommandTest` | 12 | 42 s |
   | `ArchitectureRulesTest` | 19 | 28 s |
   | `LoadedSettingsAndChainTest` | 5 | 15 s |
   | `OpenApiCoverageTest`, `OpenApiDocumentTest`, `OpenApiNamingTest` | 13 | 19 s |

5. **Een voorbeeldset laden kost lokaal ongeveer 7 seconden per test**; de traagste test
   (`it_builds_the_same_people_and_pattern_from_today_on_any_day`) 14 seconden. In CI laadt de job Database alle drie
   sets, elk met een console-start en `app:audit:verify`, in 9 tot 10 seconden samen.
6. **`ArchitectureRulesTest` start PHPStan per dataset** in een eigen proces met een lege `tmpDir`, ongeveer 1,4
   seconde per run. De docblock van `PhpStanProcess` legt uit waarom de cache leeg is: een oude uitkomst mag een
   gewijzigde regel niet verbergen.
7. **De OpenAPI-beschrijving wordt per test opnieuw opgebouwd.** Elke `api_v1_docs`-rij in `HttpMatrixTest` kost
   ongeveer 2,2 seconden (zes rijen), en `nelmio_api_doc.yaml` zet geen cache.
8. **PHPStan en Rector bewaren hun cache in `sys_get_temp_dir()`**: `phpstan.dist.neon` zet geen `tmpDir`,
   `rector.php` geen `withCache`. In de sandbox van Claude Code is dat `/tmp/claude-501`, in de eigen shell
   `/var/folders/…/T`. Er staat nu alleen een PHPStan-cache in de laatste.
9. **Alle stappen draaien na elkaar, PHPUnit in één proces**, in willekeurige volgorde.
10. **Composer start zijn `@php`-scripts met Xdebug** (`extension_loaded('xdebug')` is waar, `xdebug.mode` is
    `develop`, in een los Composer-project gemeten). PHPUnit en de vijf console-stappen van `check` draaien dus met
    Xdebug.
11. **Buiten de sandbox is `TMPDIR` dezelfde map als in de eigen shell** (`/var/folders/…/T`). Een
    `symfony composer check` van de agent, die buiten de sandbox draait, deelt de analysecaches met de gebruiker;
    alleen PHPStan of Rector binnen de sandbox schrijft naar `/tmp/claude-501`.

12. **Alle PHPUnit-runs op één machine delen `buurtpreventie_test`**, tenzij `TEST_TOKEN` gezet is
    (`dbname_suffix: '_test%env(default::TEST_TOKEN)%'`). Elke run met databasetests begint met
    `doctrine:database:drop`, dus een tweede run die start terwijl de eerste loopt, haalt de tabellen onder de
    eerste weg. `grant-prefixed-databases.sh` geeft de gebruiker elke database met het voorvoegsel
    `buurtpreventie_`, dus een eigen naam per run kan zonder extra rechten.

## Gemeten (voorlopig)

Vier keer `symfony composer check` na elkaar, 17 november 2026, terwijl een andere sessie van Claude Code op
dezelfde machine ook PHPUnit met databasetests draaide. De tijden zijn dus te hoog en de rode runs komen door die
andere sessie.

| Run | Totaal | PHPUnit | Alle andere stappen | Uitkomst |
| --- | --- | --- | --- | --- |
| Xdebug, 1 | 135 s | 124 s | 11 s | groen |
| Xdebug, 2 | 134 s | 124 s | 10 s | 20 databasetests rood |
| `XDEBUG_MODE=off`, 1 | 137 s | 129 s | 8 s | 6 databasetests rood |
| `XDEBUG_MODE=off`, 2 | 98 s | 89 s | 9 s | 12 databasetests rood |

- De ruim tien minuten komen in een losse run niet terug: de check duurt ruim twee minuten, al onder de streeftijd.
  Wat wel overeenkomt: twee sessies tegelijk, die elkaars testdatabase weggooien en om de processor strijden.
- PHPStan, Rector, Mago en de console-stappen nemen samen 8 tot 11 seconden; alle winst zit in PHPUnit.
- Het verschil met en zonder Xdebug valt binnen de ruis van deze metingen; T2 herhaalt ze zonder andere sessie.
- De rode tests vallen per klasse om (alle 19 van `DoctrineMemberRepositoryTest`, alle 12 van
  `LoadFixturesCommandTest`) met `Table … doesn't exist`, zoals Vastgesteld 12 voorspelt.

Na maatregel H, zonder andere sessie:

| Meting | Tijd | Uitkomst |
| --- | --- | --- |
| `symfony composer check`, met Xdebug | 129 s (PHPUnit 119 s) | groen, geen testdatabase over |
| `phpunit --group database` | 62 s (CI: 29 s) | groen |
| twee keer `phpunit --group database` tegelijk, één met `TEST_TOKEN=_keep` | 63 s | beide groen, `_keep` blijft staan |
| `phpunit --group database` zonder bereikbare database | 2 s | alles overgeslagen, geen drop |

- De check haalt de streeftijd zonder verdere maatregel. A tot en met G zijn daarmee optioneel; T2 (Xdebug) en T4
  (dure tests) blijven zinvol als de testsuite groeit.
- Twee runs tegelijk kosten samen niet meer tijd dan één: de databasetests wachten vooral op de database, niet op
  de processor.

## Hypotheses

Op volgorde van verwachte winst; T1 tot en met T4 bevestigen of verwerpen ze.

- **H1. Xdebug vertraagt alles wat geen eigen herstart heeft.** PHPStan, Rector en Composer zelf herstarten zonder
  Xdebug (xdebug-handler, na te gaan in T2); PHPUnit en `bin/console` niet, en Composer geeft Xdebug door aan zijn
  `@php`-scripts (Vastgesteld 10).
- **H2. De analysecaches gaan verloren tussen runs** als de agent in de sandbox en daarbuiten draait, of de gebruiker
  in de eigen shell: elke run analyseert dan alles opnieuw.
- **H3. Een paar testklassen doen hetzelfde dure werk vaak**: de voorbeeldset laden, de OpenAPI-beschrijving
  opbouwen, PHPStan starten.
- **H4. De database onder Docker op macOS** maakt de databasetests trager dan in CI.

## Taken

Elke meting met `/usr/bin/time -p`, buiten de sandbox (de Symfony-console leest `.env`), met de database en Mailpit
van `compose.yaml` aan, en zonder andere zware processen. De gebruiker start de services; ontbreekt er een, dan noemt
de agent hem met zijn poort. Uitkomsten in `$TMPDIR`, niet in de repo.

### T1. Nulmeting per stap

- Elke regel van `check` los draaien, zoals `symfony composer check` hem start, twee keer na elkaar (koud en warm),
  en de tijd per stap in de tabel zetten.
- PHPUnit per groep: zonder de groepen `integration`, `functional` en `database`, alleen `integration` en
  `functional`, alleen `database`. Met `--log-junit` voor de tijd per test.
- De opstarttijd van PHPUnit apart: de wandkloktijd min de som van de testtijden.

### T2. Xdebug (H1)

- Nagaan of PHPStan, Rector en Composer zonder Xdebug herstarten, en of een `@php`-script van Composer Xdebug
  geladen heeft (`php -r` met `extension_loaded('xdebug')` als script).
- T1 herhalen met `XDEBUG_MODE=off`.

### T3. Caches (H2)

- Nagaan welke `TMPDIR` een run buiten de sandbox ziet en waar PHPStan en Rector hun cache dan schrijven.
- PHPStan, Rector en `mago analyze` twee keer na elkaar draaien, met en zonder een cache in `var/cache/`.

### T4. Dure tests (H3, H4)

- Eén keer de demoset laden met `XDEBUG_MODE=profile` (Xdebug is er; geen andere profiler installeren) en per
  onderdeel de tijd: wachtwoord-hashes, flushes, auditketen, `app:audit:verify`.
- De OpenAPI-beschrijving één keer per proces opbouwen tegenover per test: de winst schatten uit de tijd per
  `api_v1_docs`-rij en de 13 OpenAPI-tests.
- `ArchitectureRulesTest`: de tijd van één PHPStan-run over alle fixtures tegenover 19 losse runs.
- De databasetests lokaal tegenover de 29 seconden in CI.

### T5. Uitkomst en voorstel

- De tabel en per hypothese bevestigd of verworpen, in dit plan onder een nieuwe sectie "Gemeten".
- Per kandidaat-maatregel hieronder de gemeten winst, de kost en het risico; de rest valt af.

## Kandidaat-maatregelen (na meting te kiezen)

- **H. Een eigen testdatabase per run** (gebouwd op 17 november 2026). `RunDatabaseToken::claim()` zet
  `TEST_TOKEN` op `_run` plus acht hexadecimale tekens als hij leeg is, in `$_SERVER`, `$_ENV` en met `putenv`, zodat
  `bin/console` in `SchemaPreparation` en de processen van de concurrency-tests dezelfde database gebruiken.
  `DropRunDatabase` gooit die database weg op `Application\Finished`, alleen als de voorbereiding de server bereikte.
  Niet op `ExecutionFinished`: DAMA houdt tot `TestRunner\Finished` een transactie open, en `DROP DATABASE` wacht daar
  zonder tijdslimiet op (gezien: een run die na de laatste test bleef hangen). Een gezette `TEST_TOKEN` blijft gelden
  en zijn database blijft staan (ParaTest zet die per proces, zie G). `docs/testing.md` noemt hoe je een database van
  een afgebroken run opruimt.

- **A. Xdebug uit voor de checks**: `@putenv XDEBUG_MODE=off` vooraan in `check` en `test`. Kost: niets in CI;
  `test:coverage` lokaal blijft `XDEBUG_MODE=coverage` nodig hebben. Of Xdebug standaard uit in de eigen `php.ini`:
  een keuze voor de machine, buiten de repo.
- **B. Een vaste plek voor de analysecaches**: `tmpDir: %currentWorkingDirectory%/var/cache/phpstan` en
  `withCache(var/cache/rector)`, zodat sandbox en shell dezelfde cache delen. `PhpStanProcess` houdt zijn eigen lege
  `tmpDir`.
- **C. De OpenAPI-beschrijving één keer per testproces**, of een cache-pool voor NelmioApiDocBundle in `when@test`.
- **D. `ArchitectureRulesTest` met één PHPStan-run per testproces.** De eerste test die de analyse nodig heeft,
  draait PHPStan over alle fixtures en bewaart het onveranderlijke rapport in een statische eigenschap; elke dataset
  leest alleen de meldingen van zijn eigen bestand (`files` in het JSON-rapport is per bestand). Daarmee:
  - blijft elke test los te draaien (`--filter` op één dataset start de analyse zelf) en maakt de volgorde niet
    uit, want welke test ook eerst komt, het rapport is hetzelfde;
  - wordt alleen een fixture met een verkeerde regel rood, niet de rest; alleen een fout van PHPStan zelf
    (`errors` in het rapport, zoals een parsefout of een kapotte configuratie) maakt alle datasets rood, en dat
    hoort zo;
  - blijft de reden van de lege `tmpDir` overeind: één verse analyse per proces, nooit een cache van een eerdere
    run;
  - doet `every_violation_points_to_the_architecture_document` hetzelfde rapport na, in plaats van een eigen run.

  Niet in de dataprovider: PHPUnit roept die aan bij het opbouwen van de suite, ook als de groep `integration` is
  uitgesloten. Na te gaan in T4: of de meldingen per bestand gelijk zijn aan die van een run op dat ene bestand
  (alle 19 datasets groen met de nieuwe opzet, en rood als je één verwachte regel weghaalt).
- **E. Laden van de voorbeeldsets**: wat T4 aanwijst. Ook: minder tests die de hele demoset laden, als de
  bewering met een kleinere set kan.
- **F. Werkwijze van de agent** (gekozen, zie Beslissingen): tijdens een taak alleen de geraakte tests en PHPStan
  op de gewijzigde paden, de volledige `symfony composer check` één keer voordat de taak klaar heet. Geen
  codewijziging.
- **G. PHPUnit parallel (ParaTest)**: nieuwe afhankelijkheid, en de databasetests hebben dan een database per proces
  nodig. Alleen als A tot en met F te weinig opleveren.

## Beslissingen

1. **Streeftijd: een lokale `symfony composer check` onder drie minuten**, zonder dat een test minder bewijst. Een
   maatregel die een bewering zwakker maakt of een test weghaalt, valt af, ook als hij de grens haalt.
2. **Tijdens het werk draait de agent alleen de geraakte tests**, plus de tests die over het geheel gaan zodra de
   wijziging ze raakt: `HttpMatrixTest` bij een route of Voter, de OpenAPI-tests bij een endpoint,
   `ArchitectureRulesTest` en `ModuleStructureTest` bij een nieuwe klasse of map.
3. **De volledige check draait één keer voordat een taak klaar heet**, niet alleen in CI. CI blijft de poort voor
   het mergen, maar is alleen niet genoeg: een push vraagt elke keer toestemming (`AGENTS.md`), een rode CI komt pas
   na de push en kost dan een tweede ronde, en de definition of done vraagt elke check vóór "klaar". De ontwikkelaar
   kan de check altijd tussendoor vragen.

4. **Beslissing 2 en 3 staan in `AGENTS.md`**, onder "Definition of done for every task", zodat ze voor elke agent
   in deze repo gelden.
5. **De ontwikkeldatabase `buurtpreventie` blijft gedeeld: één sessie tegelijk** laadt er voorbeelddata in of draait
   de app ertegen, Hurl inbegrepen. Dat staat in `AGENTS.md` onder "Data and secrets". Maatregel H geldt alleen
   voor de testdatabase.
