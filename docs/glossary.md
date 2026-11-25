# Woordenlijst

Nederlandse begrippen en hun naam in code, API en MCP. Code is Engels; een begrip heeft overal
dezelfde naam.

## Modules

| Begrip | Code | Toelichting |
| --- | --- | --- |
| Rondes en terugmelden | `Patrol` | Rondes, aanmelden, loopmaatjes, terugmelden |
| Ledenbeheer | `Membership` | Leden, rollen, toestemming, certificaat |
| Inloggen | `Identity` | Accounts, inlogcodes, uitnodigingen, wachtwoorden, API-tokens |
| Auditlog | `Audit` | Wie deed wat, wanneer en via welk kanaal |
| Instellingen | `Settings` | Huisstijl, contact, teksten, lopers per ronde, herinnering, taal en de schakelaar voor API-tokens per vereniging |
| Onderhoud | `Maintenance` | Gezondheidscheck, herinneringen, updates, installer, legacy-import |
| Voorbeelddata | `SampleData` | Verzonnen leden, rondes en terugmeldingen: `app:fixtures:load demo`, `test` of `edge` |

## Domein

| Begrip | Code | Toelichting |
| --- | --- | --- |
| Ronde, loopronde | `Round` | Een geplande ronde op een tijdstip; `startsAt` in UTC |
| Loper | walker (rol `walker`) | Lid dat rondes loopt |
| Loopmaatje | buddy | Tweede loper bij een ronde; een ronde gaat alleen door met loopmaatje |
| Aanmelding | `Participation` | Een loper bij een ronde; ronde en lid samen zijn uniek |
| Namens | `onBehalfOf` | Aanmelding door de coördinator voor een loper |
| Terugmelding | `RoundReport` | Uitkomst en notitie van een gelopen ronde, één per ronde, door een loper of de coördinator |
| Uitkomst | `Outcome` | Soort terugmelding: `nothing_to_report`, `remarks`, `incident` |
| Startpunt | `MeetingPoint` | Optionele plek om te verzamelen |
| Startnotitie | `startNote` | Vrije tekst bij de start van een ronde, zoals waar je precies verzamelt |
| Coördinaten | `Coordinates` | Breedte- en lengtegraad van een startpunt in WGS84, op zes decimalen |
| Herinnering | `Reminder` | Eén bericht per loper per dag, over alle eigen rondes van die dag |
| Opmerking | `RoundComment` | Opmerking bij een ronde, met auteur |
| Lid | `Member` | Profiel: naam, contactgegevens, rollen |
| Eerste beheerder | `AddFirstAdmin` | Het systeem voegt de eerste beheerder toe, alleen zolang er geen actieve beheerder is |
| Verwijderd lid | `erasedAt` | Gewist lid: geen naam, weergavenaam of contactgegevens; staat in historie en auditlog in beeld als "verwijderd lid" |
| Volledige naam | `name` | Naam van een lid; doorzoekbaar, niet uniek |
| Weergavenaam | `displayName` | Unieke naam in rondes, lijsten en auditlog, zoals "Jan H." |
| Zoeksleutel | `LookupKey` | Naam zonder hoofdletters, accenten, leestekens en dubbele spaties; zoeken en uniek zijn gaan hierop |
| Account | `Account` | Inloggegevens van een lid: wachtwoord en laatste login; het e-mailadres staat op het lid |
| Toestemming contactgegevens | `consentContactShare` | Andere leden zien telefoon en adres alleen hiermee |
| Certificaat | `certified` | Lid heeft een certificaat (in de legacy-app: `credited`) |
| Melding bij open ronde | `notifyOpenRounds`, `ChangeOpenRoundNotifications` | Lid wil bericht over een ronde die een loopmaatje zoekt; alleen het lid zelf zet dit aan of uit |
| Oproep loopmaatje | `BuddyCall`, `NotifyBuddyNeeded` | Eén bericht per ronde die nog een loopmaatje zoekt, 5, 3 of 1 dag vóór de start |
| Coördinator | `coordinator` | Rol: wijzigt rondes, handelt namens lopers |
| Analist | `analyst` | Rol: ziet terugmeldingen en statistieken |
| Beheerder | `admin` | Rol: leden, instellingen, onderhoud |
| Dagdeel | `Daypart` | `morning` (voor 12:00), `afternoon` (tot 18:00), `evening` (vanaf 18:00) |
| Minimumaantal lopers | `StaffingRule`, `minimumWalkers` | Standaard 2, vastgelegd per ronde |
| Maximumaantal lopers | `StaffingRule`, `maximumWalkers` | Standaard 7, vastgelegd per ronde; nooit lager dan het minimum |
| Lopers nodig | `walkersNeeded` | Hoeveel lopers een ronde nog nodig heeft voor het minimum; bij `FindStaffingGaps` |
| Open plekken | `openSpots` | Hoeveel lopers zich nog kunnen aanmelden; 0 als de ronde vol is of begonnen |
| Vol | `openSpots` is 0 | Geen status: een volle ronde is `staffed` |
| Zoekt loopmaatje | `seeking_buddy` | Status: minder lopers dan het minimum |
| Bezet | `staffed` | Status: minimum gehaald |
| Gelopen | `walked` | Status: voorbij en minimum gehaald |
| Niet gelopen | `not_walked` | Status: voorbij zonder loopmaatje |
| Geannuleerd | `cancelled` | Status: begonnen maar niet gelopen, zoals bij een no-show of overmacht |
| Auditregel | `AuditEntry` | Eén regel in het auditlog |
| Kanaal | channel: `web`, `api`, `mcp`, `cron`, `console` | Hoe een actie binnenkwam; `console` voor console-commando's en voorbeelddata |
| Systeem | `ROLE_SYSTEM`, `RunAs::system()` | Uitvoerder zonder lid, voor geplande taken, de eerste beheerder en voorbeelddata; een auditregel zonder lid |
| Instelling | `Setting`, `SettingName` | Waarde die een vereniging zelf instelt; `SettingName` is de lijst van alle instellingen, met standaard en regels |
| Huisstijl | `BrandPalette`, `Branding`, `BrandImageKind` | Naam, kleuren, logo en app-icoon van de vereniging, in de app, het manifest en elke e-mail |
| Contrast | `Color::contrastWith()`, `InsufficientContrast` | Verhouding tussen een kleur en de achtergrond (WCAG): 4,5 : 1 voor tekst, 3 : 1 voor randen en focus |
| Groepslink | `community.group_url`, `GroupLink` | Uitnodiging voor de groep van de vereniging in WhatsApp of Signal; alleen voor ingelogde leden |
| Hashketen | `chain_position`, `chain_hash`, `ChainVerifier` | Elke auditregel draagt een HMAC over zijn vaste velden en de hash van de vorige regel, zodat geknoei in de database opvalt |
| Kop | `AuditChainMark::HEAD` | De laatste regel van de hashketen; wie een regel schrijft, vergrendelt de kop als laatste stap |
| Anker | `AuditChainMark::ANCHOR` | Volgnummer en hash van de laatst opgeruimde regel; daar begint de controle |
| Keten controleren | `VerifyAuditChain`, `app:audit:verify` | Meldt tot welke regel de hashketen klopt, of de eerste regel die niet klopt |
| Inlogcode | `LoginChallenge`, `RequestLoginCode` | Code van zes cijfers per mail, 60 minuten geldig; werkt alleen samen met de aanvraag (`challengeId`) uit de browser die hem aanvroeg |
| Uitnodiging | `Invitation`, `IssueInvitation`, `InviteMember` | Link per mail die een lid één keer inlogt, 48 uur geldig |
| Ingelogd blijven | `remember_me`, cookie `bp_remember` | 90 dagen ingelogd op een toestel; vervalt bij een nieuw wachtwoord |
| Recent ingelogd | `RecentAuthentication` | In de laatste 15 minuten ingelogd of bevestigd; nodig voor gevoelige acties |
| Scope | `TokenScope`: `read`, `write`, `admin` | Wat een API-token mag: lezen, wijzigen, of `/api/v1/admin/`; rol en scope moeten allebei kloppen |
| Geschiedenis van een ronde | `GetRoundHistory` | De auditregels over één ronde, voor de coördinator |
| Terugmeldingen | `ListRoundReports` | De terugmeldingen van een periode met notities, voor analist en coördinator |
| Samenvatting terugmeldingen | `SummariseRoundReports`, `report_summary` | Aantallen terugmeldingen per uitkomst en incidenten per maand, zonder notities of namen |
| Statistieken | `RoundStatistics`, `round_statistics`, `RoundTally` | Hoe de rondes van een periode gingen, per maand, weekdag of dagdeel, naast de periode ervoor; nooit per loper |
| Loopmaatje gezocht | `FindStaffingGaps`, `staffing_gaps` | Komende rondes die nog lopers nodig hebben |
| API-token | `ApiToken` | Persoonlijke sleutel voor scripts en MCP, met scopes `read`, `write` en `admin`; alleen de hash wordt bewaard |
| Tijdtrigger, tick | `app:scheduler:run`, `POST /api/v1/system/tick` | Start taken op een vast moment |
| Gezondheidscheck | `CheckHealth`, `GET /api/v1/health` | Antwoordt `ok` als de app draait |

## Code

| Begrip | Code | Toelichting |
| --- | --- | --- |
| Use case | `UseCase/<Naam>/` | Eén handeling met invoer, handler en adapters, zie [architecture.md](architecture.md) |
| Invoer | `<Naam>` | Contract van de use case |
| Handler | `<Naam>Handler` | Rechten, regels en auditlog |
| Uitvoer | `<Naam>Output` | Wat REST en MCP teruggeven |
| Adapter | `<Naam>Controller`, `<Naam>Tool` | Dunne laag voor REST of MCP |
| MCP-server | `/mcp`, `mcp:server`, instelling `mcpAccess` | Ingang voor AI-clients zoals Claude Code; standaard uit, per vereniging aan te zetten |
| Tool | `<Naam>Tool`, naam in snake_case (`sign_up_for_round`) | Handeling die een AI-client via MCP aanroept, met dezelfde handler als REST |
| Resource | `buurtpreventie://config` | Gegevens die een AI-client via MCP leest |
| Prompt | `monthly_report`, `plan_week` | Kant-en-klare opdracht voor een AI-client, zonder gegevens erin |
