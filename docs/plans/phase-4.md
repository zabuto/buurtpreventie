# Fase 4: taakplan

Status: goedgekeurd op 17 november 2026.

Bron: `docs/plans/plan.md` (API en MCP in Symfony, MCP-server, Auditlog, Fase 4, Uitbreiding U10, Risico's en
review-focus), `docs/plans/phase-3.md` (beslissing 23 en Open punten), `docs/architecture.md` (regels 2 tot en met 4),
ADR 0004 en 0006, `AGENTS.md`, de code op `develop` (`1504b43`), en de broncode van `symfony/mcp-bundle` v0.15.0 en
`mcp/sdk` v0.9.0 in een proefinstallatie buiten de repo.

## Doel en klaar-criterium

Een lid gebruikt de app vanuit een AI-client met een persoonlijk API-token: rondes lezen, een loopmaatje zoeken,
aan- en afmelden, terugmelden. Elke tool roept dezelfde handler en Voter aan als de REST-API, dus MCP staat precies
toe wat de API toestaat. MCP staat per vereniging standaard uit.

Klaar als:

- de MCP Inspector met een token alle tools, de resource en beide prompts ziet;
- Claude Code met een token `staffing_gaps` en `sign_up_for_round` gebruikt, met de hand tegen de demo;
- elke tool met de SDK-client getest is: het antwoord, geweigerd zonder scope, geweigerd zonder rol, en een
  pariteitstest tegen de REST-controller;
- geen tool, resource of prompt een telefoonnummer, adres of e-mailadres geeft, ook niet bij toestemming
  (review-focus 3, als test);
- een schrijfactie via MCP een auditregel met kanaal `mcp` geeft;
- `/mcp` met `integrations.mcp` uit 404 geeft, een te snelle client 429, en een vreemde `Host` geweigerd wordt;
- `symfony composer check` groen is, `composer test` zonder database ook, en de pull request naar `develop` groen en
  gemergd is.

## Vastgesteld in de code

1. **Elke `…Tool`-klasse is nog een gewone klasse**: alleen `CheckHealthTool` bestaat, zonder MCP-binding
   (`docs/architecture.md`: "The tool gets its MCP binding in phase 4"). PHPat regel 3 staat in een tool alleen klassen
   uit de eigen map en de namespaces `Mcp` en `Symfony\AI\McpBundle` toe; `ModuleStructureTest` kent `Tool` al als
   bestandsnaam.
2. **Invoer wordt alleen in de REST-laag gevalideerd**: `#[MapRequestPayload]` en `#[MapQueryString]` draaien de
   Validator; geen handler en niets in `src/` roept de Validator zelf aan. Een tool die de invoer zelf bouwt, slaat de
   `Assert`-regels dus over.
3. **Scopes worden per HTTP-methode afgedwongen** (`TokenScopeGuard`: `GET` is `read`, de rest `write`, `/api/v1/admin/`
   is `admin`). Elk MCP-verzoek is een `POST`, dus deze regel zegt niets over een tool.
4. **De tokenfirewall dekt alleen `/api/`** (`BearerRequestMatcher`), en `ApiTokenAuthenticator` zet altijd kanaal
   `api`. `Channel::Mcp` bestaat al.
5. **`GetRound` geeft `phone` en `name` van lopers** (telefoon met toestemming aan lopers van dezelfde ronde, volledige
   naam aan coördinator en beheerder). Geen andere handler die een tool aanroept, geeft contactgegevens.
6. **`FindStaffingGaps` heeft geen periode**: hij geeft alle komende rondes die een loopmaatje zoeken. Het plan noemt
   "de komende N dagen".
7. **Twee tools hebben geen handler**: `round_statistics` (zoals bekend) en ook `report_summary`. `ListRoundReports`
   geeft terugmeldingen met notitie en auteur, geen aantallen; tellen in de tool is logica in de adapter.
8. **`report.list` geldt voor analist én coördinator** (`ReportReadingPolicy::mayListReports`); het plan noemt voor
   `report_summary` alleen de analist.
9. **De laatste loper die zich afmeldt, laat de ronde vervallen** (plan, Regels): `withdraw_from_round` kan dus iets
   weghalen, terwijl het plan `destructiveHint: false` op alle schrijftools zet.
10. **`integrations.api_access` staat in de catalogus** (`SettingName::ApiAccess`), gelezen door
    `GetApiTokenAccess`; `integrations.mcp` is een open punt uit fase 3.
11. **`symfony/rate-limiter` is al runtime** (alleen voor inloggen), en `php-http/discovery` staat al in
    `config.allow-plugins` (`true`), zonder dat het pakket geïnstalleerd is.

### Wat de bundle en de SDK doen (v0.15.0 en v0.9.0)

12. **Streamable HTTP via `McpController`** op een route uit de bundle (standaard `/mcp/<naam>`, instelbaar met
    `http.path`), en `mcp:server` over STDIO met `transports.stdio: true`.
13. **Twee protocoltijdperken.** `2026-07-28` is sessieloos (`StatelessProtocol`). Het handshake-tijdperk werkt alleen
    met een sessiestore: de bundle kent `file`, `memory`, `cache` en `framework`, geen "geen". Een sessieloos
    handshake-tijdperk, zoals het plan zegt, bestaat in deze versie niet.
14. **DNS-rebinding**: `http.allowed_hosts` (zonder poort) en `http.allowed_origins`; zonder instelling alleen
    `localhost`. Een ontbrekende `Host` wordt geweigerd, een request zonder `Origin` (zoals Claude Code) gaat door.
15. **PSR-7 is nodig**: de controller gebruikt `Http\Discovery\Psr17Factory`, die een implementatie zoekt. Geen van de
    afhankelijkheden levert er een.
16. **De SDK stuurt events door Symfony's dispatcher** (`Mcp\Event\RequestEvent` en verwante), ook in het sessieloze
    tijdperk.
17. **Een tool geeft `structuredContent`**; heeft hij een `outputSchema`, dan controleert de SDK het resultaat ertegen.

## Voorgestelde beslissingen

### Transport en toegang

1. **`/mcp` als pad**, buiten `/api/v1`. Het plan noemt `/mcp`; de frontend praat nooit met MCP, dus regel "alleen
   `/api/v1`" geldt hier niet. `BearerRequestMatcher` dekt ook `/mcp`, `access_control` eist `IS_AUTHENTICATED`.
2. **Alleen met een token.** Een verzoek op `/mcp` zonder `Authorization`-header krijgt 401 met
   `WWW-Authenticate: Bearer`, ook met een sessiecookie: een browsersessie op `/mcp` zou een andere site via
   DNS-rebinding of CSRF laten schrijven. Geen Protected Resource Metadata (RFC 9728): die hoort bij OAuth (U10).
3. **Beide tijdperken, het handshake-tijdperk met de `file`-store.** Afwijking van het plan (vastgesteld 13):
   `2026-07-28` sessieloos, het handshake-tijdperk met sessies als bestanden in `var/cache/<env>/mcp-sessions/`, een
   uur geldig. Een sessie draagt alleen het onderhandelde protocol, niet wie er werkt: elk verzoek authenticeert met
   zijn eigen token. Een update die de cache leegt, laat de client opnieuw beginnen. Alternatief: alleen
   `2026-07-28`, zonder sessies; dan werkt een client die nog geen `2026-07-28` spreekt niet, en of de Inspector en
   Claude Code dat al doen, blijkt pas in T12.
4. **JSON-antwoorden, geen SSE**, geen `subscriptions/listen` (`subscriptions.bus: none`): shared hosting houdt geen
   verbindingen open, en geen tool stuurt meldingen.
5. **STDIO met een token uit `MCP_TOKEN`.** `symfony console mcp:server` leest een API-token uit de omgeving
   (Claude Code geeft het mee via `env` in zijn MCP-configuratie) en authenticeert het met dezelfde regels als HTTP:
   schakelaars, verlopen, ingetrokken, inactief lid, scopes. Kanaal `mcp`. Zonder geldig token start hij niet
   (exitcode 1, reden in de foutmelding, zonder het token). Zo is er geen tweede manier om als een lid te werken.
   Alternatief: `--member=<id>` zonder token; dan omzeilt wie de console heeft scopes en schakelaars.

### MCP aan en uit per vereniging

6. **`integrations.mcp` (`mcpAccess`), standaard uit**, in de catalogus van fase 3, te zetten met
   `PATCH /admin/settings` door de beheerder, met een auditregel `setting.changed`. Use case `GetMcpAccess` in
   Settings, zonder Voter en zonder controller, net als `GetApiTokenAccess`.
7. **Uit betekent: `/mcp` bestaat niet.** Een listener vóór de firewall geeft op elk verzoek naar `/mcp` 404
   (problem+json `not_found`, zoals een onbekende route), welke methode of header ook: geen token opzoeken, geen
   `markUsed()`, geen telling in de rate limit, niets in het log behalve de gewone toegangsregel. `mcp:server` stopt
   met exitcode 1 en "MCP staat uit voor deze vereniging". Bestaande tokens blijven op REST werken.
   Uitzetten werkt vanaf het eerstvolgende verzoek, ook midden in een handshake-sessie.
8. **API-tokens uit zet MCP ook uit**, want elk token wordt geweigerd (401, fase 3 beslissing 23). Aan met tokens uit
   is toegestaan en geeft 401; de 404 of 401 wijst de beheerder zo de schakelaar die uit staat. De beheerhandleiding
   (fase 6b) en `docs/configuration.md` zeggen het.

### Tools

9. **Elke tool is een `…Tool`-klasse in de map van zijn use case** met `#[McpTool]`, en doet drie dingen: argumenten
   naar de invoer, invoer valideren, handler aanroepen. De uitvoer gaat ongewijzigd terug, behalve bij `get_round`
   (beslissing 13).

   | Tool | Handler (bestaand, tenzij nieuw) | Argumenten | Scope | Rol (Voter-attribuut) |
   | --- | --- | --- | --- | --- |
   | `list_rounds` | `ListRounds` | `from`, `to`, `status?` | `read` | lid (`round.list`) |
   | `get_round` | `GetRound` | `roundId` | `read` | lid (`round.view`) |
   | `my_rounds` | `ListMyRounds` | `view?` (`upcoming`, `to_report`, `all`), `from?`, `to?`, `order?`, `limit?` | `read` | lid (eigen rondes) |
   | `staffing_gaps` | `FindStaffingGaps`, met nieuw `days?` (beslissing 12) | `days?` | `read` | lid (`round.list`) |
   | `sign_up_for_round` | `SignUpForRound` | `roundId` | `write` | loper (`participation.sign_up`) |
   | `withdraw_from_round` | `WithdrawFromRound` | `roundId` | `write` | loper (`participation.withdraw`) |
   | `create_round` | `CreateRound` | `startsAt`, `meetingPointId?`, `startNote?`, `comment?` | `write` | loper (`round.create`) |
   | `report_round_result` | `ReportRoundResult` | `roundId`, `outcomeId`, `note?` | `write` | loper van die ronde, coördinator (`report.submit`) |
   | `add_round_comment` | `AddRoundComment` | `roundId`, `text` | `write` | loper van die ronde, coördinator (`comment.add`) |
   | `report_summary` | **nieuw:** `SummariseRoundReports` (beslissing 15) | `from`, `to` | `read` | analist, coördinator (`report.list`) |
   | `list_meeting_points` | `ListMeetingPoints` | geen (`withArchived` vast `false`) | `read` | lid (`catalogue.list`) |
   | `list_outcomes` | `ListOutcomes` | geen (`withArchived` vast `false`) | `read` | lid (`catalogue.list`) |
   | `round_statistics` | **nieuw:** `RoundStatistics` (beslissing 14) | `from`, `to`, `groupBy` (`month`, `weekday`, `daypart`) | `read` | analist, coördinator (nieuw: `round.statistics`) |

   Namens een ander lid (`memberId`, `firstWalkerId`) zit in geen enkele tool: dat doet de coördinator in de app,
   waar hij ziet voor wie (plan, Bewust niet in v1).
10. **Validatie met dezelfde `Assert`-regels als REST** (vastgesteld 2). Een kleine klasse
    `Shared\Infrastructure\Mcp\ToolInput` valideert de invoer met de Validator en gooit bij een fout een
    `InvalidToolInput` met de veldfouten. Regel 3 krijgt die ene klasse erbij als toegestane afhankelijkheid van een
    tool, met reden in `docs/architecture.md`. Alternatief: de regels als JSON Schema op de tool (`#[Schema]`); dat is
    een tweede kopie van elke regel die stil kan afwijken.
11. **Scope per tool uit de annotaties.** Een listener op `Mcp\Event\RequestEvent` voor `tools/call`
    (`Identity\Infrastructure\Mcp\McpScopeGuard`) eist `read` voor een tool met `readOnlyHint: true` en `write` voor
    de rest; een tool zonder annotaties is een fout bij het opbouwen van de container. `tools/list`,
    `resources/read` en `prompts/get` eisen `read`. `TokenScopeGuard` laat `/mcp` over aan deze listener.
    Scope én rol moeten kloppen: de Voter in de handler beslist daarna over de rol. `tools/list` toont elke tool aan
    elk token; filteren op rol zou de Voters dupliceren.
12. **`staffing_gaps` krijgt `days`** (vastgesteld 6), ook op REST als `GET /staffing-gaps?days=` (1 tot en met 31,
    weglaten = alle komende rondes, zoals nu), zodat tool en controller dezelfde invoer houden. In de handler, niet
    in de tool.
13. **`get_round` zonder contactgegevens** (vastgesteld 5): de tool geeft van elke loper alleen `id` en
    `displayName`; `phone` en `name` gaan eruit, ook bij toestemming (plan, Beveiliging). De pariteitstest vergelijkt
    met de REST-uitvoer zonder die twee velden. Alternatief: een vlag op `GetRound` die de handler zelf laat weglaten;
    dan weet de handler welke ingang hem aanroept (regel 2).
14. **Use case `RoundStatistics`** in Patrol, met `GET /api/v1/stats?from=&to=&groupBy=` (het plan noemt
    `GET /stats?period=`) en de tool `round_statistics`. Per groep (maand, weekdag of dagdeel, in Europe/Amsterdam):
    `planned` (rondes met start in de periode), `walked`, `notWalked` (geen loopmaatje), `cancelled`, `upcoming`
    (nog niet begonnen), `incidents` (terugmeldingen met uitkomst `incident`), en `walkedPercentage` en
    `notWalkedPercentage` over de afgelopen rondes (`walked + notWalked + cancelled`), afgerond op één decimaal, `null`
    zonder afgelopen rondes. Daarnaast `previous`: dezelfde totalen over de even lange periode direct ervoor. Periode
    hooguit 366 dagen. Geen aantallen per loper (plan, Bewust niet te vragen). De status is afgeleid, dus de handler
    telt in PHP over de rondes van de periode (enkele honderden per jaar); geen nieuwe query. Nieuw Voter-attribuut
    `round.statistics` voor analist en coördinator, met rijen in beide matrixtests. Het scherm volgt in U5.
15. **Use case `SummariseRoundReports`** in Patrol (vastgesteld 7), met `GET /api/v1/reports/summary?from=&to=` en de
    tool `report_summary`: per uitkomst naam, soort en aantal, het totaal, en het aantal incidenten per maand. Geen
    notities, geen auteurs. Rechten via `report.list`, dus analist én coördinator (vastgesteld 8; het plan noemt alleen
    de analist). Alternatief: alleen de analist, met een eigen attribuut; dan mag de coördinator de lijst wel en de
    samenvatting niet.
16. **Annotaties.** Leestools: `readOnlyHint: true`. Schrijftools: `readOnlyHint: false`, `openWorldHint: false`, en:

    | Tool | `destructiveHint` | `idempotentHint` |
    | --- | --- | --- |
    | `sign_up_for_round` | `false` | `true` |
    | `withdraw_from_round` | **`true`** | `true` |
    | `create_round` | `false` | `false` |
    | `report_round_result` | `false` | `false` |
    | `add_round_comment` | `false` | `false` |

    Afwijking van het plan voor `withdraw_from_round` (vastgesteld 9): als laatste loper laat hij de ronde vervallen,
    en een client vraagt bij `destructiveHint: true` om bevestiging. De beschrijving van de tool zegt het, en ook dat
    een ronde die daardoor een loopmaatje zoekt, de overgebleven loper een mail geeft.
17. **Door leden getypte tekst blijft een eigen veld** (`note`, `text`, `startNote`, `cancelReason`), zoals de
    handlers ze al geven, nooit in een beschrijving of titel. De `instructions` van de server zeggen dat die velden
    data zijn en geen opdracht, en dat de server geen contactgegevens heeft.
18. **Fouten als toolresultaat met `isError: true`**, met dezelfde `code` en `title` als problem+json op REST
    (`not_found`, `access_denied`, `round_is_full`, `invalid_input` met veldfouten, …), via één vertaling in
    `Shared\Infrastructure\Mcp` die `ProblemResponder` volgt. Een onverwachte fout geeft "internal error" zonder
    details; de details gaan naar het log. Scope ontbreekt: `insufficient_scope`.
19. **Geen `outputSchema` in deze fase**: de uitvoer gaat als `structuredContent` en als JSON-tekst. Een schema per
    tool is een derde beschrijving naast de Output-klasse en OpenAPI. Open punt: genereren uit de OpenAPI-beschrijving.

### Resource en prompts

20. **`buurtpreventie://config`** (`Patrol\Infrastructure\Mcp\ConfigResource`, `application/json`): naam van de
    vereniging, minimum en maximum lopers, de niet gearchiveerde startpunten en uitkomsten. Uit `GetPublicConfig`,
    `ListMeetingPoints` en `ListOutcomes`; het contact uit `GetPublicConfig` gaat er niet in. Scope `read`.
21. **Prompts** in `Patrol\Infrastructure\Mcp`, alleen tekst, ze halen zelf geen data op:
    - `monthly_report(month)` met `month` als `YYYY-MM`: vraagt de client `report_summary` en `round_statistics` voor
      die maand aan te roepen en een kort overzicht voor politie en gemeente te schrijven, zonder namen, notities of
      andere tekst van leden;
    - `plan_week(weekStart?)`, standaard de maandag na vandaag: vraagt `list_rounds` en `staffing_gaps` voor die week
      en een overzicht van open plekken en dagdelen zonder ronde.
    De tekst is Engels en vraagt een antwoord in `app.language`. Een ongeldige maand of datum is een fout van de
    prompt.

### Beveiliging rond de tools

22. **Rate limit per token**: `sliding_window` van 60 verzoeken per minuut op `/mcp`, sleutel het id van het token
    (nooit het geheim), opslag `cache.rate_limiter` (bestanden). Daarboven 429 met `Retry-After`. Elk verzoek telt,
    ook `tools/list`. Getal in `config/packages/rate_limiter.yaml`, niet als instelling.
23. **DNS-rebinding met de eigen hostnaam**: `allowed_hosts` is de host uit `DEFAULT_URI`
    (`%env(key:host:url:DEFAULT_URI)%`), `allowed_origins` leeg. In `dev` daarnaast `http://localhost:6274` (de
    Inspector) als origin. Een test stuurt een vreemde `Host` en een vreemde `Origin`.
24. **Kanaal `mcp` in het auditlog**: `ApiTokenAuthenticator` zet `Channel::Mcp` voor `/mcp` en `Channel::Api` voor
    de rest; STDIO zet het ook. Een test per schrijftool controleert het kanaal.

### Testen

25. **Tests met de SDK-client in PHPUnit**: een kleine PSR-18-client in `tests/Support` die verzoeken door de kernel
    stuurt, zodat firewall, schakelaar, scopes, rate limit en Voters echt meedraaien. Per tool: het antwoord, geweigerd
    zonder scope, geweigerd zonder rol. Beide tijdperken krijgen een rooktest (`tools/list` en één call).
26. **Pariteitstest per tool**: dezelfde opzet en hetzelfde token, één keer via REST en één keer via MCP; de uitvoer
    moet gelijk zijn (bij `get_round` zonder `phone` en `name`). Een test faalt als een `…Tool`-klasse geen
    pariteitstest heeft, of een tool uit de tabel geen klasse.
27. **Geen contactgegevens** (review-focus 3): een opzet met leden die toestemming gaven en telefoon, adres en
    e-mailadres hebben; elke tool, de resource en elke prompt wordt aangeroepen, en geen uitvoer bevat een van die
    waarden.
28. **Matrixtests**: `round.statistics` in `AuthorizationMatrixTest`; `/stats`, `/reports/summary` en `/mcp` (zonder
    token, met token, MCP uit) in `HttpMatrixTest`. Een `McpMatrixTest` zet elke tool tegen elke rol en scope.

## Nieuwe afhankelijkheden (goedgekeurd op 17 november 2026)

Versies en datums van Packagist op 17 november 2026. Cooldown uit ADR 0004: Composer 24 uur, Dependabot 7 dagen voor
patch en minor en 14 voor major.

| Pakket | Waarvoor | Licentie | Soort | Versie (datum, leeftijd) | Buiten de cooldown |
| --- | --- | --- | --- | --- | --- |
| `symfony/mcp-bundle` | MCP in Symfony: route, controller, STDIO-commando, registratie van tools | MIT | runtime | v0.15.0 (22 okt 2026, 26 dagen) | ja |
| `mcp/sdk` | Het protocol: JSON-RPC, beide tijdperken, schema's, validatie | Apache-2.0, oudere delen MIT | runtime | v0.9.0 (18 okt 2026, 30 dagen) | ja |
| `nyholm/psr7` | PSR-7 en PSR-17 voor de controller van de bundle (vastgesteld 15) | MIT | runtime | 1.8.2 (9 sep 2024) | ja |

Indirect, mee met de bundle en de SDK:

| Pakket | Licentie | Versie (datum) | Buiten de cooldown |
| --- | --- | --- | --- |
| `opis/json-schema` | Apache-2.0 | 2.6.2 (8 nov 2026, 9 dagen) | ja, ook voor Dependabot (patch, 7 dagen) |
| `opis/string`, `opis/uri` | Apache-2.0 | 2.1.0 (17 okt 2025), 1.1.0 (22 mei 2021) | ja |
| `php-http/discovery` | MIT | 1.20.0 (2 okt 2024) | ja |
| `symfony/psr-http-message-bridge` | MIT | v7.4.21 (7 okt 2026), 7.4-lijn | ja |
| `psr/http-message`, `psr/http-factory`, `psr/http-client`, `psr/http-server-handler`, `psr/http-server-middleware` | MIT | interfaces, 2016 tot en met 2023 | ja |

- **Minor vast**: `"symfony/mcp-bundle": "0.15.*"` en `"mcp/sdk": "0.9.*"`. Onder 1.0 mag een minor de API breken
  (semver), en beide zijn als experimenteel gemarkeerd; de SDK-changelog van 0.9.0 heeft tientallen BC-breaks. Een
  nieuwe minor komt dus alleen via een eigen pull request; `dependabot-automerge.yml` laat een minor onder 1.0 al
  liggen voor review.
- **Waarom niet de nieuwste**: `mcp/sdk` v0.10.0 (10 nov 2026, 7 dagen) zit op de grens van de Dependabot-cooldown,
  en geen uitgebrachte versie van de bundle ondersteunt hem (v0.15.0 vraagt `^0.9`, alleen `dev-main` vraagt `^0.10`).
  `symfony/mcp-bundle` v1.0.0-BETA1 (zelfde dag als v0.15.0) valt af door `minimum-stability: stable`.
- **`php-http/discovery` is een Composer-plugin** die zelf pakketten kan toevoegen. Voorstel: in `allow-plugins` op
  `false`. De klassen werken zonder de plugin, en met `nyholm/psr7` expliciet in `require` hoeft hij niets te kiezen.
- `SymfonyReleaseLineTest` dekt `symfony/psr-http-message-bridge` op naam; `symfony/mcp-bundle` volgt een eigen
  nummering (0.x) en krijgt in die test een uitzondering met reden.
- De bundle ondersteunt Symfony `^7.3|^8.0`, dus hij staat de overstap naar 8.4 LTS niet in de weg.

## Taken

Elke taak is één of enkele kleine commits; tests komen vóór de code.

### T1. Branch en taakplan

`feature/phase-4` vanaf `develop`; dit plan. Bouwen pas na akkoord.

### T2. Afhankelijkheden en configuratie

- `composer require symfony/mcp-bundle:0.15.* mcp/sdk:0.9.* nyholm/psr7`; `php-http/discovery` in `allow-plugins` op
  `false`; recepten nalopen, receptbestanden ongewijzigd.
- `config/packages/mcp.yaml`: één server `buurtpreventie`, `http.path: /mcp`, `transports.stdio: true`,
  `allowed_hosts` (beslissing 23), sessiestore `file` (beslissing 3), `subscriptions.bus: none`, `registry` op de
  `…Tool`-klassen en `Patrol\Infrastructure\Mcp\`.
- Test eerst: `SymfonyReleaseLineTest` met de uitzondering; `composer deprecations` blijft schoon; de container
  bouwt in `dev`, `test` en `prod`.

- Uitgevoerd op 17 november 2026, met deze keuzes:
  - `symfony/mcp-bundle` v0.15.0, `mcp/sdk` v0.9.0 en `nyholm/psr7` 1.8.2 geïnstalleerd, met de indirecte pakketten
    uit de tabel; `php-http/discovery` staat in `allow-plugins` op `false`. Het recept zette ook
    `config/packages/http_discovery.yaml` neer (PSR-17-aliassen); die blijft, zoals elk recept;
  - `SymfonyReleaseLineTest` kent `symfony/mcp-bundle` als pakket met een eigen nummering;
  - `config/packages/mcp.yaml`: één patroon `App\` voor tools, prompts en resources. De bundle weigert een patroon dat
    geen enkele service raakt, dus de configuratie met tools kwam pas in de commit van de eerste tool (T4).

### T3. MCP-schakelaar (test eerst)

- `integrations.mcp` in `SettingName`, `GetMcpAccess`, de listener van beslissing 7 en de weigering van `mcp:server`.
- Tests: standaard uit; uit geeft 404 op `GET`, `POST` en `DELETE /mcp`, met en zonder token, zonder `markUsed()`;
  aan werkt; uitzetten weigert het eerstvolgende verzoek; een auditregel `setting.changed`.

- Uitgevoerd op 17 november 2026, met deze keuzes:
  - de opgeslagen naam is `integrations.mcp`, het veld `mcpAccess`; `GetMcpAccess` in Settings, zonder Voter;
  - `McpSwitch` (`Identity\Infrastructure\Mcp`, prioriteit 16, vóór de firewall) geeft de 404 als problem+json,
    zodat `/mcp` uit niet anders klinkt dan een onbekende route.

### T4. Token, kanaal en scope op `/mcp` (test eerst)

- `BearerRequestMatcher` en `access_control` voor `/mcp`; kanaal `mcp` (beslissing 24); 401 zonder token, ook met een
  sessiecookie (beslissing 2); `McpScopeGuard` (beslissing 11) en `TokenScopeGuard` zonder `/mcp`.
- De PSR-18-client voor de tests (beslissing 25) en de eerste tool, `staffing_gaps` met `days` (beslissing 12), om de
  keten van begin tot eind te bewijzen; daarna `CheckHealthTool` met binding of weg, als de health-tool geen waarde
  heeft voor een lid (voorstel: weg, `GET /health` blijft).
- Tests: geldig token, verlopen, ingetrokken, inactief lid, tokens uit, `read` zonder `write` op een schrijftool,
  beide tijdperken.

- Uitgevoerd op 17 november 2026, met deze keuzes:
  - **`/mcp` kreeg een eigen firewall** (`mcp`, stateless, alleen `ApiTokenAuthenticator`) in plaats van een
    uitbreiding van `BearerRequestMatcher`: zo valt een verzoek zonder header ook bij de tokenauthenticator (lege 401),
    en telt een sessiecookie nooit. De authenticator zet kanaal `mcp` op `/mcp`;
  - **de scope per tool zit in een reference handler, niet in een listener op `RequestEvent`** (afwijking van
    beslissing 11): een exceptie in een listener van de SDK ontsnapt als 500. `McpServerFactory` bouwt de server van
    de bundle met een eigen reference handler (`config/services.yaml`): `ScopedReferenceHandler` (Identity) eist de
    scope, `ProblemReferenceHandler` (Shared) maakt van elke fout een toolresultaat. `McpElements` geeft de SDK de
    tools, prompts en resources per klasse, omdat de bundle zijn eigen lijst privé houdt;
  - `Problem` haalt de vertaling van een fout uit `ProblemResponder`, zodat REST en MCP dezelfde code, titel en
    veldfouten geven; `ProblemResponder` behandelt `/mcp` als API;
  - `CheckHealthTool` is weg (voorstel uit T4): een AI-client heeft niets aan de gezondheidscheck;
  - tests: `McpSwitchTest`, `McpAccessTest` (drie protocolversies, geen token, alleen een sessiecookie, ingetrokken,
    tokens uit) en `FindStaffingGapsToolTest`; `KernelHttpClient` en `McpTestCase` in `tests/Support`. Elk lid mag
    rondes lezen, dus bij een leestool voor elk lid weigert alleen de scope, niet de rol.

### T5. Invoer en fouten (test eerst)

- `ToolInput` en `InvalidToolInput` (beslissing 10), de foutvertaling (beslissing 18), regel 3 in `ArchitectureRules`
  en `docs/architecture.md`.
- Tests: een ongeldige datum geeft `invalid_input` met het veld; `NotFound`, `AccessDenied`, een regel uit het domein
  en een onverwachte fout krijgen elk hun code; de laatste zonder details.

- Uitgevoerd op 17 november 2026, samen met T4: `ToolInput` gooit dezelfde `UnprocessableEntityHttpException` met
  `ValidationFailedException` als de REST-laag, zodat `Problem` de veldfouten op dezelfde manier leest.

### T6. Leestools (test eerst)

- `list_rounds`, `get_round` (beslissing 13), `my_rounds`, `list_meeting_points`, `list_outcomes`, met annotaties.
- Per tool: antwoord, scope, rol en pariteit (beslissingen 25 en 26).

- Uitgevoerd op 17 november 2026, met deze keuzes:
  - `get_round` bouwt zijn antwoord veld voor veld en geeft van elke loper alleen `id` en `displayName`;
  - `list_meeting_points` en `list_outcomes` hebben geen argumenten en geen `ToolInput`: er is niets te valideren.

### T7. Schrijftools (test eerst)

- `sign_up_for_round`, `withdraw_from_round`, `create_round`, `report_round_result`, `add_round_comment`, met de
  annotaties van beslissing 16.
- Per tool: antwoord, scope, rol, pariteit en een auditregel met kanaal `mcp`; aan- en afmelden twee keer geeft
  hetzelfde resultaat.

- Uitgevoerd op 17 november 2026, met deze keuzes:
  - aan- en afmelden hebben geen output (REST: 204), dus de tool geeft een bevestiging (`roundId` met `signedUp` of
    `withdrawn`);
  - de pariteitstest van een schrijftool doet dezelfde handeling via REST met hetzelfde token en vergelijkt de
    auditactie, met kanaal `mcp` tegen `api`; `RecordingAuditTrail` bewaart daarvoor het kanaal.

### T8. `RoundStatistics` en `SummariseRoundReports` (test eerst)

- Beide use cases met controller en tool (beslissingen 14 en 15), `round.statistics` in Voter en matrixtests, OpenAPI
  en de Hurl-bestanden.
- Tests in memory: groeperen per maand, weekdag en dagdeel rond de tijdwissel (review-focus 2), percentages met en
  zonder afgelopen rondes, de vorige periode, een periode van 367 dagen geweigerd; een samenvatting zonder notities.

- Uitgevoerd op 17 november 2026, met deze keuzes:
  - domein in `Patrol\Domain\Statistics`: `StatisticsPeriod` (hooguit 366 dagen; hele lokale maanden vergelijken met
    evenveel maanden ervoor, zodat oktober met heel september gaat), `StatisticsGrouping` en `RoundTally`;
  - `round.statistics` staat in `ReportVoter`, met dezelfde regel als `report.list` (`ReportReadingPolicy`);
  - `SummariseRoundReports` gebruikt ook `StatisticsPeriod`, dus tot een jaar (de lijst terugmeldingen blijft 93 dagen);
  - het antwoord heeft elke groep van de periode, ook zonder rondes, zodat een gat als nul zichtbaar is.

### T9. Resource en prompts (test eerst)

- `buurtpreventie://config`, `monthly_report` en `plan_week` (beslissingen 20 en 21).
- Tests: inhoud van de resource zonder contact; argumenten van de prompts, ook een ongeldige maand.

- Uitgevoerd op 17 november 2026, met deze keuzes:
  - **`plan_week` vraagt alleen `list_rounds`** (afwijking van beslissing 21): die geeft de status al, en
    `staffing_gaps` kijkt hooguit 31 dagen vooruit, dus een latere week bleef leeg;
  - een ongeldige maand of datum is een `PromptGetException`, die de SDK als fout van het verzoek geeft;
  - de resource gebruikt `GetPublicConfig` van Settings voor naam, taal en bezetting.

### T10. Rate limit, DNS-rebinding en contactgegevens (test eerst)

- Rate limit (beslissing 22) en `allowed_hosts` (beslissing 23).
- Tests: het 61e verzoek in een minuut is 429 met `Retry-After`, per token apart; een vreemde `Host` en `Origin`
  worden geweigerd, geen `Origin` gaat door; de test van beslissing 27 over alle tools, resource en prompts;
  `McpMatrixTest` en de bewaker dat elke tool een pariteitstest heeft.

- Uitgevoerd op 17 november 2026, met deze keuzes:
  - de teller van de rate limit gebruikt het id van het token (`ApiTokenAuthenticator::TOKEN_ID`), na de firewall;
  - `/mcp` krijgt ook de beveiligingsheaders van de API (`ApiSecurityHeaders`);
  - STDIO: `StdioAuthentication` laat `ApiTokenAuthenticator` het token uit `MCP_TOKEN` controleren, dus dezelfde
    regels als HTTP. Het token wordt één keer gecontroleerd, bij het starten;
  - bewakers: `McpToolGuardTest` (elke tool een `readOnlyHint` en een eigen test met pariteit), `McpMatrixTest` (de
    tools van het plan, scope per tool, een beheerder alleen de tools voor elk lid) en `McpContactDetailsTest`. Die
    laatste faalt als `get_round` de lopers ongefilterd doorgeeft (met de hand nagegaan);
  - `app:make:use-case` maakt een tool met `#[McpTool]`, annotaties en `ToolInput`.

### T11. Documentatie

- `docs/adr/0008-mcp-server.md`: transport, beide tijdperken met de `file`-store, alleen tokens, schakelaar, scope
  per tool, STDIO met `MCP_TOKEN`.
- `docs/architecture.md` (MCP, regel 3 met `ToolInput`), `docs/configuration.md` (`integrations.mcp`, `MCP_TOKEN`,
  de host uit `DEFAULT_URI`, rate limit), `docs/user-guide/` (een token maken en Claude Code of de Inspector
  koppelen), `docs/testing.md` (SDK-client, pariteit, contactgegevens), `docs/glossary.md` (tool, resource, prompt),
  `docs/gotchas.md`; `plan.md` (Fase 4 afvinken, afwijkingen bij MCP-server, endpointtabel met `/stats` en
  `/reports/summary`, Fasen en stand); `CHANGELOG.md`; `AGENTS.md` (Commands: `mcp:server`).

- Uitgevoerd op 17 november 2026: ADR 0008, `docs/architecture.md` (sectie MCP server, firewall, scope, regel 3,
  statistieken), `docs/configuration.md` (`mcpAccess`, `MCP_TOKEN`, de host uit `DEFAULT_URI`, de grenzen),
  `docs/testing.md` (MCP-tests), `docs/glossary.md`, `docs/gotchas.md` (sectie MCP), `docs/user-guide/mcp.md`,
  `plan.md`, `CHANGELOG.md` en `AGENTS.md`.

- Aanvulling, besloten op 17 november 2026 na T11: **notities en opmerkingen gaan niet via MCP.** `get_round` geeft
  de uitkomst van de terugmelding met notitie `null` en geen opmerkingen; startnotitie en reden van annulering blijven.
  Leden typen notities en opmerkingen vrij, vaak over een verdachte, en via MCP gaat die tekst naar de aanbieder van
  de AI-client. Filteren op patronen is onbetrouwbaar. `McpContactDetailsTest` zet een telefoonnummer en e-mailadres
  in een notitie en een opmerking, en `GetRoundToolTest` vergelijkt met REST zonder die tekst.

### T12. Verifiëren en opleveren

- `composer test` zonder database, `symfony composer check`; uitvoer in het verslag, met elke check die niet draaide.
- Met de hand tegen de demo (MCP aan, met akkoord voor de dev-database): de MCP Inspector met een token ziet alle
  tools, de resource en de prompts; Claude Code met hetzelfde token over HTTP gebruikt `staffing_gaps` en
  `sign_up_for_round`; de auditregel heeft kanaal `mcp`. Welk tijdperk elke client sprak, komt in het verslag.
- Push en pull request naar `develop` na akkoord; CI groen (alle jobs); merge als squash.

- Met de hand op 17 november 2026, tegen de demo op `https://buurtpreventie.wip` met het token van een loper:
  - **MCP Inspector 2.13** verbond met protocol `2025-11-25` (het handshake-tijdperk, met sessie) en zag 13 tools, de
    resource en beide prompts. De resource geeft geen contactgegevens; `monthly_report` en `plan_week` geven hun
    periode in lokale tijd; `staffing_gaps` met 31 dagen gaf zes rondes; `round_statistics` weigerde de loper met
    `access_denied`. De Inspector waarschuwde voor typen als lijst in de invoerschema's; dat is hersteld, met een
    bewaker in `McpMatrixTest`;
  - **Claude Code** gebruikte `staffing_gaps` en `sign_up_for_round`; de aanmelding staat in het auditlog op kanaal
    `mcp`. Een aanmelding voor een ronde waar de loper al op stond, veranderde niets en schreef geen regel;
  - curl en Node bereiken het lokale domein alleen via de proxy van de Symfony CLI: `docs/getting-started.md` zegt het,
    met de stappen van deze test.

- Opgeleverd op 17 november 2026: pull request #34 naar `develop`, CI groen op alle jobs (Checks, Database op MariaDB
  10.11 en MySQL 8.4, Security scan), gemerged als squash. Onderweg was de Security scan één keer rood op
  `strtolower()` in `StatisticsGrouping` (`byte-lowercase`); `mb_strtolower()` herstelde het. Daarna, op dezelfde
  dag: `signedUp` in `staffing_gaps` en `list_rounds`, uit de open punten hieronder (branch
  `feature/signed-up-in-round-lists`).

## Afspraken

1. Claude commit op `feature/phase-4` in kleine commits en pusht de branch. De pull request gaat pas open na akkoord;
   de merge volgt als squash zodra CI op de laatste commit groen is.
   - Geen `cd`, `pushd`, `-C`, `--git-dir`, `--work-tree` of `GIT_DIR=` in dezelfde aanroep als `git add` of
     `git commit`.
   - Alleen de bestanden van die commit stagen, met letterlijke paden; daarna `git show --stat HEAD`.
2. `.env`, `.env.dev` en `.env.test` alleen lezen met `git show HEAD:<bestand>`; `.env.local`, `.env.*.local` en
   `shared/.env.local` nooit lezen of committen. Het token voor Claude Code en de Inspector maakt de ontwikkelaar.
3. PHPUnit, `symfony console`, Mago, Rector, PHPStan, Composer, `git push` en `gh` draaien buiten de sandbox; logs
   in de scratchpad.
4. Database, server en Mailpit start de ontwikkelaar. Draait er één niet, dan meldt Claude welke en draait de tests
   die hem nodig hebben niet. Voorbeelddata in de dev-database alleen na vragen.
5. Nieuwe afhankelijkheden pas na akkoord op de tabel hierboven.
6. Na elke taak wordt de tabel "Fasen en stand" in `docs/plans/plan.md` bijgewerkt.

## Open punten

Gemeld, niet in deze fase opgelost:

- **`outputSchema` per tool** (beslissing 19), bijvoorbeeld afgeleid uit de OpenAPI-beschrijving.
- **`mcp/sdk` 0.10 en de bundle 1.0** komen met een eigen pull request zodra een uitgebrachte bundle ze ondersteunt.
- **OAuth voor MCP-clients** blijft U10.
- **Het beheerscherm** voor de MCP-schakelaar bouwt fase 5 op `PATCH /admin/settings`.
- **Een hint bij het typen** van een notitie of opmerking ("geen namen of contactgegevens") hoort bij fase 5. Een
  instelling die notities en opmerkingen toch via MCP geeft, standaard uit, volgt als een vereniging erom vraagt.
- **STDIO controleert het token één keer**, bij het starten: een ingetrokken token werkt in een draaiend
  `mcp:server` tot het proces stopt. Opnieuw controleren per verzoek kan, als STDIO meer dan lokaal gebruikt wordt.
- **De server kondigt `resources: {"subscribe": true}` aan** zonder meldingen te versturen (`subscriptions.bus: none`);
  dat komt uit de bundle. Een client die zich abonneert, krijgt niets.

