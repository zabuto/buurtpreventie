# Woordenlijst

Nederlandse begrippen en hun naam in code, API en MCP. Code is Engels; een begrip heeft overal
dezelfde naam.

## Modules

| Begrip | Code | Toelichting |
| --- | --- | --- |
| Rondes en terugmelden | `Patrol` | Rondes, aanmelden, loopmaatjes, terugmelden |
| Ledenbeheer | `Membership` | Leden, rollen, toestemming, certificaat |
| Inloggen | `Identity` | Accounts, inloglinks, wachtwoorden, API-tokens |
| Auditlog | `Audit` | Wie deed wat, wanneer en via welk kanaal |
| Instellingen | `Settings` | Branding, teksten en het minimum- en maximumaantal lopers per vereniging |
| Onderhoud | `Maintenance` | Gezondheidscheck, herinneringen, updates, installer, conversie uit v3 |

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
| Herinnering | `Reminder` | Eén bericht per loper per dag, over al zijn rondes van die dag |
| Opmerking | `RoundComment` | Opmerking bij een ronde, met auteur |
| Lid | `Member` | Profiel: naam, contactgegevens, rollen |
| Volledige naam | `name` | Naam van een lid; doorzoekbaar, niet uniek |
| Weergavenaam | `displayName` | Unieke naam in rondes, lijsten en auditlog, zoals "Jan H." |
| Zoeksleutel | `LookupKey` | Naam zonder hoofdletters, accenten, leestekens en dubbele spaties; zoeken en uniek zijn gaan hierop |
| Account | `Account` | Inloggegevens van een lid: wachtwoord en laatste login; het e-mailadres staat op het lid |
| Toestemming contactgegevens | `consentContactShare` | Andere leden zien telefoon en adres alleen hiermee |
| Certificaat | `certified` | Lid heeft een certificaat (in v3: `credited`) |
| Melding bij open ronde | `notifyOpenRounds` | Lid wil mail bij een nieuwe ronde die een loopmaatje zoekt |
| Coördinator | `coordinator` | Rol: wijzigt rondes, handelt namens lopers |
| Analist | `analyst` | Rol: ziet terugmeldingen en statistieken |
| Beheerder | `admin` | Rol: leden, instellingen, onderhoud |
| Dagdeel | `Daypart` | `morning` (voor 12:00), `afternoon` (tot 18:00), `evening` (vanaf 18:00) |
| Minimumaantal lopers | `StaffingRule`, `minimumWalkers` | Standaard 2, vastgelegd per ronde |
| Maximumaantal lopers | `StaffingRule`, `maximumWalkers` | Standaard 7, vastgelegd per ronde; nooit lager dan het minimum |
| Open plekken | `openSpots` | Hoeveel lopers zich nog kunnen aanmelden; 0 als de ronde vol is of begonnen |
| Vol | `openSpots` is 0 | Geen status: een volle ronde is `staffed` |
| Zoekt loopmaatje | `seeking_buddy` | Status: minder lopers dan het minimum |
| Bezet | `staffed` | Status: minimum gehaald |
| Gelopen | `walked` | Status: voorbij en minimum gehaald |
| Niet gelopen | `not_walked` | Status: voorbij zonder loopmaatje |
| Auditregel | `AuditEntry` | Eén regel in het auditlog |
| Kanaal | channel: `web`, `api`, `mcp`, `cron`, `console` | Hoe een actie binnenkwam; `console` voor conversie en voorbeelddata |
| Instelling | `Setting` | Waarde die een vereniging zelf instelt |
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
