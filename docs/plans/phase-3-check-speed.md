# Fase 3, aanvulling: waar `symfony composer check` zijn tijd laat

Status: afgerond op 17 november 2026. De oorzaak is gevonden en verholpen (maatregel H); de check haalt de
streeftijd. De overige maatregelen zijn niet nodig voor die streeftijd en blijven open voor als de testsuite groeit.

Bron: `composer.json` (script `check`), `phpunit.dist.xml`, `phpstan.dist.neon`, `rector.php`, `mago.toml`,
`.github/workflows/ci.yml`, `config/packages/doctrine.yaml`, `config/packages/nelmio_api_doc.yaml`,
`docker/database/grant-prefixed-databases.sh`, `tests/Database/`, `tests/Architecture/PhpStanProcess.php`,
`vendor/dama/doctrine-test-bundle/src/PHPUnit/PHPUnitExtension.php`, `.phpunit.cache/test-run-history`, de stappen van
de laatste groene CI-run (16 november 2026), `php -i` op de ontwikkelmachine en de metingen hieronder.

## Doel en klaar-criterium

Weten waar de ruim tien minuten van een lokale `symfony composer check` heen gingen, en per maatregel wat die oplevert
en kost, voordat er iets verandert.

Klaar als:

- er een tabel is met de tijd per stap van `check`, lokaal gemeten (Gemeten, tabel 1);
- elke stap die meer dan 10% van de totale tijd kost een verklaring heeft die met een meting is bevestigd: alleen
  PHPUnit, en de tijd per testklasse staat onder Vastgesteld 4;
- de oorzaak van de ruim tien minuten bevestigd is (Vastgesteld 12, Gemeten);
- de keuze uit de maatregelen is goedgekeurd (Beslissingen 6 en 7).

De varianten met en zonder Xdebug en met en zonder cache (T2, T3) zijn niet zonder storing gemeten. Ze zijn niet
nodig voor de streeftijd en staan onder Open punten.

## Vastgesteld

1. **CI doet het werk in ongeveer twee minuten.** In de job Checks duurde `composer check` 87 seconden; daar slaan
   de databasetests over. Die draaien in de job Database in 29 seconden per database. Lokaal draait
   `symfony composer check` beide.
2. **Lokaal is Xdebug geladen met `xdebug.mode=develop`**; CI laadt geen Xdebug (`coverage: pcov`). OPcache staat op
   de CLI uit, op beide.
3. **Een lokale PHPUnit-run uit `.phpunit.cache/test-run-history` telt 2514 tests en 190 seconden testtijd**
   (zonder het opstarten van PHPUnit en de extensies). 50 tests van een seconde of langer nemen 125 seconden, twee
   derde; 1655 tests samen 1 seconde.
4. **De zwaarste testklassen** in die run:

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
7. **De OpenAPI-beschrijving wordt per test opgebouwd.** Elke `api_v1_docs`-rij in `HttpMatrixTest` kost ongeveer
   2,2 seconden (zes rijen), en `nelmio_api_doc.yaml` zet geen cache.
8. **PHPStan en Rector bewaren hun cache in `sys_get_temp_dir()`**: `phpstan.dist.neon` zet geen `tmpDir`,
   `rector.php` geen `withCache`. In de sandbox van Claude Code is dat `/tmp/claude-501`, daarbuiten en in de eigen
   shell `/var/folders/…/T`. Een `symfony composer check` van de agent draait buiten de sandbox (de console leest
   `.env`) en deelt de analysecaches dus met de gebruiker.
9. **Alle stappen draaien na elkaar, PHPUnit in één proces**, in willekeurige volgorde.
10. **Composer start zijn `@php`-scripts met Xdebug** (`extension_loaded('xdebug')` is waar, `xdebug.mode` is
    `develop`, in een los Composer-project gemeten). PHPUnit en de vijf console-stappen van `check` draaien dus met
    Xdebug.
11. **De testdatabase heet `buurtpreventie_test` plus `TEST_TOKEN`** (`dbname_suffix:
    '_test%env(default::TEST_TOKEN)%'`), en elke run met databasetests begint met `doctrine:database:drop`. Twee runs
    met dezelfde naam halen dus elkaars tabellen weg. `grant-prefixed-databases.sh` geeft de gebruiker elke database
    met het voorvoegsel `buurtpreventie_`, dus een eigen naam per run kan zonder extra rechten.
12. **De ruim tien minuten kwamen van twee sessies tegelijk.** Een andere sessie van Claude Code draaide op dezelfde
    machine PHPUnit met databasetests in dezelfde testdatabase. Rode runs vielen per klasse om met
    `Table … doesn't exist` (Gemeten, tabel 2), en een rode run moet opnieuw. Alleen draait de check in 129 seconden.
13. **DAMA houdt een transactie open tot `TestRunner\Finished`** (`PHPUnitExtension`), en `DROP DATABASE` wacht op die
    transactie zonder tijdslimiet. Een drop op `ExecutionFinished` liet de run na de laatste test hangen; op
    `Application\Finished` is de transactie al teruggedraaid.

## Gemeten

**Tabel 1: tijd per stap**, de eerste van vier runs met Xdebug, 17 november 2026 (groen, 135 s in totaal). Een andere
sessie draaide tegelijk tests, dus de tijden zijn eerder te hoog dan te laag.

| Stap | Tijd |
| --- | --- |
| `composer validate --strict`, `composer audit` | 1,2 s |
| `cache:warmup`, `lint:container`, `lint:yaml`, deprecations | 3,0 s |
| Mago format, lint en analyze | 1,1 s |
| Rector | 1,2 s |
| PHPStan | 3,8 s |
| PHPUnit | 124 s |

**Tabel 2: vier runs na elkaar**, terwijl een andere sessie dezelfde testdatabase gebruikte.

| Run | Totaal | PHPUnit | Alle andere stappen | Uitkomst |
| --- | --- | --- | --- | --- |
| Xdebug, 1 | 135 s | 124 s | 11 s | groen |
| Xdebug, 2 | 134 s | 124 s | 10 s | 20 databasetests rood |
| `XDEBUG_MODE=off`, 1 | 137 s | 129 s | 8 s | 6 databasetests rood |
| `XDEBUG_MODE=off`, 2 | 98 s | 89 s | 9 s | 12 databasetests rood |

**Tabel 3: met maatregel H**, zonder andere sessie.

| Meting | Tijd | Uitkomst |
| --- | --- | --- |
| `symfony composer check`, met Xdebug | 129 s (PHPUnit 119 s) | groen, geen testdatabase over |
| `phpunit --group database` | 62 s (CI: 29 s) | groen |
| twee keer `phpunit --group database` tegelijk, één met `TEST_TOKEN=_keep` | 63 s | beide groen, `_keep` blijft staan |
| `phpunit --group database` zonder bereikbare database | 2 s | alles overgeslagen, geen drop |

- PHPUnit is ruim 90% van de check; de andere stappen nemen samen 8 tot 11 seconden.
- Het verschil met en zonder Xdebug valt binnen de ruis van tabel 2.
- Twee databaseruns tegelijk kosten samen niet meer tijd dan één: de databasetests wachten vooral op de database,
  niet op de processor.

## Hypotheses

1. **Xdebug vertraagt wat geen eigen herstart heeft.** Deels bevestigd: PHPUnit en de console-stappen draaien met
   Xdebug (Vastgesteld 10). De winst is niet zonder storing gemeten (Open punten).
2. **De analysecaches gaan verloren tussen runs.** Verworpen voor de volledige check: die deelt de cache met de eigen
   shell (Vastgesteld 8). PHPStan en Rector samen nemen 5 seconden.
3. **Een paar testklassen doen hetzelfde dure werk vaak.** Bevestigd in de tijden per klasse (Vastgesteld 4 tot en
   met 7); de winst per maatregel is niet gemeten.
4. **De database onder Docker op macOS maakt de databasetests trager.** Bevestigd: 62 seconden lokaal, 29 in CI. De
   oorzaak is niet onderzocht.
5. **Twee sessies tegelijk delen de testdatabase.** Bevestigd en de oorzaak van de ruim tien minuten (Vastgesteld 11
   en 12).

## Taken

Elke meting buiten de sandbox (de Symfony-console leest `.env`), met de database en Mailpit van `compose.yaml` aan.
De gebruiker start de services. Uitkomsten in `$TMPDIR`, niet in de repo.

- **T1. Nulmeting per stap.** Gedaan, met de tijd per stap uit `symfony composer check -v` (tabel 1 en 2). PHPUnit
  per groep en met `--log-junit` is niet gedaan.
- **T2. Xdebug.** Gedaan: Composer geeft Xdebug door aan `@php`-scripts. Niet gedaan: of PHPStan en Rector zonder
  Xdebug herstarten, en de meting met `XDEBUG_MODE=off` zonder storing.
- **T3. Caches.** Gedaan: welke `TMPDIR` een run buiten de sandbox ziet (Vastgesteld 8). Niet gedaan: PHPStan, Rector
  en Mago met en zonder cache in `var/cache/`.
- **T4. Dure tests.** Niet gedaan.
- **T5. Uitkomst en voorstel.** Gedaan: dit plan.
- **T6. Maatregel H bouwen** (test first). Gedaan: `RunDatabaseTokenTest` en de nieuwe datasets in
  `DatabaseAvailabilityTest`; de drop aan het eind en twee runs tegelijk met echte runs gecontroleerd (tabel 3);
  `docs/testing.md`, `docs/getting-started.md` en `CHANGELOG.md` bijgewerkt.

## Maatregelen

- **H. Een eigen testdatabase per run** (gebouwd). `RunDatabaseToken::claim()` zet `TEST_TOKEN` op `_run` plus acht
  hexadecimale tekens als hij leeg is, in `$_SERVER`, `$_ENV` en met `putenv`, zodat `bin/console` in
  `SchemaPreparation` en de processen van de concurrency-tests dezelfde database gebruiken. `DropRunDatabase` gooit
  die database weg op `Application\Finished`, alleen als de voorbereiding de server bereikte (Vastgesteld 13). Een
  gezette `TEST_TOKEN` blijft gelden en zijn database blijft staan (ParaTest zet die per proces, zie G).
  `docs/testing.md` noemt hoe je een database van een afgebroken run opruimt.
- **F. Werkwijze van de agent** (gekozen, Beslissing 2 en 3). Geen codewijziging.

Open, niet nodig voor de streeftijd:

- **A. Xdebug uit voor de checks**: `@putenv XDEBUG_MODE=off` vooraan in `check` en `test`. Kost: niets in CI;
  `test:coverage` lokaal blijft `XDEBUG_MODE=coverage` nodig hebben. Of Xdebug standaard uit in de eigen `php.ini`:
  een keuze voor de machine, buiten de repo.
- **B. Een vaste plek voor de analysecaches**: `tmpDir: %currentWorkingDirectory%/var/cache/phpstan` en
  `withCache(var/cache/rector)`, zodat sandbox en shell dezelfde cache delen. Winst hooguit enkele seconden (tabel 1).
  `PhpStanProcess` houdt zijn eigen lege `tmpDir`.
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
  uitgesloten. Na te gaan: of de meldingen per bestand gelijk zijn aan die van een run op dat ene bestand (alle 19
  datasets groen met de nieuwe opzet, en rood als je één verwachte regel weghaalt). Verwachte winst ongeveer 25
  seconden.
- **E. Laden van de voorbeeldsets**: eerst meten waar de 7 seconden per set heen gaan (wachtwoord-hashes, flushes,
  auditketen). Ook: minder tests die de hele demoset laden, als de bewering met een kleinere set kan.
- **G. PHPUnit parallel (ParaTest)**: nieuwe afhankelijkheid. Met H heeft elk proces al een eigen database.

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
6. **Maatregel H is goedgekeurd en gebouwd** (17 november 2026).
7. **A tot en met E en G wachten**: de check haalt de streeftijd zonder.

## Open punten

- T2 en T3 zonder storing herhalen, en T4, zodra de check de streeftijd nadert.
- Waarom de databasetests lokaal twee keer zo lang duren als in CI (hypothese 4).
- Twee sessies in één werkmap delen ook de uitgecheckte branch; een eigen `git worktree` per sessie voorkomt dat.
  Buiten deze aanvulling.
