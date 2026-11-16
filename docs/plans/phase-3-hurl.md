# Fase 3, aanvulling: Hurl in plaats van de Postman-collectie

Status: goedgekeurd op 16 november 2026.

Bron: `postman/buurtpreventie.postman_collection.json`, `postman/local.postman_environment.json`,
`tests/Support/PostmanRequests.php`, `tests/Shared/Infrastructure/Http/OpenApiCoverageTest.php`, `docs/testing.md`
(Postman collection), `docs/plans/phase-3.md`, `AGENTS.md`, en de documentatie van Hurl 8.1.0 (hurl.dev, manual en
release notes).

## Doel en klaar-criterium

De controle van een draaiende app van buitenaf staat in platte tekst, één bestand per onderwerp, dat elk op zichzelf
draait en in een diff leesbaar is. Postman en Newman verdwijnen uit de repo en de documentatie.

Klaar als:

- `hurl/` per onderwerp een `.hurl`-bestand heeft dat zelf inlogt en niet afhangt van een ander bestand, en
  `hurl --test` alle bestanden groen draait tegen de app onder de Symfony CLI met voorbeelddata en Mailpit, zowel
  parallel (standaard) als met `--jobs 1`;
- elke controle uit de collectie in een `.hurl`-bestand staat, behalve de twee onder Beslissing 5 en 6, en die twee
  aantoonbaar door PHPUnit gedekt zijn;
- `OpenApiCoverageTest` elk verzoek uit `hurl/*.hurl` naar `{{baseUrl}}` tegen de spec houdt, met een eigen
  in-memory test voor de parser;
- `postman/` en `tests/Support/PostmanRequests.php` weg zijn en `git grep -i -E 'postman|newman'` alleen nog
  `docs/plans/phase-2.md` en dit plan vindt;
- `docs/testing.md`, `docs/architecture.md`, `docs/plans/phase-3.md` en `CHANGELOG.md` bijgewerkt zijn;
- `symfony composer check` groen is.

## Vastgesteld

1. **De collectie is één JSON-bestand van 3281 regels** met 65 verzoeken in vier mappen (Public 5, Refusals 4,
   Login with a code 21, Login with a password 35). De laatste map bevat rondes, leden, ontmoetingspunten,
   uitkomsten, instellingen en het auditlog, omdat ze één sessie delen. Het app-icoon staat er als
   `{{baseUrl}}{{iconUrl}}`, met het pad uit `/config`; `PostmanRequests` slaat het over.
2. **Een script op collectieniveau zet twee headers** op elk verzoek: `Sec-Fetch-Site: same-origin` (de app neemt
   een wijzigend verzoek alleen van de eigen origin aan) en `Accept-Language`.
3. **Verzoeken slaan zichzelf over** zonder `password`, `mailpitUrl`, `code` of `roundId`
   (`pm.execution.skipRequest()`).
4. **"New API token in a session that is not recent" haalt `bp_session` uit de cookie-opslag** en houdt
   `bp_remember`, zodat de volgende sessie nooit recent is.
5. **Drie controles eisen een exacte set sleutels** (`to.have.all.keys`): `group` in `/config`, elk lid in
   `/members` en elke loper in één ronde. Die van de instellingen eist alleen dat de sleutels er zijn
   (`include.all.keys`).
6. **De vervaldatum van het token rekent een script uit**: een dag vooruit, als `2026-11-17T10:00:00+00:00`.
   `expiresAt` is verplicht in `CreateApiToken`.
7. **`PostmanRequests` leest het JSON-formaat** en geeft `METHOD /pad` met de variabelen erin;
   `OpenApiCoverageTest::it_describes_every_request_of_the_postman_collection` houdt die tegen de spec. De parser
   heeft geen eigen test.
8. **Newman draait via `npx`** en staat niet in een manifest; de collectie hoort niet bij `composer check` of CI.
9. **PHPUnit dekt de twee controles die Hurl niet kan doen.** De exacte vorm van een lid en een loper staat in
   `ListMembersHandlerTest` en `GetRoundHandlerTest` (`assertSame` op de hele uitvoer); voor `group` in `/config`
   nagaan in T3. De 403
   `reauthentication_required` voor een sessie die niet recent is, staat in
   `ApiTokenEndpointsTest::it_refuses_a_new_token_in_a_session_that_is_not_recent`.

Uit de Hurl-documentatie (8.1.0, 27 oktober 2026):

10. `-H/--header` zet een header op elk verzoek (sinds 6.1.0).
11. In `--test` draaien bestanden standaard parallel; `--jobs 1` draait ze na elkaar. Elk bestand heeft een eigen
    cookie-opslag; verzoeken in één bestand delen die.
12. Een verzoek kan geen losse cookie uit de opslag halen. `[Cookies]` stuurt alleen een `Cookie`-header mee; of die
    een cookie met dezelfde naam vervangt, staat niet in de documentatie.
13. Er is geen predicaat voor een exacte set sleutels. Wel `exists`, `isList`, `isObject` (sinds 7.1.0), `count`
    en variabelen als waarde (`== {{n}}`).
14. `[Options] skip: true` bestaat per verzoek; of de waarde een variabele kan zijn, staat er niet.
15. `--variables-file` leest regels `naam=waarde`; `--variable` en `--secret` per stuk.
16. `-k/--insecure` en `-x/--proxy`; als omgevingsvariabelen noemt de documentatie alleen de kleine letters
    (`https_proxy`).
17. Een body uit een bestand (`file,naam;`) of een blok tekst, met de `Content-Type` die je zelf zet.

## Beslissingen

1. **Map `hurl/`, één bestand per onderwerp**, elk met de eigen login met wachtwoord en bevestiging vooraan:

   | Bestand | Inhoud | Inloggen |
   | --- | --- | --- |
   | `public.hurl` | health, OpenAPI-beschrijving, `/config` als bezoeker, app-icoon, manifest | nee |
   | `refusals.hurl` | de vier weigeringen van de login-endpoints | nee |
   | `account.hurl` | login met een code via Mailpit, eigen profiel, wijzigingen, API-tokens, activiteit, export, uitloggen, dezelfde code nog eens | code |
   | `rounds.hurl` | rondes van de komende week, één ronde, gaten, terug te melden, de 422's en 404's, de 403's als beheerder | wachtwoord |
   | `members.hurl` | ledenlijst, beheerlijst, één lid, de 422's en de 404 | wachtwoord |
   | `meeting-points-and-outcomes.hurl` | lijsten als lid en als beheerder, de 409, 404 en 422 | wachtwoord |
   | `settings.hurl` | instellingen, lege patch, kleur zonder contrast, tekst als logo, onbekend merkbeeld | wachtwoord |
   | `audit-log.hurl` | JSON, CSV en de 406 | wachtwoord |

   Omdat geen bestand iets van een ander nodig heeft, maakt de volgorde niet uit en mag Hurl ze parallel draaien.
   Alleen `account.hurl` vraagt een code aan, dus één run blijft één van de drie codes per uur gebruiken.
2. **Variabelen in `hurl/local.variables`**: `baseUrl`, `email`, `password`. `mailpitUrl` en `tokenExpiresAt` komen
   van de commandoregel. De vervaldatum rekent PHP uit, dat er altijd is:
   `--variable "tokenExpiresAt=$(php -r 'echo gmdate("c", time() + 86400);')"`.
3. **De twee vaste headers via `--header`** in het commando, niet per verzoek. Een bestand zonder die vlag krijgt een
   403 op elk wijzigend verzoek; `docs/testing.md` zegt dat.
4. **Niets slaat zichzelf over.** Alle variabelen zijn verplicht en een ontbrekende laat het bestand falen. Zonder
   Mailpit draai je alle bestanden behalve `account.hurl`. `rounds.hurl` eist ten minste één ronde in de komende week,
   die de voorbeelddata altijd heeft (nagaan in T3).
5. **De exacte vorm van `group`, een lid en een loper blijft bij PHPUnit** (Vastgesteld 9). Hurl controleert per sleutel dat
   die bestaat, dat elk lid er een heeft (`count == {{aantal}}`) en dat `email` ontbreekt (`count == 0`). Dat bewijst
   geen exacte set, wel wat van buitenaf telt.
6. **De 403 voor een sessie die niet recent is, gaat uit de controle van buitenaf.** Hurl kan `bp_session` niet uit
   de opslag halen (Vastgesteld 12) en de functionele test dekt het (Vastgesteld 9). Als T1 laat zien dat
   `[Cookies] bp_session: …` de opgeslagen cookie vervangt, blijft de stap erin.
7. **`HurlRequests` vervangt `PostmanRequests`**: een regex op regels `METHOD {{baseUrl}}/…`, zonder querystring,
   zonder `/api/v1/docs.json`, zonder andere hosts zoals Mailpit en zonder `{{baseUrl}}{{iconUrl}}`, dat geen pad
   uit de spec is. De test hoeft Hurl niet te draaien en `composer
   test` heeft Hurl niet nodig.
8. **Hurl blijft buiten `composer check` en CI**, net als de collectie: het heeft een draaiende app met Mailpit nodig.

## Nieuwe afhankelijkheden (goedgekeurd op 16 november 2026)

- **Hurl 8.1.0** (`brew install hurl`), een lokale tool zoals Newman via `npx` was, niet in `composer.json` of CI.
  Minimaal 7.1.0 voor `isList` en `isObject`; `docs/testing.md` noemt die grens. 8.1.0 is ouder dan 14 dagen, dus
  binnen de cooldown uit ADR 0004.

Postman, de Postman-app en Newman vervallen.

## Taken

### T1. Hurl installeren en twee onzekerheden nagaan

- `brew install hurl`, versie noteren.
- Nagaan: een ontbrekende variabele laat het bestand falen; Hurl volgt de proxy uit `https_proxy` of alleen uit
  `--proxy`; `[Cookies] bp_session: …` vervangt de opgeslagen cookie of stuurt hem dubbel mee; `-c` bewaart de
  cookies van een run met `--to-entry` en `-b` stuurt ze mee met een los verzoek via stdin. De uitkomst bepaalt
  Beslissing 4 en 6 en de commando's in `docs/testing.md`.
- Uitgevoerd op 17 november 2026, met deze uitkomsten:
  - Hurl 8.1.0 via Homebrew;
  - een ontbrekende variabele laat het bestand falen (`error: Undefined variable`): Beslissing 4 blijft;
  - de proxy werkt uit `https_proxy`, uit `HTTPS_PROXY` en met `--proxy`; zonder proxy is `.wip` onbereikbaar;
  - `[Cookies] bp_session: …` vervangt de opgeslagen cookie niet: beide gaan mee en de geldige sessie telt. Beslissing 6
    blijft. Bij de proef zette het verzoek daardoor echt het wachtwoord van de voorbeeldbeheerder in de dev-database;
    dat is teruggezet op het voorbeeldwachtwoord. Ook de stap "nieuw wachtwoord in een sessie die niet recent is" gaat
    daarom uit Hurl: in een recente sessie zou hij het wachtwoord wijzigen. `SetPasswordControllerTest` dekt die 403;
  - `-c` met `--to-entry` bewaart de sessie, `-b` stuurt hem mee met een los verzoek via stdin;
  - Hurl rekent niet met datums (`newDate` heeft fracties van seconden, die `DATE_ATOM` weigert) en heeft geen
    rekenkunde in predicaten. Het begin van de periode komt uit de `Date`-header van het antwoord, het eind uit
    `--variable inAWeek=…` (PHP). **Kleine afwijking van Beslissing 2**: één variabele `inAWeek` voor het eind van de
    periode en de vervaldatum van het token, in plaats van `tokenExpiresAt`;
  - een JSONPath met één uitkomst geeft een enkele waarde, geen lijst, en een JSONPath zonder uitkomst heeft geen
    waarde om te tellen. Controles per element gaan daarom met filters (`$.members[?!@.email]` not exists), en
    `$.members[0].*` count == 5 telt ook sleutels met `null`. **Kleine afwijking van Beslissing 5**: het eerste lid en
    de eerste loper worden wel op een exacte set sleutels gecontroleerd, elk lid en elke loper op elke sleutel; de
    exacte set voor allemaal blijft bij PHPUnit.

### T2. Parser en dekkingstest (test eerst)

- In-memory test voor `HurlRequests`: geeft `METHOD /pad` met de variabelen erin, laat querystring, de
  OpenAPI-beschrijving, andere hosts en een pad uit een variabele weg, leest alle `.hurl`-bestanden in een map.
- `HurlRequests` in `tests/Support/`; `OpenApiCoverageTest` leest `hurl/`, de testnaam wordt
  `it_describes_every_request_of_the_hurl_files`. Rood tot T3 er is.
- Uitgevoerd op 17 november 2026, met deze keuzes:
  - `HurlRequests::fromText()` (in memory, `HurlRequestsTest`) en `fromDirectory()` (`HurlRequestsFolderTest`, groep
    `integration`, met een tijdelijke map); een verzoek is een regel die met de methode begint, dus commentaar en een
    ingesprongen regel in een body tellen niet;
  - de dekkingstest eist ook dat er ten minste één verzoek is, zodat een verplaatste of lege map niet stil slaagt.

### T3. De bestanden schrijven

- Per bestand uit Beslissing 1 dezelfde verzoeken en controles als de collectie, op Vastgesteld 4 en 5 na.
- `hurl/local.variables` met de voorbeeldbeheerder en het voorbeeldwachtwoord.
- Draaien met en zonder `--jobs 1`, en twee keer achter elkaar; de tweede run blijft onder de grens van drie codes.
- Uitgevoerd op 17 november 2026, met deze keuzes:
  - acht bestanden zoals in Beslissing 1; het onbekende merkbeeld staat in `settings.hurl`, zoals de tabel zegt;
  - de code uit Mailpit wordt alleen genomen als de nieuwste mail aan `{{email}}` is gericht;
  - `rounds.hurl` kiest een ronde met lopers (`$.rounds[?@.walkerCount > 0]`), zodat de controle op de lopers iets
    controleert; de sortering van "terug te melden" valt weg, omdat de voorbeeldbeheerder geen rondes loopt;
  - **een fout gevonden**: `GET /rounds` gaf 500 voor elke periode met een ronde op een ontmoetingspunt, en een
    terugmelding kon haar uitkomst niet laden. Doctrine laadt die pas bij lezen en zet dan het id opnieuw, als een
    nieuw `Uuid`-object met dezelfde waarde, wat een readonly id weigert. Hersteld in een eigen pull request (#29,
    besloten op 17 november 2026), met twee databasetests;
  - "aanmelden voor een ronde die niet bestaat" verwachtte 404, maar de voorbeeldbeheerder heeft de rol loper niet en
    krijgt eerst de 403 van de rechten. In de collectie kwam die stap na de 500 van de rondes en is hij nooit groen
    gezien. `rounds.hurl` controleert de 403 `access_denied`, zoals de 403's voor terugmeldingen en geschiedenis;
  - gedraaid tegen de demo: parallel en met `--jobs 1`, twee runs achter elkaar, 8 van 8 bestanden en 76 verzoeken
    groen. Ervoor stond de teller van inlogcodes van eerdere proefruns op de grens (429); `cache:pool:clear
    cache.login_attempts` zette hem terug, zoals `docs/testing.md` beschrijft.

### T4. Postman weghalen

- `postman/` en `tests/Support/PostmanRequests.php` verwijderen.
- `LoadFixturesCommandTest` (docblock "one Postman environment") en de docblock van `OpenApiCoverageTest` noemen Hurl.
- Uitgevoerd op 17 november 2026: `postman/` en `PostmanRequests` weg; de docblocks noemen de Hurl-variabelen en
  -bestanden.

### T5. Documentatie

- `docs/testing.md`: de sectie "Postman collection" wordt "Hurl", met wat elk bestand controleert, het commando met
  `--variables-file`, `--header`, `--insecure`, proxy en `tokenExpiresAt`, wat zonder Mailpit draait, en wat
  PHPUnit in plaats van Hurl dekt. De uitleg over `--insecure` en de proxy blijft, voor Hurl in plaats van Node.js.
- `docs/testing.md`, alinea "Sending a request by hand": één verzoek via stdin (`-i` voor de headers), een deel van
  een bestand met `--to-entry` en `--verbose`, en een sessie over losse commando's met `-c` en `-b` (als T1 dat
  bevestigt). Een wijzigend verzoek heeft `-H 'Sec-Fetch-Site: same-origin'` nodig; het cookiebestand bevat een
  geldige sessie en staat in `$TMPDIR`, nooit in de repo.
- `docs/architecture.md` (Rule 6, Why): de spec tegen de router en de Hurl-bestanden.
- `docs/plans/phase-3.md`: de taken die "de Postman-collectie" noemen, noemen de Hurl-bestanden.
- `CHANGELOG.md`: de regel onder Unreleased over de Postman-collectie beschrijft de Hurl-bestanden.
  `docs/plans/phase-2.md` blijft zoals het is.
- Uitgevoerd op 17 november 2026, met deze keuzes:
  - `docs/testing.md`: een tabel met wat elk bestand controleert, het commando, variabelen, headers, proxy,
    `--insecure`, de grens van drie codes, de minimale versie, wat PHPUnit dekt, en een alinea over een verzoek met de
    hand. De exacte vorm van de ledenlijst staat in `GetMemberSummariesHandlerTest`, niet in `ListMembersHandlerTest`
    (dat is de lijst van de beheerder), zoals Vastgesteld 9 noemde;
  - de passages in `phase-3.md` die "de Postman-collectie" noemden, noemen de Hurl-bestanden, ook in de blokken
    "Uitgevoerd".

### T6. Verifiëren en opleveren

- `symfony composer check`, `composer test` zonder database, en `hurl --test` tegen de lokale app; elke check die
  niet draaide, melden.
- `git grep -i -E 'postman|newman'` vindt alleen `docs/plans/phase-2.md` en dit plan.
- Uitgevoerd op 17 november 2026:
  - `symfony composer check`: groen (2515 tests met database), op een eigen testdatabase (`TEST_TOKEN=_hurl`), omdat
    tegelijk een andere controle op de gedeelde draaide;
  - `hurl --test`: 8 van 8 bestanden groen, parallel en met `--jobs 1` (zie T3); `hurlfmt --check` op elk bestand;
  - `git grep -i -E 'postman|newman'` vindt alleen `docs/plans/phase-2.md` en dit plan;
  - niet gedraaid: Hurl in CI (open punt).

## Open punten

- Hurl in CI tegen een app onder de Symfony CLI met Mailpit: buiten deze aanvulling.
- Of `account.hurl` ook met een wachtwoord kan, zodat de controle zonder Mailpit volledig is. Dan gaat de login met
  een code naar een eigen bestand.
