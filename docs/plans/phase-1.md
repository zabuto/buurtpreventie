# Fase 1: taakplan

Status: goedgekeurd op 15 november 2026.

Bron: `docs/plan-v4.md` (Domeinmodel en API-ontwerp, Voorbeelddata, Auditlog, Privacy en AVG, Conversie
v3 → v4, Fase 1, Risico's en review-focus), `docs/plans/phase-0.md`, `docs/plans/phase-0-security-scanning.md`,
ADR 0001 tot en met 0004, `AGENTS.md`, en uit `../buurtpreventie-legacy` (alleen gelezen): `src/Entity`,
`src/Service/WalkService.php`, `migrations/`, met de plekken die `WalkService` aanroepen.

## Doel en klaar-criterium

Het domein van v4 staat: de modules `Patrol`, `Membership`, `Identity` en `Audit` met entiteiten,
use cases (invoer en handler), Voters en auditlog, opgeslagen in MariaDB en MySQL, met voorbeelddata.
Controllers en tools volgen in fase 2 en 4.

Gewijzigd op 15 november 2026, na T5: de conversie uit v3 is een eigen fase 5b in `docs/plan-v4.md`, vlak vóór
de installer van fase 6a, zodat ze het model volgt dat uitgebracht wordt. Daarheen gaan T13, de set `v3` uit
T12, de conversietest uit T5 en beslissingen 26 tot en met 29 en 31; ze blijven hieronder staan als
uitgangspunt voor dat taakplan. Beslissing 8 (anonimiseren) volgt met haar eerste gebruiker: de conversie of
het opruimen volgens bewaartermijnen in fase 6a.

Klaar als:

- elke domeinregel uit v3 een test heeft, met per regel de keuze "blijft" of "verbeterd" (tabel bij T4);
- elke schrijvende handler een auditregel achterlaat, een Voter raadpleegt en een rij in de matrixtest heeft,
  en een bewakingstest faalt als een nieuwe schrijvende handler dat mist;
- review-focus 1, 2 en 4 elk een test hebben die groen is (T10, T5, T7);
- de migraties vanaf een lege database draaien en `doctrine:schema:validate` schoon is op MariaDB 10.11 én
  MySQL 8.4, en de databasetests op beide groen zijn;
- `app:fixtures:load` de sets `demo`, `test` en `edge` laadt, en CI elke set laadt met een rooktest;
- ADR 0005 de database-aanpak vastlegt;
- `composer check` lokaal groen is en de pull request naar `develop` groen en gemergd is.

## Vastgesteld in v3

1. **v3 heeft geen schemamigraties.** De enige migratie (`Version20000101000000`) is leeg; het schema kwam uit
   de Doctrine-mapping met `underscore_number_aware`-naamgeving. De conversie leest dus de tabellen die die mapping
   oplevert (`user`, `round`, `round_walker`, `round_result`, `result`, `meeting_point`, `comment`) en controleert
   vooraf of de verwachte kolommen er zijn.
2. **Soft delete overal.** Alle entiteiten behalve `User` hebben `deleted_at` en `deleted_by`, `User` alleen
   `deleted_at`. Afmelden zet `round_walker.deleted_at` (zo telt v3 "N lopers afgemeld"); uitschrijven van een
   inactief lid verwijdert de regel echt (`doHardDelete`); de laatste loper die zich afmeldt laat de ronde
   soft-deleted achter.
3. **Tijd zonder tijdzone.** `round.datetime` is een `DATETIME` in lokale tijd zonder zone; `inPast()` vergelijkt
   datum en `H:i` als tekst met de klok van de server.
4. **Telefoon en mobiel.** `User` heeft `phone` én `mobile` (libphonenumber), v4 noemt één telefoonnummer.
5. **Startpunt als `POINT`.** `meeting_point.location` is een spatial `POINT(lengtegraad breedtegraad)`.
6. **Rollen met hiërarchie.** `ROLE_SUPER_ADMIN` erft alle rollen; `ROLE_WALK` en `ROLE_COORDINATE` erven
   `ROLE_MEMBER`. Aanmelden via de v3-API vraagt alleen `ROLE_USER`; een ronde aanmaken vraagt `ROLE_WALK`; een
   ronde wijzigen of verwijderen mag de coördinator of de maker.
7. **Het minimum staat in `.env`** (`APPLICATION_WALKER_MINIMUM`), niet in de database. De conversie kan het dus
   niet lezen.

## Voorgestelde beslissingen

### Model

1. **Het minimum wordt per ronde vastgelegd** (`Round.minimumWalkers`, gezet bij aanmaken uit de instelling). In
   v3 verandert het verleden mee als het minimum in `.env` wijzigt: een gelopen ronde wordt dan achteraf "niet
   gelopen", en de statistiek (uitbreiding U5) klopt niet meer. De conversie zet minimum en maximum met
   `--minimum-walkers=<n>` (standaard 2) en `--maximum-walkers=<n>` (standaard 7), en vult de instellingen ermee.
2. **Ook een maximum, net zo per ronde vastgelegd** (`Round.maximumWalkers`, instelling standaard 7); anders wordt
   een groep te groot. `StaffingRule` houdt minimum en maximum samen:
   - maximum ≥ minimum, gecontroleerd bij de instelling en bij elke ronde. Een gewijzigde instelling geldt alleen
     voor nieuwe rondes; `ChangeRound` wijzigt minimum en maximum niet;
   - aanmelden bij een volle ronde wordt geweigerd met "ronde is vol", ook namens. Is het lid al aangemeld, dan
     blijft het een geslaagde no-op, ook bij een volle ronde: de controle op dubbel gaat vóór die op vol, anders
     krijgt een dubbelklik op de laatste plek een foutmelding;
   - het aantal lopers is nooit hoger dan het maximum. Daarom krijgt een v3-ronde met meer lopers dan het maximum
     het aantal lopers als maximum (beslissing 29), en niet het standaardmaximum;
   - **"vol" is een veld, geen status.** De status zegt of een ronde doorgaat (`seeking_buddy`, `staffed`,
     `walked`, `not_walked`); een volle ronde is altijd `staffed`, want maximum ≥ minimum. Als eigen status zou
     een filter op `staffed` volle rondes missen, en zou een voorbije volle ronde moeten kiezen tussen `walked` en
     vol. Elke ronde in de uitvoer krijgt daarom `maximumWalkers` en `openSpots` naast `status`, `walkerCount` en
     `minimumWalkers`. `openSpots` is maximum min aantal lopers zolang de ronde niet begonnen is, en 0 vanaf de
     start, omdat aanmelden dan niet meer kan;
   - review-focus 1 houdt de laatste plek: twee lopers tegelijk op de laatste plek geeft precies één aanmelding
     (beslissing 24, T10).
3. **`startsAt` als UTC** via een eigen Doctrine-type `utc_datetime_immutable` in `Shared\Infrastructure`. Het
   standaardtype van Doctrine gebruikt de tijdzone van PHP; zonder eigen type hangt de opgeslagen waarde af van
   de serverinstelling. Status en dagdeel rekenen in `Europe/Amsterdam`.
4. **Geen koppeling tussen modules in de database.** `Participation`, `RoundReport` en `RoundComment` bewaren een
   `memberId`, geen Doctrine-relatie naar `Member`: zo'n relatie zou regel 4 (modules via use cases) breken.
   Er komt dus geen foreign key tussen tabellen van verschillende modules. Daarom wordt een lid nooit uit de
   database verwijderd: wissen (AVG) anonimiseert het lid, zodat elke `memberId` blijft verwijzen naar een
   bestaand lid. Binnen een module wel foreign keys.
   - Een verwijzing naar een andere module is altijd de UUID van het lid (`BINARY(16)`), nooit een oplopend id:
     `Participation.memberId` en `onBehalfOf`, `RoundReport.authorId`, `RoundComment.authorId`,
     `Account.memberId` en `AuditEntry.actorId`.
   - Elke zo'n kolom krijgt een eigen index, ook `Participation.memberId`: de unieke sleutel ronde + lid begint met
     de ronde en helpt dus niet bij "rondes van dit lid". Zonder foreign key maakt de database die index niet
     vanzelf. Een schematest op beide databases (T10) eist een index op elke UUID-kolom die geen primaire sleutel
     is.
5. **Rollen zonder hiërarchie.** `walker`, `coordinator`, `analyst`, `admin` zijn losse rollen; een lid zonder
   rol kan alleen lezen. `ROLE_SUPER_ADMIN` uit v3 wordt alle vier, `ROLE_MEMBER` of `ROLE_USER` alleen wordt
   geen rol (melding in de dry-run). Opslag als `simple_array`, zonder JSON-kolom (zie beslissing 13).
6. **Eén telefoonnummer.** De conversie neemt `mobile`, anders `phone`; zijn beide gevuld en verschillend, dan
   meldt de dry-run het. Alternatief: twee velden in v4 houden.
7. **Een ronde wijzigen of verwijderen:** de coördinator altijd; de maker zolang er nog geen andere loper is
   aangemeld. Daarna moet een loopmaatje erop kunnen rekenen. In v3 mocht de maker altijd.
8. **Gegevens na de bewaartermijn:** `Round` krijgt `anonymisedAt` en `anonymisedWalkerCount`, zodat aantal
   lopers en status blijven kloppen als de aanmeldingen weg zijn; `RoundReport` verliest alleen de notitie. De
   conversie gebruikt dit al (Conversie v3 → v4); de scheduler later ook.
9. **Een verwijderd v3-lid wordt een eigen anoniem lid** ("verwijderd lid", inactief, zonder account en zonder
   weergavenaam), één per
   v3-gebruiker. Eén gedeeld anoniem lid botst met de unieke sleutel ronde + lid zodra twee verwijderde leden in
   dezelfde ronde liepen.

### Architectuur

10. **`Symfony\Component\Uid` mag in `Domain`** (regel 1 uitgebreid). ID's zijn UUIDv7 (`Uuid`); een eigen
    ID-klasse per entiteit zou alleen een laag om dezelfde waarde zijn. De uitbreiding krijgt een sectie in
    `docs/architecture.md`; de testopstelling blijft bewijzen dat Doctrine ORM in `Domain` wel afgaat.
11. **Handlers kennen geen Doctrine** (regel 2 uitgebreid met `Doctrine\ORM` en `Doctrine\DBAL`). Ze werken
    via repository-interfaces in `Domain` en via `Shared\Domain\Transaction`, zodat wijziging en auditregel in
    één transactie vallen. Met een overtreding in `tests/Architecture/Fixtures/`.
12. **Tijd, kanaal en uitvoerder komen via `Shared\Domain`:**
    - domeinmethodes krijgen `DateTimeImmutable $now` mee, handlers krijgen `Psr\Clock\ClockInterface`;
    - `Shared\Domain\ExecutionContext` geeft het kanaal (`web`, `api`, `mcp`, `cron`, `console`) en het lid dat
      de actie uitvoert. In fase 1 vullen console-commando's en tests het; fase 2 en 4 voegen HTTP en MCP toe.
      `console` is nieuw ten opzichte van de woordenlijst, voor conversie en voorbeelddata;
    - `Shared\Domain\Authorization::denyUnlessGranted()`, geïmplementeerd met Symfony's AccessDecisionManager
      en de Voters. Elke Voter staat in de `Infrastructure` van zijn module en volgt een regelklasse in `Domain`
      (zoals `RoundPolicy`), zodat de matrixtest in memory draait.
13. **Geen JSON-kolommen.** MariaDB slaat `JSON` op als `LONGTEXT` met een controle, MySQL heeft een eigen type;
    dezelfde migratie levert dan op de twee een ander schema. De gewijzigde velden van een auditregel worden tekst
    met `json_encode`, rollen een `simple_array`.
14. **Schrijvende handlers dragen `#[WriteAction(...)]`** met de Voter-actie en de auditactie, lezende
    `#[ReadAction]`. Een bewakingstest leest alle handlers: elke handler heeft één van beide, elke `WriteAction`
    heeft een rij per rol in de matrixtest, en de test van de handler heeft een test die met `it_records_` begint.
15. **E-mail volgt in fase 3.** `SendReminders` en `NotifyBuddyNeeded` bepalen in fase 1 wie welk bericht krijgt,
    en leggen vast dat het verstuurd is (`remindedAt`, één bericht per ronde). Versturen loopt via een interface
    `Shared\Domain\Notifier`; fase 1 heeft alleen een implementatie die logt, tests gebruiken een eigen in-memory
    versie. Zo komen `symfony/mailer` en Twig pas in fase 3.
16. **Hashketen in het auditlog niet in fase 1.** Het plan noemt die optioneel; een hash van de vorige regel maakt
    de anonimisering bij wissen (de naam wordt "verwijderd lid") lastiger. Apart te beslissen.

### Database (ADR 0005)

17. **Twee databases, één set migraties.** Migraties worden lokaal op MariaDB 10.11 gegenereerd en met de hand
    nagelopen. CI draait ze vanaf een lege database op MariaDB 10.11 en MySQL 8.4, met daarna
    `doctrine:schema:validate` en de databasetests. Een migratie die alleen op één database werkt, faalt zo
    meteen. Migraties mogen alleen toevoegen en blijven terug te draaien (`down()`), zoals het plan voor
    patchversies eist.
18. **`utf8mb4` met `utf8mb4_unicode_ci`** als standaard-tabeloptie in `doctrine.yaml`, en `charset: utf8mb4` op
    de verbinding. MySQL 8.4 kiest anders `utf8mb4_0900_ai_ci`, MariaDB `utf8mb4_general_ci`.
19. **Zoeken is hoofdletter- en accentongevoelig, met een zoeksleutel uit PHP in een `utf8mb4_bin`-kolom.**
    Zoeken op "Jose" of "JOSE" vindt "José". De collation laten vergelijken is niet genoeg: wat
    `utf8mb4_unicode_ci` op MariaDB en `utf8mb4_0900_ai_ci` op MySQL gelijk vinden, verschilt (bijvoorbeeld `ß`
    en ligaturen), en een sleutel uit PHP is op beide databases dezelfde. Daarom:
    - `LookupKey` maakt van een naam: `trim`, spaties samengevoegd, `Normalizer::normalize(..., FORM_D)`, de
      combinerende tekens weg (`\p{Mn}`), dan `mb_strtolower`. Letters die niet ontleden (`ø`, `ł`, `æ`, `ß`)
      blijven staan: "Søren" vindt "Soren" niet. Ongeldige UTF-8 wordt bij de invoer geweigerd, want `Normalizer`
      geeft dan `false`;
      leestekens tellen als spatie (vastgelegd bij T6), anders botsen "Jan H." en "jan h" niet;
    - `EmailAddress` maakt `mb_strtolower(trim(...))` en haalt geen accenten weg: met en zonder accent zijn twee
      adressen. Het adres wordt alleen zo opgeslagen;
    - de sleutel staat in een eigen kolom met `utf8mb4_bin` en een index; de naam zoals getypt staat ernaast en is
      wat de app toont;
    - **bij leden zijn het e-mailadres en de weergavenaam uniek, de volledige naam niet.** Twee leden mogen
      dezelfde volledige naam hebben (`name`), ook "José" en "Jose". In rondes, bij "zoekt loopmaatje", in lijsten
      en in het auditlog staat de weergavenaam (`displayName`, bijvoorbeeld "Jan H." en "Jan B."): met twee keer
      "Jan" weet niemand wie er loopt. De weergavenaam is uniek op dezelfde `LookupKey`, dus "Jan H." en "jan h"
      botsen, net als "José" en "Jose". Beide zijn doorzoekbaar;
    - de beheerder kiest de weergavenaam bij `AddMember` en kan hem wijzigen met `ChangeMember`. Een gewist lid en
      een verwijderd v3-lid hebben geen weergavenaam (sleutel `NULL`; een unieke index in InnoDB staat
      meerdere `NULL`'s toe, op beide databases) en staan in beeld als "verwijderd lid";
    - bij een tweede gelijk e-mailadres of gelijke weergavenaam weigert de handler met een eigen fout; de unieke
      index vangt de gelijktijdige variant, en de repository vertaalt die naar dezelfde fout;
    - lopers zien alleen de weergavenaam, coördinator en beheerder ook de volledige naam. De uitvoer van fase 1
      (`GetMemberSummaries`, `GetRound`) volgt dat al, met een test per rol; fase 2 toont het via de API;
    - **startpunten en uitkomsten houden een unieke naam, met dezelfde sleutel.** Ze staan in een keuzelijst:
      "Café" en "cafe" naast elkaar zijn voor een loper niet uit elkaar te houden. De unieke index geldt ook voor
      gearchiveerde items, omdat MariaDB en MySQL geen gedeeltelijke index kennen. Een naam die al gearchiveerd
      bestaat geeft een fout die dat zegt, en de beheerder zet het oude item terug (`RestoreMeetingPoint`,
      `RestoreOutcome`). Botsen twee v3-items op de sleutel, dan krijgt het tweede " (2)" achter de naam, met een
      waarschuwing in de dry-run;
    - zoeken is "bevat": `LIKE` op de sleutel, met `%`, `_` en `\` in de zoekterm geëscaped. De index helpt daarbij
      alleen voor "begint met"; bij enkele honderden leden maakt dat niets uit;
    - `Normalizer` en `mb_*` komen uit ext-intl en ext-mbstring, die standaard aan staan bij shared hosting.
      `composer.json` eist ze (`ext-intl`, `ext-mbstring`), zodat Composer en zijn platformcontrole een host
      zonder deze extensies meteen weigeren; de app leunt niet op de polyfills die `symfony/string` toevallig
      meebrengt. Een hostingpaneel kan ze wel uitzetten: de diagnosepagina van de installer (fase 6a) controleert
      ze.
20. **Geheime tokens alleen als hash.** SHA-256 van 32 willekeurige bytes (`random_bytes`), opgeslagen als
    `BINARY(32)`, gezocht op de hash zelf; de tekst wordt één keer getoond. Dat geldt in fase 1 voor
    `ApiToken`; inloglink en wachtwoord-reset (fase 2) volgen hetzelfde patroon. Een geheim wordt altijd
    gegenereerd, nooit ingevuld (besloten op 15 november 2026): `ApiToken::issue()` weigert alles wat niet `bpt_`
    plus 64 hex-tekens is. Wachtwoorden gaan via
    Symfony's `auto`-hasher, zodat v3-hashes geldig blijven.
21. **Geen eigen SQL**, behalve in een repositorymethode in `<Module>\Infrastructure\Persistence\` met een
    integratietest die op beide databases draait. Querybuilder en DQL gelden ook als eigen query: elke
    repositorymethode met een query heeft zo'n test; `find()` en `findBy()` niet.
22. **Coördinaten als `DECIMAL(9,6)`**, breedte- en lengtegraad in WGS84, in een embeddable `Coordinates` met
    controle op het bereik. Geen spatial types. De conversie leest `POINT(lengte breedte)` met
    `ST_X()`/`ST_Y()`, wat op beide databases bestaat.
23. **ID's als `BINARY(16)`.** Of Doctrine op MariaDB 10.11 het eigen `UUID`-type van MariaDB kiest, controleert
    T3; zo ja, dan wordt de kolom expliciet `BINARY(16)`, zodat het schema op beide gelijk is.
24. **Aanmelden is veilig bij gelijktijdigheid:**
    - unieke sleutel op ronde + lid; een tweede aanmelding van hetzelfde lid is een geslaagde no-op (`PUT` is
      idempotent);
    - aanmelden en afmelden zetten als eerste query van de transactie een schrijfslot op de ronde
      (`PESSIMISTIC_WRITE`, `SELECT ... FOR UPDATE`). Pas daarna tellen ze de aanmeldingen, met een vergrendelende
      query in de repository en niet met een collectie die al geladen was. Zo wacht de tweede aanmelder voor de
      laatste plek op de eerste, telt hem mee en krijgt "ronde is vol"; en verdwijnt een aanmelding nooit doordat
      tegelijk de laatste loper vertrekt en de ronde wordt verwijderd;
    - waarom de telling zelf ook vergrendelt: InnoDB legt in `REPEATABLE READ` de momentopname vast bij de eerste
      gewone `SELECT` van de transactie. Leest de handler vóór het slot al iets, dan ziet een gewone telling de
      aanmelding van de ander niet, en komt er een loper te veel bij. Een vergrendelende lezing ziet altijd de
      laatste gecommitte stand.
25. **Databasetests:** groep `database`, met `dama/doctrine-test-bundle`; elke test rolt terug en filtert op de
    id's die hij zelf maakt. De gelijktijdigheidstest zet DAMA voor zichzelf uit, werkt met twee eigen
    verbindingen die echt committen en ruimt zijn rijen zelf op. Het schema wordt gemaakt vóór de tests, door een
    eigen PHPUnit-extensie (migraties vanaf leeg, één keer per run), omdat MariaDB bij DDL stil commit en
    `tests/bootstrap.php` een receptbestand is dat blijft zoals geleverd. Zonder bereikbare database slaan de
    databasetests over met een duidelijke reden; in CI maakt `REQUIRE_DATABASE=1` van overslaan een fout.

### Conversie en voorbeelddata

Beslissingen 26 tot en met 29 en 31 gelden voor fase 5b; beslissing 30 voor T12.

26. **`app:import-v3` vult alleen een lege v4-database** en weigert anders. De v3-database komt via een tweede
    verbinding `legacy` (`LEGACY_DATABASE_URL`), met alleen `SELECT`-rechten (staat in de handleiding) en een
    lezer die alleen vaste `SELECT`-query's kent.
27. **`--dry-run` voert de hele conversie uit in een transactie en rolt die terug.** Het rapport komt dus uit
    precies dezelfde code: aantallen per soort, waarschuwingen, en wat niet meegaat.
28. **Opnieuw draaien geeft hetzelfde resultaat, behalve de ID's.** Een test converteert twee keer naar een lege
    database en vergelijkt alles behalve de UUID's. Een koppeltabel `legacy_id_map` (soort, oud id, nieuw id)
    blijft staan voor naslag.
29. **Wat de conversie doet met v3-gegevens:**
    - soft-deleted rondes en catalogusitems gaan niet mee, behalve een catalogusitem waar een meegenomen
      terugmelding of ronde naar verwijst: dat wordt gearchiveerd;
    - een afgemelde `round_walker` wordt geen aanmelding, wel een auditregel "afgemeld" (gemarkeerd "uit v3") als
      hij binnen de bewaartermijn valt;
    - een terugmelding bij een niet-gelopen ronde gaat niet mee, met een waarschuwing in het rapport; meerdere
      terugmeldingen van één lid bij één ronde worden de laatste, ook met waarschuwing;
    - een `result` met `incident` wordt `incident`, anders met `remarks` wordt `remarks`, anders
      `nothing_to_report`;
    - de v3-naam wordt volledige naam én weergavenaam. v3 eiste al een unieke naam, maar botsen twee namen op de
      sleutel (hoofdletters, accent of spaties, afhankelijk van de v3-collation), dan krijgt de tweede
      weergavenaam " (2)", met een waarschuwing in de dry-run;
    - een ronde met meer lopers dan `--maximum-walkers` krijgt `maximumWalkers` gelijk aan het aantal lopers, met
      een waarschuwing in de dry-run; minimum en maximum met maximum < minimum geven een fout vóór de conversie;
    - twee startpunten of uitkomsten met dezelfde sleutel: zie beslissing 19;
    - `round.datetime` geldt als `Europe/Amsterdam`; een tijd die bij de wintertijdwissel dubbel bestaat of bij
      de zomertijdwissel niet bestaat, staat als waarschuwing in het rapport;
    - bewaartermijnen en `--since=<datum>` zoals in het plan.
30. **Voorbeelddata met een eigen command en `doctrine/data-fixtures`, zonder de bundle.** `app:fixtures:load
    <set>` leegt de database met de `ORMPurger` en bouwt de set via de use cases, met een vaste seed en datums vanaf
    de klok. De bundle voegt daar `doctrine:fixtures:load` en servicetags aan toe, die dit project niet gebruikt;
    zonder bundle is er een afhankelijkheid minder.
31. **De set `v3` vult de `legacy`-verbinding** met een schema dat de v3-mapping nabouwt (zie Vastgesteld 1) en
    verzonnen rijen: hooguit 12 leden, rondes over 30 maanden, soft-deleted rijen, een verwijderd lid in de
    historie, een tijd rond de wintertijdwissel, twee leden met zowel telefoon als mobiel, een ronde met acht
    lopers (boven het maximum van 7). Wachtwoorden zijn
    bcrypt-hashes van vaste voorbeeldwachtwoorden die alleen in de fixture-code staan. De schemadefinitie is
    nagebouwd uit de MIT-code van v3; v3 zelf blijft ongemoeid.

### Opengrep-regels voor deze fase

32. Elke regel met een regeltest, zoals in `docs/testing.md`:
    - **`sql-built-from-strings` uitbreiden** met `select`, `addSelect`, `from`, `join`, `innerJoin`, `leftJoin`,
      `groupBy`, `addGroupBy` en `set` van de querybuilder;
    - **`sql-outside-repository`** (nieuw): `executeQuery`, `executeStatement`, `fetch*`, `createQuery`,
      `createNativeQuery` en `createQueryBuilder` alleen in `src/*/Infrastructure/Persistence/` (fase 5b voegt
      de v3-lezer toe);
    - **`weak-random-or-hash`** (nieuw): `md5`, `sha1`, `rand`, `mt_rand`, `uniqid`, `lcg_value` en
      `str_shuffle` niet in `src/` (v3 maakte tokens met `sha1(random_bytes(10))`); voor tokens `random_bytes` en
      `hash('sha256', ...)`;
    - **`byte-lowercase`** (nieuw): `strtolower` en `strtoupper` niet in `src/`, want ze laten `É` staan; de
      zoeksleutel gebruikt `mb_strtolower`.

## Nieuwe afhankelijkheden (ter goedkeuring)

Alle MIT. Versies en datums van Packagist op 15 november 2026. De Composer-cooldown van 24 uur houdt releases
tegen die jonger zijn dan 24 uur; `composer require` kiest dan de vorige versie.

| Pakket | Waarvoor | Soort | Releaseritme | Nu |
| --- | --- | --- | --- | --- |
| `doctrine/orm` | Entiteiten, mapping, unit of work | runtime | Patch elke 1 tot 3 weken, minor een paar keer per jaar | 3.8.0 (14 nov, 11:18 UTC; buiten de cooldown sinds 15 nov, 11:18 UTC) |
| `doctrine/dbal` (via ORM) | Verbinding, platforms, schema | runtime | Patch maandelijks, minor een paar keer per jaar | 4.6.0 (14 nov, cooldown), 4.5.1 (10 nov) |
| `doctrine/doctrine-bundle` | Koppeling met Symfony, `doctrine.yaml` | runtime | Patch elke paar weken; 3.x vraagt PHP 8.4 | 3.4.2 (15 nov, cooldown), 3.4.1 (12 nov) |
| `doctrine/doctrine-migrations-bundle` + `doctrine/migrations` | Migraties, ook voor de updater in fase 6b | runtime | Een paar releases per jaar | 4.0.1 (aug 2026); migrations 3.9.8 (okt 2026) |
| `symfony/doctrine-bridge` (via de bundle) | UUID-type, `UniqueEntity` | runtime | Symfony 7.4 LTS, patch ongeveer maandelijks | 7.4.22 (18 okt) |
| `symfony/uid` | UUIDv7 | runtime | Symfony 7.4 LTS | 7.4.x |
| `symfony/clock` | Klok die tests vastzetten | runtime | Symfony 7.4 LTS | 7.4.21 (12 okt) |
| `symfony/security-bundle` | Voters, AccessDecisionManager, wachtwoord-hasher (`auto`); fase 2 bouwt erop verder | runtime | Symfony 7.4 LTS | 7.4.x |
| `doctrine/data-fixtures` | `ORMPurger` en volgorde voor `app:fixtures:load` | dev | Een paar releases per jaar | 2.2.1 (apr 2026) |
| `dama/doctrine-test-bundle` | Elke databasetest rolt terug | dev | Elke paar weken tot maanden | v8.8.0 (13 nov), v8.7.1 (10 nov) |

Niet nodig bij beslissing 30: `doctrine/doctrine-fixtures-bundle`. `symfony/uid`, `symfony/clock` en
`symfony/security-bundle` stonden niet in de prompt; ze zijn nodig voor UUIDv7, de vaste klok in tests en de Voters
uit het plan.

Daarnaast eist `composer.json` de PHP-extensies `ext-intl` en `ext-mbstring` (beslissing 19); dat zijn geen
pakketten, en CI zet ze expliciet aan in `setup-php`.

Dependabot neemt ze vanzelf mee, binnen de cooldown en zonder Symfony-majors; `SymfonyReleaseLineTest` bewaakt
dat de drie Symfony-pakketten op 7.4 staan.

## Taken

Elke taak is één of enkele kleine commits; tests komen vóór de code.

### T1. Branch en taakplan

`feature/phase-1` vanaf `develop`; dit plan. Bouwen pas na akkoord.

### T2. Afhankelijkheden en configuratie

- `composer require` van de goedgekeurde pakketten en van `ext-intl` en `ext-mbstring`; recepten nalopen,
  receptbestanden ongewijzigd laten. `extensions: intl, mbstring` bij elke `setup-php` in `ci.yml` en
  `weekly.yml`; `docs/getting-started.md` en `docs/operations.md` noemen de twee extensies.
- `config/packages/doctrine.yaml`: tabelopties (beslissing 18), eigen types, mapping per module uit
  `src/<Module>/Domain`, verbinding `legacy`; `doctrine_migrations.yaml` met migraties in `migrations/`.
- `phpunit.dist.xml`: de DAMA-extensie. De schema-extensie (beslissing 25) komt in T3, met de eerste
  databasetest die haar nodig heeft.
- `compose.yaml`: een init-script geeft de databasegebruiker alle databases met het voorvoegsel
  `buurtpreventie_` (`_test`, `_legacy`, `_legacy_test`); in `dev` en `test` gebruikt de `legacy`-verbinding
  `DATABASE_URL` met een eigen achtervoegsel.
- Test eerst: `SymfonyReleaseLineTest` dekt de nieuwe Symfony-pakketten; `composer deprecations` blijft schoon.

### T3. Gedeelde bouwstenen (test eerst)

In `Shared\Domain`: `Transaction`, `ExecutionContext` met `Channel`, `AuditTrail`, `Authorization`, `Notifier`,
`EmailAddress`, `LookupKey`. In `Shared\Infrastructure`: `UtcDateTimeImmutableType`, de Doctrine-transactie,
de Symfony-autorisatie. Unit-tests voor de value objects:

- `EmailAddress`: `Anke@Example.org ` wordt `anke@example.org`; een accent blijft staan;
- `LookupKey`: "José", "Jose" en "JOSE" geven dezelfde sleutel; dubbele spaties tellen als één; "Søren" en
  "Soren" blijven verschillend (de grens uit beslissing 19); ongeldige UTF-8 wordt geweigerd.

Een integratietest dat `utc_datetime_immutable` een tijd ongewijzigd heen en terug brengt, en welk kolomtype een
UUID op MariaDB krijgt (beslissing 23). Daarvoor eerst de schema-extensie voor PHPUnit uit beslissing 25.

PHPat: regel 1 en 2 uitbreiden (beslissingen 10 en 11), met een overtreding per uitbreiding in de fixtures en
een sectie in `docs/architecture.md`.

### T4. Regels uit v3 als test, dan het Patrol-domein

Eerst de tests, in memory, met een vaste klok. Per v3-regel:

| v3 (`WalkService` en aanroepers) | v4 | Test (in `tests/Patrol/Domain/`) |
| --- | --- | --- |
| `hasMinimumWalkers`: aantal ≥ minimum | blijft, minimum per ronde | `it_is_staffed_once_the_minimum_is_reached` |
| `wasWalked`: voorbij en minimum gehaald | blijft, als status `walked`/`not_walked` | `it_counts_as_walked_only_when_past_with_the_minimum` |
| `getWalked` laat rondes onder het minimum weg | blijft, als status | `it_counts_a_past_round_below_the_minimum_as_not_walked` |
| `inPast`: datum en `H:i` als tekst, serverklok | verbeterd: tijdstip in UTC tegen de klok | `it_starts_at_the_same_instant_in_every_time_zone` |
| `getTimeOfDay`: < 12, < 18, anders avond | blijft, in `Europe/Amsterdam` | `it_takes_the_daypart_from_local_time` |
| `addRound`: maker wordt meteen loper, memo wordt opmerking | blijft, als eerste loper en eigenaar; de opmerking maakt `CreateRound` (T9) | `it_signs_up_the_first_walker_of_a_new_round` |
| `walkRound`: geen controle op dubbel | verbeterd: idempotent | `it_ignores_a_second_sign_up_of_the_same_member` |
| `walkRound`: ook voor een voorbije ronde | verbeterd: alleen vóór de start | `it_refuses_a_sign_up_after_the_start` |
| `exitRound`: laatste loper weg, ronde weg | blijft; het domein meldt het, de handler verwijdert (T9) | `it_asks_for_deletion_when_the_last_walker_withdraws` |
| `exitRound`: ook na de start | verbeterd: alleen vóór de start | `it_refuses_a_withdrawal_after_the_start` |
| `roundResult`: memo alleen bij `remarks` | verbeterd: notitie bij `remarks` en `incident` | `it_drops_the_note_when_there_is_nothing_to_report` |
| `roundResult`: geen controle op status of loper | verbeterd: alleen `walked`; een loper van die ronde of de coördinator (`RoundPolicy`) | `it_refuses_a_report_for_a_round_that_was_not_walked` |
| `roundResult`: meerdere per lid | verbeterd: één per ronde, een tweede wordt geweigerd | `it_refuses_a_second_report` |
| `isUserRoundResult`, `isUserComment`: eigenaar is `updatedBy ?? createdBy` | verbeterd: vaste auteur, plus coördinator | `it_lets_only_the_author_or_a_coordinator_delete_a_report` |
| `walkerRemoveFromFutureRounds`: vanaf morgen | verbeterd: alles wat nog niet begonnen is | `it_removes_an_inactive_member_from_rounds_that_have_not_started` |
| `getWalkers`/`getWalkedWith`: inactieve lopers als aantal | blijft, in de uitvoer van `GetRound` (T9): alleen Membership weet wie inactief is | `it_lists_inactive_walkers_as_a_count` (in `tests/Patrol/UseCase/GetRound/`) |
| Herinnering één keer per lid per dag, alleen actieve lopers | blijft | `it_reminds_each_active_walker_once` |
| `setActive(false)` wist wachtwoord en token | verbeterd: account uit, tokens ingetrokken, wachtwoord blijft voor heractiveren | `it_revokes_every_token_on_deactivation` (Membership/Identity) |
| `erasePersonalInformation` | blijft, plus naam "verwijderd lid" in historie en auditlog | `it_erases_contact_details_and_the_name` |
| Ronde wijzigen of verwijderen: coördinator of maker | verbeterd: coördinator, of de eigenaar zolang hij de enige loper is | `it_lets_the_owner_change_a_round_until_a_buddy_joins` |
| Aanmelden: elke ingelogde gebruiker | verbeterd: rol `walker` | in de matrixtest |

Nieuwe regels, die v3 niet had (beslissing 2):

| Regel | Test (in `tests/Patrol/Domain/`) |
| --- | --- |
| Minimum en maximum komen bij aanmaken uit de instelling en veranderen daarna niet | `it_keeps_the_staffing_rule_it_was_created_with` |
| Maximum onder het minimum wordt geweigerd | `it_refuses_a_maximum_below_the_minimum` |
| Aanmelden bij een volle ronde wordt geweigerd | `it_refuses_a_sign_up_when_the_round_is_full` |
| Een lid dat al aangemeld is, meldt zich ook bij een volle ronde zonder fout aan | `it_ignores_a_second_sign_up_in_a_full_round` |
| Open plekken tot de start, daarna 0 | `it_has_no_open_spots_once_started` |
| Een volle ronde is `staffed` | `it_reports_a_full_round_as_staffed` |

Namens aanmelden bij een volle ronde test de handler in T9.

Daarna `Round`, `Participation`, `RoundReport`, `Outcome`, `MeetingPoint`, `RoundComment`, `StaffingRule`
(minimum en maximum), `RoundStatus`, `Daypart`, `RoundPolicy` en de repository-interfaces.

### T5. Review-focus 2: zomer- en wintertijd (test eerst)

- Domein: een ronde om 22:00 op de dag vóór en na beide wissels houdt 22:00 `Europe/Amsterdam` en dagdeel
  `evening`; de herinnering valt op de goede kalenderdag.
- Database (beide): dezelfde rondes heen en terug via de repository, met `startsAt` exact gelijk.
- Conversie: een v3-tijd in het dubbele uur geeft een waarschuwing en een vaste keuze (de eerste, zomertijd).
  Deze test komt met de conversie in fase 5b; de databasetest brengt de Patrol-repository en de eerste migratie
  naar voren, de andere modules volgen in T10.
  Fase 2 test dezelfde rondes via de API.

### T6. Membership, Identity, Settings (test eerst)

- `Member` met `EmailAddress`, volledige naam en weergavenaam elk met `LookupKey`, telefoon, adres, rollen, `consentContactShare`,
  `certified`, `active`, `notifyOpenRounds`, `erasedAt`.
- `Account` (lid-id, wachtwoordhash, laatste login) en `ApiToken` (hash, naam, scopes `read`/`write`/`admin`,
  verloopdatum, laatst gebruikt, `revokedAt`). Het e-mailadres staat alleen op `Member` (besloten op 15 november
  2026): één adres voor mails en inloggen, dat nooit uit elkaar loopt, op één plek gewist wordt, en ook een lid
  zonder account bereikt. Bij inloggen (fase 2) zoekt Identity het lid op via een use case van Membership.
- `Setting` met alleen het minimum- en maximumaantal lopers (standaard 2 en 7, maximum ≥ minimum); beheer volgt
  in fase 3.
- Use cases: `AddMember`, `ChangeMember`, `ChangeMemberRoles`, `ChangeContactConsent`, `DeactivateMember`,
  `ReactivateMember`, `EraseMember`, `GetMemberSummaries` (met optionele zoekterm op naam); `SetPassword`,
  `VerifyPassword`, `CreateApiToken`, `RevokeApiToken`, `RevokeMemberApiTokens`.
- Tests: twee leden met dezelfde volledige naam, en "José" naast "Jose", bestaan naast elkaar; `AddMember` en
  `ChangeMember` weigeren een e-mailadres dat alleen in hoofdletters verschilt van een bestaand adres, en een
  weergavenaam die op de sleutel botst ("Jan H." en "jan h", "José" en "Jose"); twee gewiste leden bestaan naast
  elkaar zonder weergavenaam.

### T7. Review-focus 4: gedeactiveerd of gewist lid (test eerst)

- `DeactivateMember` schrijft het lid uit elke ronde die nog niet begonnen is (via de Patrol-use case
  `WithdrawMemberFromUpcomingRounds`), elk met een auditregel "automatisch uitgeschreven"; valt een ronde onder
  het minimum, dan krijgt de overgebleven loper bericht; was het de laatste loper, dan vervalt de ronde.
- Na `DeactivateMember` en `EraseMember` is elk token ingetrokken en het account uit, in dezelfde transactie.
- Een gewist lid staat in historie en auditlog als "verwijderd lid", zonder contactgegevens.
- Fase 2 test dat de token-authenticator een ingetrokken token meteen weigert en dat de ledenlijst
  gedeactiveerde leden weglaat.
- Uitgevoerd op 15 november 2026, met deze keuzes:
  - "account uit" is het lid zelf: `Account` krijgt geen eigen vlag, want de authenticator van fase 2 weigert
    al op een gedeactiveerd of gewist lid. Eén bron, net als het e-mailadres; het wachtwoord blijft;
  - `WithdrawMemberFromUpcomingRounds` komt uit T9 naar voren. De beheerder roept hem aan via `DeactivateMember`
    en `EraseMember`; de Voter (T9) staat hem dus toe aan de beheerder, als deel van ledenbeheer;
  - bericht gaat alleen uit als de ronde door het uitschrijven onder het minimum zakt, niet als ze er al onder
    zat;
  - "verwijderd lid" in auditlog en historie volgt met `EraseActorName` (T8) en `GetRound` (T9).

### T8. Audit (test eerst)

- `AuditEntry`: tijdstip (UTC), lid-id en weergavenaam op dat moment of "systeem", actie (`round.created`, ...),
  onderwerp (soort en id), kanaal, gewijzigde velden oud → nieuw, `importedFromV3`. Nooit wachtwoorden,
  tokens of volledige IP-adressen.
- `DoctrineAuditTrail` schrijft in de lopende transactie; `EraseActorName` vervangt de naam van een gewist lid.
- Geen use case om regels te wijzigen of te verwijderen. Lezen via API volgt in fase 2.
- Uitgevoerd op 15 november 2026, met deze keuzes:
  - de naam op dat moment komt uit `Shared\Domain\MemberNames`, die Membership implementeert; `Actor` en
    `ExecutionContext` krijgen er geen veld bij;
  - geen actor is "systeem", een actor zonder naam is "verwijderd lid"; `EraseMember` roept `EraseActorName`
    aan in dezelfde transactie, en die wist de naam met één bulk-update;
  - een veldnaam met `password`, `secret`, `token` of `hash` wordt geweigerd; een IP-adres heeft geen veld;
  - de migratie voor `audit_entry` komt nu, omdat de databasetests de tabel nodig hebben; de andere modules volgen
    in T10.

### T9. Patrol-use cases, Voters en matrixtest (test eerst)

- Use cases: `CreateRound`, `ChangeRound`, `DeleteRound`, `SignUpForRound` (ook namens), `WithdrawFromRound`
  (ook namens en verwijderen door de coördinator), `WithdrawMemberFromUpcomingRounds`, `ReportRoundResult`,
  `DeleteRoundReport`, `AddRoundComment`, `DeleteRoundComment`, `CreateMeetingPoint`, `ChangeMeetingPoint`,
  `ArchiveMeetingPoint`, `RestoreMeetingPoint`, `CreateOutcome`, `ChangeOutcome`, `ArchiveOutcome`,
  `RestoreOutcome`, `ListRounds`, `GetRound`, `FindStaffingGaps`, `SendReminders`, `NotifyBuddyNeeded`.
- `ListRounds` en `GetRound` geven per ronde `status`, `walkerCount`, `minimumWalkers`, `maximumWalkers`,
  `openSpots` en `daypart`.
- `FindStaffingGaps` geeft de rondes die nog niet begonnen zijn met status `seeking_buddy`, op starttijd, met
  `walkersNeeded` (minimum min aantal lopers) en `openSpots`. Een volle ronde is nooit een gat, want maximum ≥
  minimum; een test met een volle en een bijna volle ronde legt dat vast.
- Handlertests: namens aanmelden bij een volle ronde geeft "ronde is vol"; een startpunt of uitkomst met een naam
  die op de sleutel botst wordt geweigerd, ook tegen een gearchiveerd item, en de fout noemt dat item.
- Voters: `RoundVoter`, `ParticipationVoter`, `ReportVoter`, `CommentVoter`, `CatalogueVoter`, `MemberVoter`,
  `ApiTokenVoter`.
- Matrixtest (`tests/Shared/AuthorizationMatrixTest.php`): rol (geen rol, loper, coördinator, analist, beheerder)
  × elke `WriteAction`, met als verwachting:

  | Actie | Wie |
  | --- | --- |
  | Ronde aanmaken | loper; coördinator namens een actief lid |
  | Ronde wijzigen of verwijderen | coördinator; eigenaar zolang hij de enige loper is |
  | Zelf aan- of afmelden | loper |
  | Namens aan- of afmelden, loper verwijderen | coördinator |
  | Terugmelden (één per ronde) | loper van die ronde, coördinator |
  | Terugmelding of opmerking verwijderen | auteur, coördinator |
  | Opmerking plaatsen | loper van die ronde, ook vóór de start; coördinator (besloten op 15 november 2026) |
  | Startpunten en uitkomsten | beheerder |
  | Leden toevoegen, wijzigen, rollen, (de)activeren, wissen | beheerder |
  | Eigen toestemming, eigen tokens | het lid zelf |

  De beheerder beheert leden, catalogi en instellingen en heeft geen rechten op de inhoud van rondes; wie dat
  nodig heeft, krijgt ook de rol coördinator (besloten op 15 november 2026).

- De bewakingstest uit beslissing 14.
- Rechten en use cases (besloten op 15 november 2026):
  - namens aanmelden kan voor elk actief lid, ook zonder de rol loper; Patrol vraagt dat via een interface in
    `Shared\Domain`, zoals `MemberNames`;
  - zelf een ronde aanmaken vraagt de rol loper; de coördinator maakt ook een ronde aan namens een actief lid. Dat
    is namens aanmelden: de eerste loper is de eigenaar, niet de coördinator;
  - één terugmelding per ronde, niet één per loper: een loper van die ronde of de coördinator meldt terug, en dat
    is genoeg. De eigenaar hoeft het niet te zijn. Reageren door de andere lopers volgt later (uitbreiding U3 in
    `docs/plan-v4.md`);
  - een tweede terugmelding wordt geweigerd ("deze ronde heeft al een terugmelding"), zodat niemand ongemerkt de
    melding van een ander overschrijft. De auteur of de coördinator verwijdert de bestaande; daarna kan er een
    nieuwe komen. Er is geen use case om een terugmelding te wijzigen;
  - de eigenaar wordt afgeleid, niet opgeslagen: de loper die zich het eerst aanmeldde en nog in de ronde zit.
    Meldt die zich af, dan is de volgende de eigenaar. Beslissing 7 wordt daarmee: de coördinator altijd, de
    eigenaar zolang hij de enige loper is. `Round.createdBy` en de kolom `created_by` vervallen; wie de ronde
    aanmaakte, staat in het auditlog: `round.created` met de uitvoerder (ook een coördinator) en in de gewijzigde
    velden de eerste loper. Bij aanmaken namens bewaart de aanmelding van de eerste loper ook `onBehalfOf`;
  - `GetRound` toont het telefoonnummer van een loper met toestemming alleen aan de andere lopers van die ronde en
    aan de coördinator;
  - de laatste beheerder kan zijn rol niet kwijtraken en niet gedeactiveerd of gewist worden; `ChangeMemberRoles`,
    `DeactivateMember` en `EraseMember` weigeren dat met een eigen fout;
  - `NotifyBuddyNeeded` stuurt één bericht per ronde, aan actieve lopers met `notifyOpenRounds` die er nog niet in
    zitten, en alleen als de ronde dan nog een loopmaatje zoekt. Niet bij het aanmaken: lopers krijgen eerst de tijd
    om zich zelf aan te melden. Het moment is het eerste van 5, 3 of 1 dag vóór de start dat na het aanmaken valt;
    is de ronde minder dan een dag vooruit aangemaakt, dan meteen. Dagen tellen in `Europe/Amsterdam`, zodat de
    melding op dezelfde kloktijd valt, ook over een wissel van zomer- en wintertijd. De scheduler verstuurt de
    geplande meldingen; de melding "meteen" verstuurt `CreateRound` zelf, zodat die ook zonder cron aankomt. De ronde
    onthoudt dat de melding verstuurd is, dus een loopmaatje dat zich aan- en weer afmeldt, geeft geen tweede;
  - de analist schrijft niets; wat elk lid mag (eigen toestemming, eigen tokens) en wat een loper van de ronde mag,
    geldt voor hem als voor ieder ander. Loopt een analist ook, dan krijgt hij de rol loper erbij: rollen tellen op,
    en elke rol geeft alleen zijn eigen rechten.
- Wie de actie uitvoert (besloten op 15 november 2026):
  - één bron: de token-opslag van Symfony. `ExecutionContext` leest het token; `SymfonyAuthorization` leest via de
    `AuthorizationChecker` hetzelfde token, dus handler en Voters zien altijd dezelfde uitvoerder;
  - `RunAs` zet dat token voor elke ingang zonder HTTP-authenticatie: console en fixtures (kanaal `console`), later
    de web-installer (`web`) en de scheduler. De authenticator van fase 2 zet het voor `api` en `mcp`;
  - de scheduler krijgt een expliciete systeemidentiteit: `RunAs::system()` zet een token met een `SystemUser`
    (rol `ROLE_SYSTEM`, geen lid). Cron en tick krijgen allebei kanaal `cron`. Een verzoek zonder token is nooit
    "systeem": een Voter staat een systeemactie alleen toe bij `ROLE_SYSTEM`;
  - geen automation-account als lid: dat telt overal mee als persoon en kan rollen of tokens krijgen. Een script
    werkt met het persoonlijke API-token van een lid;
  - `DoctrineTransaction` zet de EntityManager terug (`ManagerRegistry::resetManager()`) als de operatie faalt,
    zodat de volgende use case in hetzelfde proces werkt.

### T10. Database en review-focus 1 (test eerst)

- Doctrine-repositories per module in `Infrastructure/Persistence/`, elke query met een integratietest.
- Migraties uit de mapping, nagelopen op beslissingen 4, 13, 18, 19, 22 en 23.
- Schematest: elke UUID-kolom die geen primaire sleutel is heeft een index (beslissing 4).
- Zoeken, op beide databases: "Jose" en "JOSE" vinden "José"; twee leden met dezelfde volledige naam staan allebei
  in de uitkomst; "Anke@Example.org" en "anke@example.org" botsen op de unieke index, net als de weergavenamen
  "Jan H." en "jan h"; twee leden zonder weergavenaam (`NULL`) passen naast elkaar; een `%` in de zoekterm zoekt
  een letterlijk procentteken.
- Review-focus 1, met twee eigen verbindingen die echt committen:
  - **twee lopers melden zich tegelijk aan voor de laatste plek**: er komt er precies één bij, de ander krijgt
    "ronde is vol", en `walkerCount` is gelijk aan het maximum;
  - hetzelfde lid meldt zich tegelijk twee keer aan: één aanmelding, status klopt, geen fout voor het lid;
  - de laatste loper meldt zich af terwijl een ander zich aanmeldt: óf de ronde bestaat met de nieuwe loper, óf
    de aanmelding krijgt "ronde bestaat niet meer"; nooit een stil verloren aanmelding.
- Opzet van die tests: één PHP-proces kan niet op twee verbindingen tegelijk wachten. Verbinding A neemt het slot
  en doet zijn aanmelding zonder te committen; een tweede proces (`proc_open`) meldt aan en wacht op het slot; A
  commit, en dan wordt de uitkomst van B gecontroleerd. Zonder slot of met een niet-vergrendelende telling
  (beslissing 24) komt B er in deze volgorde wel bij, dus de test faalt als een van beide ontbreekt.

### T11. CI: databases

Job "Database (MariaDB 10.11)" en "Database (MySQL 8.4)" in `ci.yml` (matrix, services met healthcheck,
database met `utf8mb4_unicode_ci`): migraties vanaf leeg, `doctrine:schema:validate`, `migrations:migrate
first` en weer omhoog (alle `down()` werken), databasetests met `REQUIRE_DATABASE=1`, elke fixture-set laden
met rooktest. Actions vastgepind op SHA. De twee jobs worden verplichte checks op
`develop`, na akkoord.

### T12. Voorbeelddata (test eerst)

`app:fixtures:load <set>` (alleen `dev` en `test`, `#[When]`), sets `demo`, `test` en `edge`, zoals in
Voorbeelddata. Tests: dezelfde seed geeft dezelfde mensen en hetzelfde patroon; de set `demo`
houdt zich aan hooguit 20 leden, drie met een andere rol dan loper, alleen `example.org`, ruim een kwart van
de rondes zonder loopmaatje en een paar volle rondes; de set `edge` bevat elk geval uit het plan, plus een ronde
op het maximum, een ronde met één plek over, en twee leden met dezelfde volledige naam, elk met een eigen
weergavenaam.

### T13. Conversie `app:import-v3`: naar fase 5b

De tests hieronder zijn het uitgangspunt voor het taakplan van fase 5b. Integratietests op de set `v3`, op
beide databases:

- dry-run: rapport met aantallen per soort en elke waarschuwing uit beslissing 29, en daarna een lege
  v4-database;
- echte run: dezelfde aantallen, auditregels "uit v3", bewaartermijnen toegepast, `--since` neemt minder mee;
- de ronde met acht lopers krijgt `maximumWalkers` 8, met een waarschuwing in de dry-run; de andere rondes krijgen
  minimum en maximum uit de opties, en de instellingen ook;
- `--maximum-walkers` kleiner dan `--minimum-walkers` geeft een fout zonder wijzigingen;
- elk voorbeeldlid met een v3-wachtwoord: `VerifyPassword` slaagt met het v3-wachtwoord en faalt met een ander;
- een tweede conversie naar een lege database geeft hetzelfde (beslissing 28);
- een niet-lege v4-database en een afwijkend v3-schema geven een duidelijke fout zonder wijzigingen.

### T14. Opengrep-regels

Per regel eerst het testbestand, dan de regel (beslissing 32).

### T15. ADR 0005 en documentatie

- `docs/adr/0005-database-approach.md`: beslissingen 4 (UUID-verwijzingen met index, geen foreign keys tussen
  modules), 13 en 17 tot en met 25: de zoeksleutel uit PHP in plaats van de collation, bij leden e-mailadres en
  weergavenaam uniek en de volledige naam niet, het slot met vergrendelende telling voor de laatste plek, en waarom geen spatial types en geen
  JSON-kolommen.
- `docs/architecture.md`: de uitbreiding van regel 1 en 2, `Shared\Domain` met zijn interfaces, en de module-
  grens in de database (beslissing 4).
- `docs/testing.md`: groep `database`, DAMA, de gelijktijdigheidstest, de matrix- en bewakingstest, en hoe je
  lokaal de database start voor tests.
- `docs/getting-started.md`: fixtures laden;
  `docs/gotchas.md`: wat er tijdens deze fase opvalt.
- `docs/glossary.md`: kanaal `console`, `LookupKey` (zoeksleutel), `walkersNeeded`, "verwijderd lid". `maximumWalkers`, `openSpots` en `displayName` staan er al in, net als in het
  Domeinmodel van `docs/plan-v4.md`.
- `docs/gotchas.md`: de momentopname van InnoDB bij `REPEATABLE READ` (beslissing 24).
- `AGENTS.md` (Commands) en `CHANGELOG.md`; in `docs/plan-v4.md` de punten van Fase 1 afvinken.

### T16. Verifiëren en opleveren

- Lokaal `composer check` met de database aan; uitvoer in het verslag, en welke check niet draaide.
- Push en pull request naar `develop` na akkoord; CI groen (alle jobs); merge na akkoord.

## Afspraken

1. Claude commit in deze privérepo, in kleine commits. Push, pull request, merge en instellingen op GitHub
   alleen na akkoord.
2. De database start de ontwikkelaar (`docker compose up -d --wait`, container `database`, poort door Docker
   gekozen; `docker compose port database 3306` toont hem). Draait hij niet, dan meldt Claude dat en draait de
   databasetests niet.
3. Nieuwe afhankelijkheden pas na akkoord op de tabel hierboven; ze vallen onder de Composer-cooldown van 24 uur.

## Opgemerkt, buiten deze fase

- Voorbeelddata: het plan zegt "nooit in de release-zip" en ook "een proefinstallatie kan in de web-installer
  voorbeelddata laden". Dat kan niet allebei zolang fixtures alleen in `dev` bestaan; dat hoort bij fase 6a.
- De hashketen in het auditlog (beslissing 16) en de bewaartermijn-opruiming door de scheduler (fase 6b) staan
  open.
