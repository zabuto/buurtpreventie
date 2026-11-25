# Fase 4, aanvulling: grenzen tegen misbruik van tokens en AI-clients

Status: goedgekeurd op 25 november 2026.

Bron: `docs/plans/plan.md` (MCP-server, Inloggen en rechten, Privacy en AVG), `docs/plans/phase-4.md`,
`config/packages/rate_limiter.yaml`, `src/Identity/Infrastructure/Mcp/McpRateLimit.php`,
`src/Identity/Infrastructure/Mcp/ScopedReferenceHandler.php`, `src/Identity/Domain/ApiToken.php`, de use cases in
`src/Identity/UseCase/`, `src/Patrol/Infrastructure/Mcp/MonthlyReportPrompt.php`, `tests/Shared/McpMatrixTest.php`
en `tests/Shared/McpContactDetailsTest.php`, op `develop` (`953369d`).

## Doel en klaar-criterium

Een lid met een geldig token kan niet ongemerkt veel gegevens ophalen of in een lus schrijven, een gestolen token
valt op, en een beheerder ziet en trekt tokens in. Wat MCP geeft, blijft zonder contactgegevens, ook bij een tool die
later komt. Het maandoverzicht is herkenbaar een concept.

Rechten veranderen niet: Voters en scopes bepalen wat een token mag, deze aanvulling bepaalt hoe vaak. De enige
inperking is wie MCP gebruikt: per vereniging welke rollen, en daarnaast losse leden.

Klaar als:

- elk verzoek met een token, op `/api/v1` en `/mcp`, telt mee voor de grenzen per token: lezen per minuut en per
  dag, schrijven per uur; een verzoek boven een grens geeft 429 met `Retry-After`, ook via MCP;
- de eerste keer per dag dat een token de daggrens haalt, een auditregel geeft en het lid een e-mail krijgt;
- een lid een e-mail krijgt als er een token op de eigen naam wordt gemaakt;
- een beheerder alle actieve tokens ziet en alle tokens van één lid intrekt, via de API, met Voter en matrixrij;
- een beheerder kiest welke rollen MCP gebruiken, en een lid gebruikt via MCP alleen die rollen;
- een beheerder zet MCP daarnaast aan voor losse leden, die via MCP al hun rollen houden;
- de prompt `monthly_report` om een concept vraagt dat de lezer eerst controleert;
- `McpContactDetailsTest` faalt op een sleutel met contactgegevens of notities in een antwoord, ook zonder waarde in
  de voorbeelddata;
- `docs/` en `CHANGELOG.md` bijgewerkt zijn en `symfony composer check` groen is.

## Vastgesteld in de code

1. **Alleen `/mcp` heeft een grens per token**: `McpRateLimit` (60 verzoeken per minuut, `mcp_per_token`) draait
   alleen als `McpSwitch::isMcp()` het pad herkent. Een verzoek met hetzelfde token op `/api/v1` heeft geen grens.
   Wie via MCP tegen de grens loopt, kan met hetzelfde token via REST verder.
2. **De MCP-grens telt elk verzoek**, ook `initialize` en `tools/list`, en maakt geen verschil tussen lezen en
   schrijven. Welke tool een verzoek aanroept, weet pas `ScopedReferenceHandler`, die per tool de scope controleert.
3. **REST kent het onderscheid al per HTTP-methode**: `TokenScopeGuard` rekent `GET` tot `read`, de rest tot `write`.
4. **Er is geen grens per dag** en geen spoor van veel lezen: lezen komt niet in het auditlog, en `ApiToken::markUsed`
   bewaart alleen het laatste gebruik (hooguit eens per 60 seconden).
5. **Een token verloopt binnen 365 dagen** (`ApiToken::MAXIMUM_LIFETIME_IN_DAYS`); `expiresAt` is verplicht in
   `CreateApiToken`, dus een standaardtermijn is een keuze van het scherm, niet van de API.
6. **Het lid krijgt geen bericht als er een token wordt gemaakt.** De e-mails in `templates/mail/` gaan over inloggen,
   uitnodigen en rondes. Versturen gaat via `Shared\Domain\Notifier`.
7. **`RevokeMemberApiTokens` heeft een handler maar geen controller**: hij draait bij het deactiveren of wissen van
   een lid. Er is geen overzicht van alle tokens voor een beheerder; `ListApiTokens` geeft alleen de eigen tokens.
8. **`monthly_report` vraagt "counts only"** en sluit namen en getypte tekst uit, maar zegt niet dat het resultaat een
   concept is dat iemand controleert voordat het gebruikt wordt.
9. **`McpContactDetailsTest` zoekt waarden**: vaste telefoonnummers, een straat en `@example.org` in elk antwoord. Een
   nieuw veld met een contactgegeven dat de voorbeelddata van de test niet vullen, valt er niet in op.
   `McpMatrixTest::it_serves_exactly_the_tools_of_the_plan` houdt de lijsten van tools compleet, dus een nieuwe tool
   komt wel in de test.
10. **MCP staat per vereniging aan of uit** (`integrations.mcp`, API `mcpAccess`, standaard uit); `McpSwitch` geeft
    404 als het uit staat. Instellingen zijn tekst per naam (`SettingName`, `SettingValue::tidy`); een lijst als
    waarde bestaat nog niet.
11. **De rollen van een tokenverzoek komen uit `ApiTokenAuthenticator`**: die bouwt `MemberUser` met de rollen van het
    lid en zet op dezelfde plek het kanaal (`mcp` of `api`). Voters lezen de rollen van die gebruiker. Een beheerder
    krijgt via MCP alleen de tools voor elk lid (`McpMatrixTest`).

## Voorgestelde beslissingen

1. **Eén luisteraar voor alle tokenverzoeken**, `TokenRateLimit`, in plaats van `McpRateLimit`. Hij draait na de
   firewall op `/api/v1` en `/mcp`, alleen bij een token (een browsersessie telt niet mee), met de id van het token
   als sleutel, nooit het geheim. Grenzen in `rate_limiter.yaml`, niet in de instellingen:

   | Grens | Waarde | Telt |
   | --- | --- | --- |
   | `token_reads_per_minute` | 60 per minuut | elk leesverzoek |
   | `token_reads_per_day` | 2000 per dag | elk leesverzoek |
   | `token_writes_per_hour` | 20 per uur | elke schrijfactie |

   Een vraag aan een AI-client kost meestal 2 tot 10 verzoeken; 2000 per dag laat honderden vragen toe en houdt een
   jaar rondes ophalen in stukjes van een maand toch zichtbaar. 20 schrijfacties per uur is meer dan een lid met de
   hand doet en stopt een client in een lus binnen een uur. Alternatief: alleen de MCP-grens houden; dan blijft REST
   de omweg (vastgesteld 1).
2. **Lezen of schrijven:** bij REST de methode (zoals `TokenScopeGuard`); bij MCP telt elk verzoek als lezen, en een
   aanroep van een tool met scope `write` telt in `ScopedReferenceHandler` ook als schrijfactie. Zo telt een
   schrijfactie via MCP voor beide grenzen, net als een REST-verzoek dat eerst leest en dan schrijft. Boven een
   leesgrens: 429 met `Retry-After` op beide paden, zoals `McpRateLimit` nu op `/mcp` doet. Boven de schrijfgrens via
   MCP: een toolfout met het aantal seconden tot de volgende poging, want `ScopedReferenceHandler` draait binnen het
   antwoord van de server en kan geen 429 meer geven.
3. **De daggrens laat een spoor na**, alleen de eerste keer per token per dag: een auditregel `api_token.limit_reached`
   (kanaal van het verzoek, naam van het token, geen inhoud) en een e-mail aan het lid: welk token, wanneer, en
   "Was jij dit niet? Trek het token in en meld het de coördinator." De auditregel is een gebeurtenis van het systeem,
   geen schrijfactie van een lid, dus zonder Voter; de matrixtest krijgt geen rij. Lezen zelf komt niet in het
   auditlog: dat zou van elk lid een leesgeschiedenis maken.
4. **E-mail bij een nieuw token**, aan het lid zelf, na het opslaan: naam, scopes en vervaldatum, nooit het geheim,
   met dezelfde zin als in beslissing 3. Teksten in `nl` en `en`, B1, volgens `docs/writing.md`.
5. **Overzicht en intrekken voor de beheerder:**
   - `GET /api/v1/admin/api-tokens`: elk actief token met lid (weergavenaam), naam, scopes, aangemaakt, laatst
     gebruikt en vervaldatum; geen hash. Voter `api_token.list_all`, alleen `admin`.
   - `POST /api/v1/admin/members/{id}/api-tokens/revoke`: de controller bij `RevokeMemberApiTokens`, met de bestaande
     Voter `api_token.revoke_all` en auditregel.
   - Niet via MCP: beheer blijft buiten MCP (plan, MCP-server). Het scherm hoort bij fase 5b.
6. **Standaard 90 dagen in het scherm Koppelingen**, met 365 als maximum zoals nu. Dat is een keuze van de frontend
   (vastgesteld 5) en staat in `docs/plans/phase-5.md` (beslissing 39, T11); de API verandert niet.
7. **`monthly_report` vraagt om een concept:** bovenaan een regel dat het een concept is dat de lezer controleert
   voordat het gebruikt wordt, de periode en de tools waar de getallen uit komen, en geen getal dat geen tool gaf. Het
   model rekent niet zelf bij; ontbreekt iets, dan zegt het dat.
8. **`McpContactDetailsTest` controleert ook sleutels:** elk antwoord dat JSON is, wordt recursief doorlopen; de test
   faalt op `phone`, `email`, `address` of `name` (de volledige naam), op `note` met een waarde en op `comments` die
   niet leeg is. De controle op waarden blijft. Geen Opengrep-regel: een `…Output` mag `phone` of `note` wel hebben
   voor REST (vastgesteld in fase 4), dus alleen het antwoord van de tool zegt iets.

9. **MCP per rol** (besloten op 25 november 2026): een nieuwe instelling `integrations.mcp_roles`, in de API
   `mcpRoles`, een lijst met `walker`, `coordinator`, `analyst` en `admin`. Standaard alle vier, zodat aanzetten werkt
   zoals nu. Een vereniging kan zo bijvoorbeeld alleen coördinatoren en analisten toelaten zolang lopers nog geen
   OAuth hebben (U10), of de beheerder uitsluiten.
   - **Werking:** bij een verzoek op `/mcp` houdt `ApiTokenAuthenticator` alleen de rollen van het lid over die in de
     lijst staan; de Voters zien via MCP dus alleen die rollen. Een loper die ook analist is, krijgt bij alleen
     `analyst` de statistieken maar kan zich via MCP niet aanmelden. REST en de app houden alle rollen.
   - **Geen enkele toegestane rol:** 403 met `mcp_not_for_role` op elk MCP-verzoek, vóór de grenzen van beslissing 1,
     zodat het niet meetelt. Geen 404: MCP staat wel aan, en het lid moet weten waarom het niet werkt.
   - **Opslag:** de lijst als tekst met komma's in de bestaande tabel, gesorteerd en zonder dubbele waarden
     (`SettingValue::tidy`). Geen migratie: een nieuwe instelling is een nieuwe case (`SettingName`). Een lege lijst
     betekent: geen rol, alleen de gekozen leden (beslissing 10).
   - **Audit en rechten:** wijzigen gaat via `PATCH /admin/settings` met de bestaande Voter en auditregel van
     instellingen, met oude en nieuwe waarde. Het scherm hoort bij fase 5b, bij de schakelaar voor MCP.
   - **Alternatief:** toegang als het lid minstens één toegestane rol heeft, met alle eigen rollen. Eenvoudiger, maar dan
     geeft "alleen analisten" een loper die ook analist is toch de schrijftools.

10. **MCP per lid** (besloten op 25 november 2026): naast `mcpRoles` zet de beheerder MCP aan voor losse leden, zoals
    een paar lopers die er handig mee zijn terwijl de andere lopers MCP niet gebruiken. Geen nieuwe rol: rollen geven
    rechten via de Voters en tellen op, en een rol die alleen een kanaal opent, zou elke Voter en de rollenmatrix
    moeten negeren.
    - **Model:** een veld `mcpAllowed` op `Member` (standaard uit), in de API op `PATCH /admin/members/{id}` en in
      `GET /admin/members` en `GET /admin/members/{id}`, zoals `certified`. Wissen zet het uit. Een kolom erbij met
      standaard `0`: de migratie is terug te draaien.
    - **Werking:** `MemberIdentity` krijgt `mcpAllowed`. Bij een verzoek op `/mcp` houdt een lid met `mcpAllowed` al de
      eigen rollen; een lid zonder houdt de rollen uit `mcpRoles`, zoals in beslissing 9. Zonder `mcpAllowed` en zonder
      toegestane rol: 403 `mcp_not_for_role`, zoals nu.
    - **Audit en rechten:** de bestaande Voter `member.change`, auditregel `member.changed` met oude en nieuwe waarde en
      matrixrij van `PATCH /admin/members/{id}`; geen nieuwe route.
    - **Gevolg voor `mcpRoles`:** een lege lijst mag (besloten op 25 november 2026): geen rol, alleen de leden met
      `mcpAllowed`. `mcpAccess` uit sluit MCP voor iedereen. Het scherm hoort bij fase 5b, bij de schakelaar voor MCP.
    - **Terug naar toegang per rol** (besloten op 25 november 2026): `POST /api/v1/admin/members/mcp-allowed/reset`
      zet `mcpAllowed` in één keer uit bij elk lid dat het aan had; daarna geldt alleen `mcpRoles`. Use case
      `ResetMcpAllowed` in Membership, Voter `member.reset_mcp_allowed` (beheerder), per lid een auditregel
      `member.changed` met oude en nieuwe waarde, zodat het lid het bij de eigen activiteit ziet, en een matrixrij. Het
      antwoord geeft het aantal leden. `mcpAccess` uit blijft de noodknop: die laat de keuzes staan.
    - **Alternatief:** een lid met `mcpAllowed` alleen de rollen uit `mcpRoles` plus `walker` geven. Ingewikkelder uit
      te leggen, en een lid dat de beheerder zelf kiest, krijgt geen rechten die het lid buiten MCP niet ook al heeft.

## Nieuwe afhankelijkheden

Geen. `symfony/rate-limiter` en de mailer zijn er al.

## Taken

### T1. Branch en taakplan

Branch `feature/misuse-guards` vanaf `develop`; dit plan. Wacht op akkoord.

### T2. Grenzen per token (test eerst)

Functionele tests: een token op `/api/v1` krijgt 429 na de minuutgrens; lezen via MCP en via REST telt voor dezelfde
grens; een schrijfactie telt voor de uurgrens, via REST en via een MCP-tool; een browsersessie telt niet mee;
`Retry-After` staat er. Daarna `TokenRateLimit`, de telling in `ScopedReferenceHandler` en `rate_limiter.yaml`;
`McpRateLimit` verdwijnt.

### T3. Spoor van de daggrens (test eerst)

Test dat de eerste 429 van de dag één auditregel en één e-mail geeft, en een tweede 429 op dezelfde dag geen van
beide. Daarna de regel, de template in `templates/mail/account/` en de teksten.

### T4. E-mail bij een nieuw token (test eerst)

Handlertest met een in-memory `Notifier`: na `CreateApiToken` één bericht aan het lid, zonder geheim. Daarna template
en teksten.

### T5. Overzicht en intrekken voor de beheerder (test eerst)

Rijen in `HttpMatrixTest` voor beide routes, handlertest voor de lijst (geen hash, alleen actieve tokens), OpenAPI
en Hurl. Daarna use case `ListAllApiTokens` (`symfony console app:make:use-case Identity ListAllApiTokens`), de Voter
en de controller bij `RevokeMemberApiTokens`.

### T6. Maandoverzicht als concept (test eerst)

`ResourceAndPromptsTest` eist de zin over het concept, de periode en de bron. Daarna `MonthlyReportPrompt`.

### T7. Sleutels in `McpContactDetailsTest` (test eerst)

Eerst met de hand nagaan dat de test faalt als `get_round` `phone` teruggeeft; daarna de sleutelcontrole zoals in
beslissing 8.

### T8. MCP per rol (test eerst)

Domeintest voor `SettingValue::tidy` (sorteren, dubbele waarden, onbekende rol, lege lijst), functionele tests: alleen
`analyst` toegestaan geeft een loper-analist `round_statistics` maar 403 op `sign_up_for_round`; een lid zonder
toegestane rol krijgt 403 `mcp_not_for_role` en telt niet voor de grenzen; REST met hetzelfde token houdt alle rollen.
Daarna `SettingName`, `SettingValue`, `ChangeSettings`, `GetSettings`, OpenAPI, Hurl, de rollen in
`ApiTokenAuthenticator` en `StdioAuthentication`, en de voorbeelddata waar instellingen staan.

### T8b. MCP per lid (test eerst)

Domeintest voor `Member` (aan, uit, wissen zet het uit) en voor een lege `mcpRoles`, handlertest voor `ChangeMember`
(auditregel met oude en nieuwe waarde), functionele tests: met een lege `mcpRoles` komt alleen een lid met `mcpAllowed`
binnen; met alleen `analyst` toegestaan houdt een loper met `mcpAllowed` via MCP
`sign_up_for_round`, en een loper zonder krijgt 403 `mcp_not_for_role`; `GET /admin/members/{id}` toont het veld.
Daarna het veld en de migratie, `ChangeMember`, `GetMember`, `ListMembers`, `MemberIdentity` en de rollen in
`ApiTokenAuthenticator`, OpenAPI, Hurl, de voorbeelddata (het lid met een API-token in `demo` krijgt `mcpAllowed`) en
de woordenlijst als er een Nederlandse term bij komt.

### T8c. Terug naar toegang per rol (test eerst)

Handlertest: alleen leden met `mcpAllowed` veranderen, elk met een auditregel, en het aantal klopt; zonder gekozen leden
geen auditregel. Rij in `HttpMatrixTest`, OpenAPI. Daarna de use case
(`symfony console app:make:use-case Membership ResetMcpAllowed`), de Voter en de controller.

### T9. Documentatie

Teksten voor leden noemen geen instelling, endpoint of andere details waar de lezer geen toegang toe heeft: "Vraag het
de beheerder." is genoeg. Die regel komt in `docs/writing.md`. `docs/user-guide/mcp.md` en
`plugins/buurtpreventie/README.md` noemen `mcpAccess` en het endpoint van de instellingen; die horen in
`docs/configuration.md`.

`docs/user-guide/mcp.md` (de drie grenzen, de e-mails, MCP per rol en per lid), `docs/configuration.md` (`mcpRoles`), de
handleiding voor beheerders waar tokens staan, ADR 0008
(grenzen gelden voor elk token, niet alleen MCP), `docs/plans/plan.md` (MCP-server, Beveiliging: rate limit per
token op API en MCP), `CHANGELOG.md`.

### T10. Verifiëren en opleveren

`symfony composer check`, de taalcontrole op nieuwe teksten, en met de hand tegen de demo: één token tot de minuutgrens
via MCP, daarna REST met hetzelfde token (ook 429). Pull request naar `develop` na akkoord, squash merge.

## Afspraken

1. Claude commit op `feature/misuse-guards` in kleine commits en pusht de branch. De pull request gaat pas open na
   akkoord; de merge volgt als squash zodra CI op de laatste commit groen is.
2. `.env.local`, `.env.*.local` en `shared/.env.local` nooit lezen of committen.
3. PHPUnit, `symfony console`, Mago, Rector, PHPStan, Composer, `git push` en `gh` draaien buiten de sandbox; logs in
   de scratchpad.
4. Database, server en Mailpit start de ontwikkelaar. Draait er één niet, dan meldt Claude welke en draait de tests die
   hem nodig hebben niet. Voorbeelddata in de dev-database alleen na vragen.

## Open punten

- **Grenzen per vereniging instelbaar**, als een vereniging met veel analisten tegen de daggrens loopt.
- **Een melding aan de beheerder** bij een token dat vaak de daggrens haalt; tot dan staat het in het auditlog.
- **Het beheerscherm voor `mcpRoles` en `mcpAllowed`** bouwt fase 5b, bij de schakelaar voor MCP.
