# Fase 3: taakplan

Status: goedgekeurd op 16 november 2026.

Bron: `docs/plans/plan.md` (Samenvatting, Branding en configuratie, Privacy en AVG, Auditlog, Fase 3, Risico's en
review-focus), `docs/plans/phase-2.md` (beslissingen en Open punten uit het plan), `docs/plans/phase-1.md`
(beslissingen 15 en 16), `docs/architecture.md`, `docs/testing.md`, `docs/configuration.md`, ADR 0001 tot en met 0006,
`AGENTS.md`, de e-mails in `../buurtpreventie-legacy/templates/emails/`, en de code op `develop` (`97a913b`).

## Doel en klaar-criterium

Een vereniging richt de app zelf in: naam, logo, app-icoon, kleuren, contact en teksten via `/admin/settings` en
de upload van afbeeldingen, en de app gebruikt die inrichting in `GET /config`, het manifest en elke e-mail. E-mails
gaan opgemaakt en in tekst, in `nl` of `en`, en pas na de commit. Het auditlog krijgt een hashketen die geknoei
in de database zichtbaar maakt.

Klaar als:

- een tweede vereniging alleen via `PATCH /admin/settings` en de upload van logo en icoon een eigen naam, logo,
  kleuren en teksten krijgt, zonder codewijziging, als functionele test en met de hand tegen de demo;
- `GET /config` en `manifest.webmanifest` die inrichting tonen, en een kleur zonder voldoende contrast bij opslaan
  geweigerd wordt;
- elk bericht uit de code (zes typen, vastgesteld 2) een e-mail heeft, in HTML en tekst, in `nl` en `en`, zonder
  telefoonnummer, adres of notitie;
- een test bewijst dat een teruggedraaide wijziging niets verstuurt;
- review-focus 5 een test heeft: met een basis-URL in een pad bevatten links in mails en het manifest dat pad, en
  nooit `/public/`;
- `app:audit:verify` een intacte keten meldt, en een gewijzigde, verwijderde of tussengevoegde regel aanwijst; wissen
  en opruimen breken de keten niet; twee gelijktijdige schrijfacties op MariaDB en MySQL geven een kloppende keten;
- de schakelaar voor API-tokens elk token meteen weigert;
- elke nieuwe schrijvende handler een auditregel, een Voter en rijen in beide matrixtests heeft;
- `symfony composer check` groen is, `composer test` zonder database ook, en de pull request naar `develop` groen en
  gemergd is.

## Vastgesteld in de code

1. **`Setting` is een paar naam en tekst** (`Settings\Domain\Setting`, `VARCHAR(100)` en `TEXT`). Er zijn twee
   namen in gebruik, `patrol.minimum_walkers` en `patrol.maximum_walkers`, gelezen door `GetStaffingDefaults`, een
   publieke `#[ReadAction]` zonder Voter. Er is geen use case die een instelling schrijft. `SettingRepository` kent
   alleen `find()` en `add()`.
2. **Zes soorten berichten**, niet de vijf uit het plan: `account.login_code`, `account.invitation`,
   `round.reminder`, `round.buddy_needed`, `round.walker_withdrawn` en `round.cancelled` (van `DeleteRound`). Een
   `Notification` draagt alleen een type, een lid-id en ids als parameters; de tekst bepaalt de notifier.
3. **Elke `send()` staat binnen `Transaction::run()`**: in `IssueInvitation`, `RequestLoginCode`, `DeleteRound`,
   `WithdrawFromRound`, `WithdrawMemberFromUpcomingRounds`, `SendReminders` en via `BuddyCaller` in `CreateRound` en
   `NotifyBuddyNeeded`. Een mailfout draait zo de wijziging terug, en een teruggedraaide wijziging kan al een mail
   verstuurd hebben.
4. **`DoctrineTransaction` kent geen moment na de commit.** Hij nest met een teller en `wrapInTransaction()`; de
   testfake `ImmediateTransaction` voert alleen uit.
5. **`MailNotifier` mailt alleen inlogcode en uitnodiging**, als platte tekst met vaste taal `nl` uit
   `translations/mail.nl.yaml`, link `DEFAULT_URI` plus `/app/#/welcome/…`; de andere vier gaan naar de
   `LoggingNotifier`. Hij staat in `Shared\Infrastructure\Notification`. Regel 5 staat Twig alleen toe in
   `<Module>\Infrastructure\Mail` en de installer, en `Shared\Infrastructure` kent geen module (regel 4), dus de
   notifier kan geen ronde opzoeken.
6. **Er is geen Twig** in `composer.json`, geen `templates/` en geen `public/` buiten `index.php` en `bundles/`.
7. **Drie repositories flushen midden in een transactie** (`DoctrineMeetingPointRepository`,
   `DoctrineOutcomeRepository`, `DoctrineMemberRepository`, voor unieke namen), dus een auditregel die eerder in
   dezelfde transactie is vastgelegd, staat dan al in de database.
8. **`AuditEntry` heeft geen volgorde-kolom**: id UUIDv7, tijdstip in hele seconden, gewijzigde velden als
   JSON-tekst. `eraseActorName()` wist alleen de naam van de uitvoerder; de gewijzigde velden blijven.
   `member.added` legt de weergavenaam vast in die velden, dus na wissen staat de weergavenaam van een gewist lid
   nog in het log. Contactgegevens staan er niet in: `member.changed` en `member.contact_details_changed` leggen
   voor telefoon, adres, naam en e-mail alleen `null → null` vast.
9. **Elke auditregel gaat via `Transaction`**, ook inloggen en een mislukte inlog (`LoginRecorder`).
10. **De herinnering gaat vast over morgen** (`SendRemindersHandler`: `LocalDay::of($now)->next()`); het plan wil
    N dagen vooraf als instelling.
11. **De tijdzone ligt vast** in `LocalTime::ZONE` (`Europe/Amsterdam`) en wordt overal gebruikt: dagdelen,
    kalenderdagen, herinneringen, oproepen voor een loopmaatje, `ListingPeriod`.
12. **De taal van de API komt uit `Accept-Language`** (standaard `nl`); `MailNotifier` heeft een vaste `nl`.
13. **De tokenauthenticator kent geen schakelaar**: hij weigert alleen om redenen van het token zelf
    (`ApiToken::refusalAt()`) of een misvormde header.
14. **`DEFAULT_URI` is de basis van elke link** buiten een verzoek (`config/services.yaml`, standaard
    `http://localhost`); `docs/configuration.md` zegt "zonder `/public`", maar niets controleert dat.
15. **De e-mails van v3** staan in `templates/emails/` (herinnering, wachtwoord-reset, welkom), elk in HTML en tekst.
    De herinnering noemt de namen van de andere lopers. `translations/` van v3 heeft alleen `messages` en
    `validators`.
16. **`gd`, `fileinfo` en `sodium` zijn lokaal geladen**; `composer.json` vraagt alleen `ctype`, `iconv`, `intl` en
    `mbstring`.

## Voorgestelde beslissingen

### Instellingen

1. **Eén catalogus van instellingen in `Settings\Domain`**: een enum `SettingName` met per naam het type (tekst,
   getal, kleur, aan/uit, URL, e-mailadres, telefoon), de standaardwaarde, de regels en of hij publiek is. De tabel
   blijft naam en tekst (vastgesteld 1), zodat een nieuwe instelling geen migratie vraagt. Een onbekende naam bestaat
   niet: de catalogus is de enige bron.

   | Instelling | Standaard | Publiek in `/config` |
   | --- | --- | --- |
   | `association.name`, `association.short_name` | "Buurtpreventie", "Buurtpreventie" | ja |
   | `brand.logo`, `brand.icon` (bestandsnaam, beslissing 8) | geen logo; neutraal icoon uit de repo | ja |
   | `brand.primary`, `brand.accent`, `brand.primary_dark`, `brand.accent_dark` | neutrale kleuren die de contrastregels halen | ja |
   | `contact.name`, `contact.email`, `contact.phone` | leeg | alleen ingelogd (beslissing 4) |
   | `community.group_url` (beslissing 4a) | leeg | alleen ingelogd |
   | `mail.sender_name` | `association.name` | nee |
   | `text.welcome.<taal>`, `text.emergency.<taal>` | neutrale tekst in `nl` en `en` | ja |
   | `text.privacy_url`, `text.accessibility_url` | leeg | ja |
   | `patrol.minimum_walkers`, `patrol.maximum_walkers` | 2 en 7 (bestaand) | ja |
   | `patrol.reminder_days_ahead` | 1, van 1 tot en met 7 | nee |
   | `app.language` | `nl` | ja |
   | `integrations.api_tokens` | aan | nee |

2. **De tijdzone wordt geen instelling.** Afwijking van het plan: `Europe/Amsterdam` zit in elke regel over
   dagdelen en kalenderdagen (vastgesteld 11), en elke vereniging zit in Nederland. Een instelling vraagt dat elke
   regel de zone als invoer krijgt, met tests rond de tijdwissel per zone, voor geen enkele gebruiker. Alternatief:
   instelbaar, met die prijs.
3. **Teksten zijn platte tekst, geen Markdown.** Afwijking van het plan: een lege regel is een nieuwe alinea, en de
   frontend maakt van een URL, e-mailadres of telefoonnummer een link. Markdown vraagt een parser in PHP (voor de
   mail) én in de frontend, of HTML uit de API die de frontend ongezien in de pagina zet; beide zijn een
   afhankelijkheid of een XSS-risico voor wat een welkomsttekst en een spoedtekst nodig hebben. Alternatief:
   `league/commonmark` met `html_input: strip`, en de API levert de HTML.
4. **Contact van de coördinator alleen voor ingelogde leden.** `/config` is publiek, ook voor zoekmachines; een
   naam, telefoonnummer en e-mailadres daar is een persoonsgegeven op internet. `GET /config` geeft `contact` met
   vaste sleutels, `null` zonder sessie of token (zoals T23 van fase 2). Het plan noemt "contact coördinator op elke
   pagina": dat zijn de pagina's na inloggen, en het inlogscherm toont de spoedtekst.
4a. **Een link naar de groep van de vereniging in WhatsApp of Signal** (besloten op 16 november 2026), voor de
    buurtsignalen die het plan bewust buiten de app houdt (Spoedmeldingen). Eén instelling `community.group_url`,
    leeg of een uitnodigingslink:
    - alleen `https://chat.whatsapp.com/<code>` of `https://signal.group/#<code>`; een andere host of `http` geeft
      een veldfout. `/config` geeft de link met `service` (`whatsapp` of `signal`, afgeleid uit de host), zodat de
      frontend het juiste woord en icoon toont;
    - **alleen voor ingelogde leden**, zoals het contact (beslissing 4): wie de link heeft, kan de groep in, en dan
      zien alle leden van de groep zijn telefoonnummer. Daarom ook niet in een mail, die wordt doorgestuurd;
    - de auditregel noemt de wijziging als `null → null`, zodat de link niet in het log staat;
    - een vereniging die de link vervangt (omdat hij gelekt is), trekt de oude in de app van WhatsApp of Signal in;
      de app kan dat niet. De beheerhandleiding van fase 6b zegt het.
    Eén groep, geen twee: een vereniging met beide kiest er een, zodat lopers niet op twee plekken hoeven te kijken.
    Alternatief: een vrije URL met een vrije naam, zonder controle op de host.
5. **De privacyverklaring en de toegankelijkheidsverklaring zijn een URL**, geen tekst in de app (besloten op
   16 november 2026). Fase 6b doet een voorstel voor een standaardtekst van beide, in `docs/privacy/`, die de
   vereniging op haar eigen site zet. Zo bestaat er één versie van de verklaring, en houdt de app geen juridische
   tekst bij.
6. **Vertalingen overschrijven per vereniging valt af.** Het plan wil losse teksten laten overschrijven zonder
   vertaalbestanden; de teksten die een vereniging wil aanpassen, zijn de instellingen hierboven. Een vrije
   overschrijving van elke vertaalsleutel is een tweede bron voor elke tekst, die bij een update stil achterloopt.
7. **Wijzigen in één `PATCH /admin/settings`** (JSON Merge Patch, T19 van fase 2): een weggelaten instelling blijft,
   `null` zet de standaardwaarde terug, een waarde vervangt. `GET /admin/settings` toont elke instelling met haar
   waarde en of het de standaard is. Alleen de beheerder (`settings.change`, `SettingsPolicy`). Eén auditregel
   `setting.changed` per verzoek, met per naam oud → nieuw; een tekst als `null → null`, zoals contactgegevens, zodat
   lange teksten het log niet vullen. Fouten per veld als 422 `invalid_input`.

### Logo en icoon

8. **Twee uploads: logo en app-icoon.** Afwijking van het plan (drie): de favicon komt uit het icoon.
   `PUT /admin/settings/images/logo` en `/icon` met het bestand als body, geen multipart; `DELETE` zet de standaard
   terug. Voorwaarden:

   | Voorwaarde | Logo | Icoon |
   | --- | --- | --- |
   | Formaat | PNG of JPEG | PNG of JPEG |
   | Bestandsgrootte | hooguit 2 MB | hooguit 2 MB |
   | Afmetingen | hooguit 2048 × 2048, elke verhouding | vierkant, van 512 × 512 tot 2048 × 2048 |
   | Wat de app opslaat | PNG binnen 512 × 512 (frontend) en 128 px hoog (mail) | PNG van 512, 192, 180 (Apple) en 32 (favicon) |

   - **Het formaat volgt uit de inhoud**, met `getimagesize()` en `finfo`. Extensie, bestandsnaam en het type dat
     de client noemt tellen niet: een `.png` die een JPEG is, gaat door als JPEG; een `.png` die geen beeld is,
     wordt geweigerd.
   - **Geweigerd:** SVG (kan script bevatten), GIF (animatie, en geen meerwaarde boven PNG), HEIC (GD kan het niet
     lezen; een iPhone deelt een foto als JPEG), WebP en AVIF (GD leest ze alleen als het ermee gebouwd is, en dat
     verschilt per host). Elke weigering is 422 `unsupported_image` met de voorwaarde die niet klopt.
   - **De grenzen gelden vóór het decoderen**: GD zet een beeld volledig in het geheugen, en 2048² is al 16 MB.
   - **Opnieuw opslaan met GD** haalt metadata (zoals de GPS-plek van een foto) en alles wat geen beeld is eruit,
     ook een bestand dat tegelijk iets anders is. Transparantie in een PNG blijft.

   Alternatief zonder GD: de beheerder levert elke maat zelf aan, en de app slaat alleen op wat hij controleerde.
9. **`ext-gd` wordt een eis** in `composer.json`, naast `ext-fileinfo`; de installer van fase 6a controleert het.
   Op shared hosting staat GD vrijwel altijd aan.
10. **Opslag buiten de webmap**, in `UPLOAD_DIR` (nieuwe variabele, standaard `var/uploads`; fase 6a zet hem op
    `shared/uploads`). De bestandsnaam is soort, maat en 16 hex-tekens van de SHA-256 van de inhoud
    (`icon-192-3f2a….png`), dus een nieuwe versie heeft een nieuwe naam en mag een jaar in de cache.
    `GET /api/v1/media/brand/{bestand}` geeft alleen een bestand dat een instelling noemt; een naam die niet op het
    patroon past, is 404 vóór er een pad gemaakt wordt. Een oud bestand wordt na de commit verwijderd.

### Kleuren en contrast

11. **Vier kleuren: primair en accent, voor licht en donker.** Eén kleur kan niet tegelijk 4,5 : 1 halen tegen wit
    en tegen een donkere achtergrond (tegen wit vraagt een relatieve luminantie van hooguit 0,18, tegen `#121212`
    minstens 0,20). Regels bij opslaan, met de vaste achtergronden van de frontend (wit en `#121212`):
    - primair: 4,5 : 1 tegen de achtergrond van zijn modus (tekst, links en knoppen met tekst in de
      achtergrondkleur);
    - accent: 3 : 1 tegen de achtergrond (randen en focus, WCAG 1.4.11);
    - een kleur als `#rrggbb`; een te zwakke kleur geeft 422 `insufficient_contrast` met de gemeten en de vereiste
      verhouding, zodat het beheerscherm het kan uitleggen.
    Fase 5 gebruikt deze achtergronden; `docs/architecture.md` legt dat vast. Alternatief: één kleur per rol, en de
    frontend rekent een donkere variant uit; dan is het resultaat niet gecontroleerd bij opslaan.

### Publieke configuratie en manifest

12. **`GET /api/v1/config`** (publiek): naam, korte naam, kleuren, URL's van logo en iconen, taal, teksten in de taal
    van de vereniging, privacy- en toegankelijkheids-URL, minimum en maximum lopers, en `contact` en `group`
    (beslissingen 4 en 4a).
    Eén use case `GetPublicConfig` in Settings, die `GetStaffingDefaults` vervangt als bron voor de frontend.
13. **`GET /api/v1/manifest.webmanifest`** (publiek, `application/manifest+json`): naam, korte naam, `theme_color`
    (primair licht), `background_color` (wit), `display: standalone`, iconen 192 en 512, en `start_url` en `scope`
    op `<pad van de basis-URL>/app/`. Onder `/api/v1`, zodat de frontend alleen de API aanroept; `start_url` en
    `scope` volgen de basis-URL, niet de plek van het manifest.
14. **De basis-URL wordt een waardeobject `AppUrl`** in `Shared\Domain`, uit `DEFAULT_URI`, zonder slash aan het
    eind. Elke link in een mail en het manifest komt uit `AppUrl::to('app/#/…')`, zodat een pad in de basis-URL
    overal meegaat (review-focus 5). Geen controle op `/public` bij het starten (besloten op 16 november 2026): de
    app zet zelf nooit `/public` in een link, en een verkeerde `DEFAULT_URI` is een fout in de installatie, die de
    installer van fase 6a voorkomt door de waarde zelf te schrijven uit het adres waarop hij draait.

### E-mail

15. **Berichten gaan pas na de commit uit.** `DoctrineTransaction` krijgt `afterCommit(callable)`: wat binnen een
    transactie wordt aangemeld, wacht tot de buitenste transactie commit, en vervalt bij een rollback. De notifier
    van de app (`AfterCommitNotifier`) zet elk bericht zo in de wachtrij; buiten een transactie verstuurt hij
    meteen. Een fout bij het versturen na de commit gaat naar het log en breekt het verzoek niet: de wijziging staat
    al (besloten op 16 november 2026). `ImmediateTransaction` in de tests krijgt dezelfde semantiek.
    Alternatief: een outbox-tabel met de berichten in dezelfde transactie en versturen door de scheduler. Dat is
    betrouwbaarder (geen mail kwijt bij een mailfout), maar een inlogcode moet binnen seconden aankomen, en zonder
    cron komt de scheduler pas met een tick. Gevolg om te accepteren: een herinnering die na de commit faalt, staat
    als verstuurd (`remindedAt`) en gaat niet opnieuw; het log toont de fout, en Onderhoud (fase 6b) de laatste.
16. **Twig voor de e-mails, per module.** Afwijking van vastgesteld 5: elke module bouwt de mails van haar eigen
    berichten in `<Module>\Infrastructure\Mail` (regel 5): Identity de inlogcode en uitnodiging, Patrol de vier
    over rondes, met de ronde uit de eigen repository. Een interface `MailComposer` in `Shared\Infrastructure\Mail`
    (type → onderwerp, HTML en tekst); `MailNotifier` kiest de composer op type en verstuurt. Een type zonder
    composer is een fout, met een test die elk type uit `src/` naast een composer legt.
17. **Branding in de mail via `Shared\Domain\Branding`**, geïmplementeerd door Settings: naam, kleuren, logo,
    contact, taal en de naam van de afzender. Het afzenderadres blijft `MAILER_FROM`: alleen een adres dat de
    mailserver mag gebruiken komt door SPF en DKIM, en dat beslist de beheerder van de server, niet een
    instelling. `Reply-To` wordt het contactadres als dat er is. Afwijking van het plan ("afzender e-mail" als
    instelling).
18. **Elke mail in HTML en tekst**, uit één Twig-layout met stijlen inline, zonder CSS-inliner (een afhankelijkheid
    voor een handvol regels). Het logo gaat als ingesloten bijlage (`cid:`), niet als externe afbeelding, zodat
    geen mailprogramma iets ophaalt bij de server; zonder logo staat de naam er als tekst. Alles wat het logo zegt,
    staat ook in tekst.
19. **Zo min mogelijk persoonsgegevens.** Een mail noemt datum, tijd, dagdeel, startpunt en hoeveel anderen meelopen
    ("Er lopen 2 anderen mee", of "Je loopt nog alleen"), en nooit een naam of weergavenaam van een andere loper
    (afwijking van v3, vastgesteld 15; besloten op 16 november 2026), een telefoonnummer, een adres of een notitie.
    Wie meeloopt, staat in de app; elke mail over een ronde heeft een knop naar die ronde. Een test zoekt in elke mail naar de
    telefoon, het adres en het e-mailadres van de leden uit de opzet en eist dat ze er niet in staan
    (review-focus 3).
20. **Taal van de mail is `app.language`**, datums met `IntlDateFormatter` in `Europe/Amsterdam`, voluit
    ("donderdag 24 oktober om 20:00"). Leden hebben geen eigen taal; dat volgt als iemand erom vraagt.
21. **De mail "zoekt een loopmaatje"** zegt waarom het lid hem krijgt en waar het dat uitzet (Account in de app), met
    een link. Een `List-Unsubscribe` met één klik vraagt een ondertekend endpoint zonder sessie; dat valt erbuiten.
22. **Herinnering N dagen vooraf** (`patrol.reminder_days_ahead`): `SendReminders` leest de instelling via een use
    case van Settings en kiest de kalenderdag `vandaag + N`. Standaard 1, zoals nu.

### Schakelaar voor API-tokens

23. **`integrations.api_tokens` aan of uit**, de schakelaar uit Datalek en Beveiligingsupdates. Uit: de
    tokenauthenticator weigert elk token met reden `tokens_disabled` in het log (401 zonder body, zoals elke
    weigering), `POST /me/tokens` geeft 409 `api_tokens_disabled`, en bekijken en intrekken van tokens werkt gewoon,
    want intrekken hoort bij de stappen na een lek. MCP gebruikt dezelfde tokens, dus de schakelaar zet fase 4 mee
    uit; fase 4 krijgt daarnaast `integrations.mcp`, standaard uit. De authenticator leest de instelling via een use
    case van Settings, bij elk verzoek met een token (één query).

### Hashketen in het auditlog

Volgens Fase 3 in het plan (besloten op 16 november 2026); hieronder wat het plan open laat.

24. **De regels wachten tot het einde van de buitenste transactie.** `DoctrineAuditTrail` houdt de regels van een
    transactie in het geheugen. Vlak vóór de commit (een `beforeCommit`-stap van `DoctrineTransaction`, net als
    beslissing 15) neemt de keten het slot op de kop, geeft elke regel een volgnummer en hash, en schrijft ze weg.
    Zo is het slot altijd de laatste stap (plan), ook als een repository halverwege flusht (vastgesteld 7). Een
    rollback gooit de wachtende regels weg.
25. **Kolommen en tabel.** `audit_entry` krijgt `chain_position` (`BIGINT`, uniek), `changes_hash` (`BINARY(32)`,
    leeg zonder gewijzigde velden) en `chain_hash` (`BINARY(32)`). Een nieuwe tabel `audit_chain` met twee rijen:
    `head` (laatste volgnummer en hash, de rij met het slot) en `anchor` (volgnummer en hash van de laatst
    opgeruimde regel, of 0 en 32 nulbytes).
26. **Wat de hash dekt.** `chain_hash` = HMAC-SHA-256 met `AUDIT_SECRET` over een vaste JSON-lijst: versie `1`,
    volgnummer, id, tijdstip in UTC (`Y-m-d\TH:i:s\Z`), lid-id of `null`, actie, soort en id van het onderwerp,
    kanaal, `changes_hash` en de `chain_hash` van de vorige regel. Het volgnummer erin maakt verwisselen zichtbaar.
    `changes_hash` = HMAC-SHA-256 met dezelfde sleutel over id en de JSON van de gewijzigde velden. Een HMAC, geen
    gewone SHA-256: na wissen blijft de hash staan, en een gewone hash van `{"displayName":…}` is met een lijst namen
    terug te rekenen. Zo blijft de gewiste inhoud ook voor wie de database heeft onvindbaar.
27. **Wissen haalt ook de gewijzigde velden weg** (vastgesteld 8): `EraseActorName` wist de naam van de uitvoerder
    (zoals nu) en de gewijzigde velden van elke regel over het gewiste lid (onderwerp `member`, dat lid). Dat
    sluit het gat dat de weergavenaam na wissen in `member.added` blijft staan. `changes_hash` blijft, de keten
    klopt.
28. **`AUDIT_SECRET`** in `shared/.env.local` (lokaal `.env.local`, in `test` een publieke waarde in `.env.test`,
    in de databasejobs van CI per run gemaakt, zoals `APP_SECRET`). Zonder sleutel start het schrijven van een
    auditregel niet: geen stille regels zonder hash. Een verloren sleutel maakt de keten oncontroleerbaar, dus
    `docs/operations.md` zet hem bij de back-up. Een sleutel vervangen kan niet in deze fase (open punt).
29. **Bestaande regels** (alleen in ontwikkel- en testdatabases; productie heeft er nog geen) blijven buiten de keten:
    de migratie nummert ze op tijdstip en id en zet het anker erachter, zonder hash. `app:audit:verify` meldt hun
    aantal apart.
30. **Opruimen** (de scheduler van fase 6a) loopt via `AuditEntryRepository::pruneThrough(int $position)`: het anker
    krijgt volgnummer en hash van de laatste verwijderde regel, in dezelfde transactie. Deze fase bouwt de methode
    met test; de bewaartermijnen en de planning volgen in 6a.
31. **Controle: `app:audit:verify`** (use case `VerifyAuditChain` in Audit, alleen voor het systeem en de console)
    leest vanaf het anker per 500 op volgnummer en meldt "intact tot en met regel N (tijdstip)" of de eerste regel
    die niet klopt, met de reden: inhoud gewijzigd, regel ontbreekt (gat in de nummering), regel tussengevoegd of
    vorige hash klopt niet, kop wijst niet naar de laatste regel. Een regel met gewiste inhoud telt als intact.
    Exitcode 0 of 1. Onderhoud (fase 6b) toont hetzelfde.
32. **Grens, eerlijk in `docs/`:** wie de database kan wijzigen, kan de nieuwste regels weghalen en de kop terugzetten
    naar een eerdere regel die hij kent; dat is zonder kopie buiten de database niet te zien. `app:audit:verify`
    toont daarom het laatste volgnummer, zodat een beheerder het kan noteren.

### Testen

33. **In memory waar het kan**: catalogus, contrast, `AppUrl`, beeldbewerking (met kleine PNG's en JPEG's in
    `tests/`), mails (gerenderd met de echte Twig-omgeving, met een in-memory mailer), keten (met een in-memory
    repository). **Databasetests** voor de keten (gelijktijdig, op MariaDB en MySQL), het slot en de migratie van
    bestaande regels, en voor "teruggedraaid verstuurt niets" met de echte `DoctrineTransaction`.
34. **Matrixtests**: `settings.change`, het lezen van `/admin/settings` en de uploads in `AuthorizationMatrixTest` en
    `HttpMatrixTest`; `/config`, het manifest en `/media/brand/…` als publieke rijen. De Postman-collectie krijgt de
    nieuwe endpoints.

### Opengrep-regels voor deze fase

35. Elke regel met een regeltest:
    - **`client-file-metadata`**: `getClientOriginalName()`, `getClientOriginalExtension()`,
      `getClientMimeType()` en `guessClientExtension()`; de client bepaalt naam en type van een upload niet;
    - **`twig-autoescape-disabled`**: `{% autoescape false %}` in een template en `autoescape: false` in de
      configuratie;
    - **`mailer-outside-notifier`**: `MailerInterface::send()` buiten `Shared\Infrastructure\Notification`, zodat
      geen mail om de wachtrij na de commit heen gaat.

## Nieuwe afhankelijkheden (goedgekeurd op 16 november 2026)

Versies en datums van Packagist op 16 november 2026. De Composer-cooldown van 24 uur geldt.

| Pakket | Waarvoor | Licentie | Soort | Releaseritme | Nu |
| --- | --- | --- | --- | --- | --- |
| `symfony/twig-bundle` (+ `symfony/twig-bridge`) | Twig in Symfony, voor de e-mails (beslissing 16) | MIT | runtime | Symfony 7.4 LTS, patch ongeveer maandelijks | v7.4.21 (9 okt), bridge v7.4.22 (15 okt) |
| `twig/twig` (via de bundle) | Templates met automatisch escapen | BSD-3-Clause | runtime | Minor elke paar maanden; 4.0 staat in alpha, de 3-lijn krijgt releases | v3.30.0 (25 sep) |
| `ext-gd` (PHP-extensie, geen pakket) | Logo en icoon controleren, verkleinen en opnieuw opslaan (beslissing 8) | PHP | platform | Met PHP | lokaal geladen |

`symfony/mime` (al runtime via `symfony/mailer`) en `ext-fileinfo` (standaard in PHP) komen niet nieuw mee.
`SymfonyReleaseLineTest` dekt de nieuwe Symfony-pakketten. Alternatief zonder Twig: mails opbouwen in PHP met
`htmlspecialchars()` per waarde. Dat is geen afhankelijkheid, maar elke vergeten escape is HTML-injectie in een
mail, en het plan en regel 5 kiezen Twig.

## Taken

Elke taak is één of enkele kleine commits; tests komen vóór de code.

### T1. Branch en taakplan

`feature/phase-3` vanaf `develop`; dit plan. Bouwen pas na akkoord.

### T2. Afhankelijkheden en configuratie

- `composer require symfony/twig-bundle`, `ext-gd` en `ext-fileinfo` in `require`; recept nalopen
  (`config/packages/twig.yaml` met `strict_variables`, `templates/` alleen voor mail), receptbestanden ongewijzigd.
- `UPLOAD_DIR` en `AUDIT_SECRET` in `config/services.yaml` en `docs/configuration.md`; `AUDIT_SECRET` in
  `.env.test`; de databasejobs van CI maken hem per run.
- Controleren dat de CI-image GD heeft (`setup-php` met `extensions`).
- Test eerst: `SymfonyReleaseLineTest` dekt de nieuwe pakketten; `composer deprecations` blijft schoon.
- Uitgevoerd op 16 november 2026, met deze keuzes:
  - geïnstalleerd: `symfony/twig-bundle` v7.4.21, `symfony/twig-bridge` v7.4.22 en `twig/twig` v3.30.0;
    `ext-gd` en `ext-fileinfo` staan in `require`;
  - `SymfonyReleaseLineTest` dekt elk `symfony/`-pakket al op naam, dus de nieuwe vallen er vanzelf onder; hij
    bleef groen zonder wijziging;
  - `config/packages/twig.yaml` zet `strict_variables` in elke omgeving, niet alleen in `test`: een ontbrekende
    variabele laat een mail falen in plaats van hem met een gat te versturen. `templates/base.html.twig` uit het
    recept is weg: een pagina-layout met scripts van een CDN, en de backend rendert geen pagina's (regel 5);
  - `gd` en `fileinfo` staan bij elke `setup-php` in `ci.yml` en `weekly.yml`, en in de eisen van
    `docs/getting-started.md` en `docs/operations.md`;
  - `AUDIT_SECRET` en `UPLOAD_DIR` komen in de taak die ze gebruikt (T11 en T6), zodat config en docs geen
    variabele noemen die nog niets doet.

### T3. Berichten na de commit (test eerst)

- Tests: binnen een transactie gaat er niets uit tot de commit; een rollback verstuurt niets (in memory én als
  databasetest met `DoctrineTransaction`); een geneste transactie wacht op de buitenste; buiten een transactie gaat
  een bericht meteen; een fout van de mailer na de commit komt in het log en geeft geen fout aan de aanroeper.
- `DoctrineTransaction::afterCommit()` en `beforeCommit()` (beslissingen 15 en 24), `AfterCommitNotifier`,
  `ImmediateTransaction` met dezelfde volgorde.
- Uitgevoerd op 16 november 2026, met deze keuzes:
  - `afterCommit()` staat op `Shared\Domain\Transaction`, zodat de fake en de echte transactie hetzelfde contract
    hebben; `beforeCommit()` komt in T11, met de keten die het gebruikt;
  - een mislukte geneste run laat de buitenste altijd mislukken (Doctrine sluit de entity manager), dus de wachtrij
    hoort bij de buitenste run en vervalt als die faalt. Een aparte wachtrij per geneste run zou code zijn voor een
    situatie die niet kan bestaan;
  - `AfterCommitNotifier` logt een mislukte levering als `error` met type, lid-id en de exceptie, en levert de
    volgende berichten gewoon. De melding van de exceptie kan een e-mailadres bevatten (een SMTP-weigering noemt de
    ontvanger); dat blijft in het serverlog, dat alleen de beheerder van de server ziet (geaccepteerd op 16 november
    2026);
  - `services.yaml`: `Notifier` is `AfterCommitNotifier`, die levert via `MailNotifier`;
  - tests: `DoctrineTransactionTest` (na de buitenste commit, vervalt bij een fout, meteen buiten een run),
    `AfterCommitNotifierTest` (in memory, ook de mislukte levering) en `AfterCommitDeliveryTest` (databasetest met
    de bedrading van de app: mail na de commit, niets bij een rollback).

### T4. Settings-catalogus en wijzigen (test eerst)

- `SettingName` met typen, standaarden en regels (beslissing 1); `ContrastRatio` en de regels van beslissing 11.
- Use cases: `GetSettings` (beheerder), `ChangeSettings` (`PATCH /admin/settings`, beslissing 7), `GetPublicConfig`
  (beslissing 12), `GetReminderLeadTime`, `GetApiTokenAccess`.
- Tests: elke instelling weigert een verkeerde waarde met een veldfout; `null` zet de standaard terug; minimum en
  maximum samen (maximum ≥ minimum, ook als maar één van beide verandert); de groepslink neemt alleen de twee hosts
  van beslissing 4a over `https`; elke standaardkleur haalt de regels; een
  te zwakke kleur geeft 422 `insufficient_contrast` met de verhoudingen; contrast tegen bekende paren uit WCAG; de
  auditregel noemt alleen wat veranderde, teksten als `null → null`.
- Een gewijzigd minimum geldt alleen voor nieuwe rondes (bestaande test van fase 1 blijft groen).

### T5. Basis-URL (test eerst)

- `AppUrl` (beslissing 14). Tests: subdomein en pad, met en zonder slash aan het eind, geven dezelfde links.
- `MailNotifier` en het manifest gebruiken `AppUrl`; de uitnodigingslink van fase 2 ook.

### T6. Logo en icoon (test eerst)

- `PUT` en `DELETE /admin/settings/images/{logo|icon}`, `GET /media/brand/{bestand}` (beslissingen 8 tot en met 10).
- Tests: PNG en JPEG geven de afgeleide maten als PNG; te groot in bytes of pixels, geen beeld, een GIF, een SVG,
  een WebP en een PNG met iets erachter worden geweigerd of gestript; de GPS-gegevens van een JPEG zijn weg;
  extensie en het type uit de header tellen niet; een niet-vierkant of te
  klein icoon geeft 422; de bestandsnaam volgt de inhoud; een oud bestand verdwijnt na de commit en niet bij een
  rollback; `/media/brand/` geeft alleen een bestand dat een instelling noemt, met `nosniff` en een cache van een
  jaar; `../` of een andere naam is 404.
- Auditregel `setting.image_changed` (soort, oude en nieuwe bestandsnaam).

### T7. `/config` en manifest (test eerst)

- Tests: `/config` zonder sessie heeft `contact` en `group` met `null`-waarden, met sessie of token de waarden, en
  `group.service` volgt de host; teksten in de
  taal van de vereniging; het manifest heeft `start_url` en `scope` op het pad van de basis-URL plus `/app/`,
  zonder `/public/` (review-focus 5), iconen 192 en 512 en het juiste mediatype.
- OpenAPI voor beide; `OpenApiCoverageTest` en de Postman-collectie.

### T8. E-mails (test eerst)

- `MailComposer`, layout en een composer per type (beslissingen 16 tot en met 21), teksten in
  `translations/mail.{nl,en}.yaml`, startpunt de v3-teksten (vastgesteld 15), Nederlands op B1.
- Tests per type: onderwerp, HTML en tekst bestaan in `nl` en `en`; de link naar de ronde gebruikt `AppUrl` met een
  basis in een pad (review-focus 5); geen namen van andere lopers, telefoon, adres of notitie (review-focus 3); de
  inlogcode staat niet in het onderwerp; zonder logo de naam als tekst, met logo een `cid:`-bijlage; `Reply-To`
  alleen met een contactadres; elk type uit `src/` heeft een composer.
- Een nieuwe vertaling heeft een sleutel in beide talen (bestaande dekkingstest uitbreiden).
- Met de hand: elke mail in Mailpit, licht en donker, op een smal scherm.

### T9. Herinnering N dagen vooraf (test eerst)

- `SendReminders` leest `GetReminderLeadTime` (beslissing 22). Tests: 1 en 3 dagen vooraf, en rond de tijdwissel
  (review-focus 2: een ronde om 22:00 blijft 22:00 in de mail).

### T10. Schakelaar voor API-tokens (test eerst)

- Tests: uit weigert een geldig token bij het eerstvolgende verzoek, met reden in het log; uit weigert een nieuw
  token met 409; intrekken en bekijken werken; aan werkt zoals ervoor (beslissing 23).

### T11. Hashketen (test eerst)

- Migratie (alleen toevoegen): kolommen, tabel `audit_chain`, bestaande regels genummerd achter het anker
  (beslissingen 25 en 29); `down()` werkt.
- Keten bij de commit, HMAC over de vaste lijst, wissen van gewijzigde velden, `pruneThrough()`, `VerifyAuditChain`
  en `app:audit:verify` (beslissingen 24 tot en met 31).
- Tests: een gewijzigde actie, tijdstip, uitvoerder, gewijzigd veld of hash wordt aangewezen; een verwijderde en een
  tussengevoegde regel ook; een verwisselde volgorde; wissen breekt de keten niet en laat geen weergavenaam achter;
  opruimen breekt de keten niet; een rollback laat geen volgnummer of regel achter; geen sleutel geeft een fout,
  geen regel zonder hash; twee gelijktijdige schrijfacties (twee processen, zoals review-focus 1) geven aaneengesloten
  nummers en een kloppende keten, op MariaDB en MySQL; een transactie die een ronde vergrendelt en een auditregel
  schrijft, loopt niet vast tegen een tweede.

### T12. Voorbeelddata (test eerst)

- `demo` krijgt de inrichting van "Buurtpreventie Voorbeeldwijk" via `ChangeSettings` (plan, Voorbeelddata), met
  eigen kleuren en een verzonnen logo dat de fixture met GD tekent; `edge` krijgt een korte en een lange naam.
- De rooktest per set blijft groen; na laden meldt `app:audit:verify` een intacte keten.

### T13. Opengrep-regels

Per regel eerst het testbestand, dan de regel (beslissing 35). Lokaal gedraaid zoals in T16 van fase 2.

### T14. ADR 0007 en documentatie

- `docs/adr/0007-audit-hash-chain.md`: beslissingen 24 tot en met 32, met de grens uit 32.
- `docs/architecture.md`: Settings, `Branding`, `AppUrl`, `MailComposer`, berichten na de commit, de keten, de vaste
  achtergronden voor het contrast; `docs/configuration.md`: `UPLOAD_DIR`, `AUDIT_SECRET`, de instellingen en hun
  grenzen (afbeeldingen, contrast, herinnering); `docs/testing.md`: de nieuwe tests en regels;
  `docs/operations.md`: `AUDIT_SECRET` bij de back-up, `app:audit:verify`; `docs/glossary.md`: instelling,
  huisstijl, hashketen, anker; `docs/gotchas.md`: wat opvalt.
- `docs/plans/plan.md`: Fase 3 afvinken, de afwijkingen (beslissingen 2, 3, 5, 6, 8 en 17) bij Branding en
  configuratie, de endpointtabel; `CHANGELOG.md`; `AGENTS.md` (Commands) voor `app:audit:verify`.

### T15. Verifiëren en opleveren

- `composer test` zonder database, `symfony composer test` met database, `symfony composer check`, de Opengrep-tests
  en de scan; uitvoer in het verslag, met elke check die niet draaide.
- Met de hand: inrichting wijzigen en upload via `curl` tegen de demo, `/config` en het manifest lezen, elke mail in
  Mailpit, `app:audit:verify`.
- Push en pull request naar `develop` na akkoord; CI groen (alle jobs); merge als squash na akkoord.

## Afspraken

1. Claude commit in deze privérepo, in kleine commits. Push, pull request en merge alleen na akkoord.
   - Geen `cd`, `pushd`, `-C`, `--git-dir`, `--work-tree` of `GIT_DIR=` in dezelfde aanroep als `git add` of
     `git commit`.
   - Alleen de bestanden van die commit stagen, met letterlijke paden; daarna `git show --stat HEAD`.
   - Merge als squash pas als CI groen is.
2. `.env`, `.env.dev` en `.env.test` alleen lezen met `git show HEAD:<bestand>`; `.env.local`, `.env.*.local` en
   `shared/.env.local` nooit lezen of committen.
3. PHPUnit, `symfony console`, Mago, Rector, PHPStan, Composer, `git push` en `gh` draaien buiten de sandbox; logs
   in de scratchpad.
4. Database, Mailpit en server start de ontwikkelaar. Draait er één niet, dan meldt Claude welke en draait de tests
   die hem nodig hebben niet. Voorbeelddata in de dev-database alleen na vragen.
5. Nieuwe afhankelijkheden pas na akkoord op de tabel hierboven.
6. Na elke taak wordt de tabel "Fasen en stand" in `docs/plans/plan.md` bijgewerkt.

## Open punten

Gemeld, niet in deze fase opgelost:

- **`AUDIT_SECRET` vervangen** (bijvoorbeeld na een lek van `shared/.env.local`) vraagt een sleutel-id per regel of
  een nieuw anker met de oude sleutel; dat volgt als het nodig blijkt, uiterlijk met Onderhoud in fase 6b.
- **Bewaartermijnen** als instelling en het opruimen zelf (auditlog, inlogregels, eenmalige links) horen bij de
  scheduler in fase 6a; `pruneThrough()` staat klaar.
- **De laatste mailfout** voor Onderhoud bewaren hoort bij fase 6b.
- **`integrations.mcp`** komt in fase 4.
- **Een voorstel voor de standaardteksten** van privacy- en toegankelijkheidsverklaring, en het verwerkingsregister,
  in `docs/privacy/` horen bij fase 6b (beslissing 5).
- **`anonymisedAt`** uit beslissing 8 van fase 1 volgt in fase 5b of 6a.
- **`.htaccess`, `shared/` en HSTS bij installatie** volgen met de mapindeling in fase 6a; `UPLOAD_DIR` wijst dan
  naar `shared/uploads`.
- **Het beheerscherm** voor de instellingen en de contrastmelding in gewone taal bouwt fase 5 op deze endpoints.
