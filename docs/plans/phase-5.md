# Fase 5: taakplan

Status: goedgekeurd op 18 november 2026, met de wekelijkse controle (beslissing 12) in plaats van Renovate en de
nieuwe afhankelijkheden uit de tabel. Op dezelfde dag ingeperkt tot de loper op een telefoon; andere rollen worden
fase 5b, tablet en desktop fase 5c (`plan.md`). De lijst van schermen met hun status staat in
`docs/design/walker/mobile/README.md`.

Bron: `docs/plans/plan.md` (Architectuur, Frontend: PWA, responsive en toegankelijk, Branding en configuratie, Privacy
en AVG, Domeinmodel en API-ontwerp, Fase 5, Risico's en review-focus), de Open punten van `docs/plans/phase-3.md` en
`docs/plans/phase-4.md`, `docs/architecture.md`, ADR 0004 en 0006, `AGENTS.md`, de templates en routes van
`../buurtpreventie-legacy`, de code op `develop` (`589778d`), het ontwerp-canvas "Buurtpreventie v4 — schermen"
(versie `1795010071-b82c`), de npm-registry en de repository `lit/dist` op 18 november 2026.

## Doel en klaar-criterium

Een loper gebruikt de app op de eigen telefoon, ook een iPhone met iOS 15 (daar alleen online, beslissing 32):
inloggen, een ronde vinden die een loopmaatje zoekt, aan- en afmelden, een ronde plannen, terugmelden, en de eigen
gegevens beheren. De frontend is een
losse, buildless map `frontend/` met een gevendorde Lit-bundel, die alleen `/api/v1` aanroept. Eerst worden de schermen
goedgekeurd en volgt een proef met Lit; de overige schermen pas na akkoord op de evaluatie.

Klaar als:

- elk scherm uit `docs/design/walker/mobile/README.md` behalve die met status "later" goedgekeurd is, als afbeelding
  in die map staat en gebouwd is, en elk scherm van de oude app dat een loper gebruikt een tegenhanger heeft;
- een loper op een telefoon in drie tikken aanmeldt (browsertest);
- de eigen code niets gebruikt wat Safari 15.0 mist (beslissing 27, test);
- axe-core op elk scherm en elke toestand in CI geen enkele overtreding vindt (WCAG 2.2 A en AA), in Chromium, WebKit
  en Firefox, licht en donker, op 320, 360 en 390 px breed;
- de hoofdtaken met alleen het toetsenbord lukken (test), en met de hand met VoiceOver en NVDA (checklist);
- geen enkel scherm een CSP-overtreding geeft (test), en `frontend/.htaccess` de headers van beslissing 31 zet;
- zoekmachines de app niet crawlen of indexeren (beslissing 35, test);
- de app-shell inclusief Lit onder 100 KB gecomprimeerd blijft (test);
- de evaluatie van Lit in een ADR staat;
- `symfony composer check` groen is, `composer test` zonder database ook, en de pull request naar `develop` groen en
  gemergd is.

Niet in deze fase: schermen voor coördinator, analist en beheerder (fase 5b, met het beheerscherm voor de instellingen,
de MCP-schakelaar en de contrastmelding uit de open punten van fase 3 en 4), en brede schermen (fase 5c, met het
maandraster).

## Schermen

De schermen worden ontworpen in het canvas "Buurtpreventie v4 — schermen" (claude.ai, privé, pagina "Mobile",
390 × 844). Een goedgekeurd scherm komt als afbeelding in de repo, onder `docs/design/<rol>/<apparaat>/` (beslissing 1);
alleen zo'n scherm wordt gebouwd. Alle schermen van deze fase zijn voor de loper op een telefoon, dus
`docs/design/walker/mobile/`.

De lijst van schermen staat alleen in `docs/design/walker/mobile/README.md`: per scherm de bestandsnaam, de titel in
het canvas, de status (beslissing 2) en de hoogte. Een nieuw scherm of een nieuwe toestand krijgt daar eerst een regel
met status "draft", in hetzelfde werk dat het aan het canvas toevoegt. Dit plan noemt schermen bij hun bestandsnaam
(`start`, `login-code-error`). Endpoints en ontbrekende velden per scherm staan bij vastgesteld 19.

`calendar-desktop` (pagina "Desktop", in het canvas "· later (fase 5c)") hoort bij fase 5c en staat niet in die
README; `flow` is een overzicht, geen scherm.

## Vastgesteld

### In de code

1. **`frontend/` bestaat nog niet**; `public/` heeft alleen `index.php` en `bundles/`. De release-workflow van fase 6b
   kopieert `frontend/` naar `public/app/` (plan, Architectuur).
2. **De e-mails linken al naar hash-routes**: `app/#/rounds/<id>`, `app/#/account` en `app/#/welcome/<geheim>`
   (`RoundFacts`, `BuddyNeededMail`, `WalkerWithdrawnMail`, `InvitationMail`). De router moet die drie dus kennen.
3. **Het manifest staat op `GET /api/v1/manifest.webmanifest`** met `start_url` en `scope` op `<pad>/app/` (fase 3,
   beslissing 13). De frontend linkt er relatief naar (`../api/v1/manifest.webmanifest`).
4. **CSRF**: `SameOriginGuard` weigert een schrijvend verzoek van de browserfirewall zonder `Sec-Fetch-Site`, `Origin`
   of `Referer`, tenzij een `csrf-token`-header van minstens 24 tekens meekomt. Safari op iOS 15 stuurt geen van de
   drie; zonder die header is een lid daar overal buitengesloten (ADR 0006).
5. **`GET /config`** geeft vier kleuren, logo, iconen, taal, welkomst- en spoedtekst, privacy- en
   toegankelijkheids-URL, minimum en maximum lopers; `contact` en `group` alleen voor een ingelogd lid.
6. **Geen instelling voor de hint bij een notitie**: de catalogus (`SettingName`) heeft welkomst- en spoedtekst, niets
   voor het terugmeldformulier.
7. **Iedereen ziet de uitkomst van een terugmelding**: `GetRound` geeft `report.outcomeKind` aan elk lid, alleen de
   notitie is beperkt (`ReportReadingPolicy`).
8. **`GetRound` geeft telefoonnummers** van lopers aan lopers van dezelfde ronde (met toestemming). Die mag een service
   worker nooit bewaren.
9. **Een loper mag een eigen ronde wijzigen en verwijderen zolang hij er alleen op staat** (`RoundPolicy::mayChange`);
   annuleren mag elke loper van een begonnen ronde. "Ronde wijzigen" en "Ronde ging niet door" vragen dus geen nieuwe
   rechten.
10. **Regel 6 van `docs/architecture.md`** (de frontend roept alleen endpoints uit de OpenAPI-spec aan) wacht op deze
    fase; `OpenApiCoverageTest` houdt spec, router en Hurl al gelijk.
11. **Dependabot heeft geen npm-ecosysteem** in `.github/dependabot.yml`; `dependabot-automerge.yml` werkt per
    update-soort, niet per ecosysteem, en past dus ook op npm. Node 26 staat lokaal, niet op de server.
12. **De Opengrep-regels** in `.opengrep/rules/` gaan over PHP en YAML; JavaScript scant niemand.

### In het ontwerp

13. **Lettertype Inter via Google Fonts** in elk scherm. De app gebruikt het systeemlettertype (plan, Frontend): een
    lettertype van Google stuurt het IP-adres van elke loper naar Google, en de CSP (`style-src 'self'`) blokkeert het.
14. **De kalender is een week met drie dagdelen per dag**, ook op een telefoon; het plan noemde een lijst met alleen
    de dagen waarop iets gepland staat. Op 320 px is een dagdeel 65 px breed.
15. **"Start, offline" toont open rondes met namen** ("Avond · Joost"); het plan bewaart offline alleen de eigen
    rondegegevens, nooit een ledenlijst. Zie beslissing 32.

### Lit 3.3.3

16. **De nieuwste release is 3.3.3** (npm `lit`, 14 mei 2026, 188 dagen oud); er is geen Lit 4. Releases kwamen de
    afgelopen anderhalf jaar ongeveer twee keer per jaar (3.3.1 juli 2025, 3.3.2 december 2025, 3.3.3 mei 2026).
17. **De gebundelde versie staat niet op npm** maar in de repository `lit/dist`: tag `v3.3.3`, commit `bd7a6bc`
    ("Bundles for lit@3.3.3", 14 mei 2026), bestanden `core/lit-core.min.js` en `all/lit-all.min.js`. De commits zijn
    van "Lit Robot" en niet ondertekend. jsDelivr (`cdn.jsdelivr.net/gh/lit/dist@3.3.3`) levert hetzelfde bestand,
    byte voor byte. `lit-core.min.js`: 15.734 bytes, 6.087 gecomprimeerd (gzip -9), SHA-256
    `f607f470475d8ab790754cb70f72cdbe2390c4a57ab59df6cdb360eeb72bd87c`, licentie BSD-3-Clause in de kop, met
    `@lit/reactive-element` 2.1.2, `lit-html` 3.3.3 en `lit-element` 4.2.2, zonder `eval` of `new Function`.
    `lit-all.min.js` (met alle directives) is 29.370 bytes, 10.240 gecomprimeerd.
18. **Zonder `adoptedStyleSheets` zet Lit stijlen als `<style>`-element** in de shadow root (de controle staat aan het
    begin van de bundel). Safari kent `adoptedStyleSheets` pas vanaf 16.4. Op iOS 15 is dat een inline stijl, en de
    CSP uit Fase 5 (`style-src 'self'`, zonder `'unsafe-inline'`) blokkeert die.

### Endpoints per scherm

19. Wat de schermen gebruiken, en wat ontbreekt (beslissingen 20 tot en met 23):

    | Scherm | Endpoints | Ontbreekt |
    | --- | --- | --- |
    | Inloggen, Code invullen | `GET /config`, `POST /auth/login`, `POST /auth/login-code`, `POST /auth/login-code/verify`, `GET /me` (ingelogd of niet) | niets |
    | Welkom, Wachtwoord instellen | `POST /auth/invitation`, `PUT /me/password` | niets |
    | Browser te oud | geen | niets |
    | Start, Start offline | `GET /me` (naam), `GET /me/rounds?view=upcoming&limit=1`, `GET /staffing-gaps?days=7`, `PUT` en `DELETE /rounds/{id}/walkers/me` | **wie al loopt** (beslissing 20) |
    | Kalender (Week en Lijst) | `GET /rounds?from=&to=` | **wie al loopt (Lijst) en de uitkomst van een afgelopen ronde** (beslissing 21) |
    | Ronde plannen | `POST /rounds`, `GET /meeting-points`, `GET /config` | niets |
    | Afgelopen ronde | `GET /rounds/{id}` (`report.outcomeKind`, vastgesteld 7) | de uitkomst (beslissing 21) |
    | Ronde (open, genoeg lopers, met anderen, alleen, vol) | `GET /rounds/{id}`, `PUT`/`DELETE /rounds/{id}/walkers/me` | **wat het lid hier mag** (beslissing 22) |
    | Ronde wijzigen | `PATCH`/`DELETE /rounds/{id}`, `GET /meeting-points` | idem |
    | Opmerking toevoegen | `POST /rounds/{id}/comments`, `DELETE /comments/{id}` | **de hint** (beslissing 23) |
    | Afmelden bevestigen | `DELETE /rounds/{id}/walkers/me` | niets |
    | Resultaat melden | `GET /outcomes`, `POST /rounds/{id}/reports`, `DELETE /reports/{id}` (ongedaan maken) | de hint |
    | Ronde ging niet door | `POST /rounds/{id}/cancellation` | niets |
    | Mijn rondes | `GET /me/rounds?view=upcoming\|to_report\|all` | de uitkomst (beslissing 21) |
    | Meer | `GET /config` (privacy-URL), `POST /auth/logout` | niets |
    | Mijn gegevens | `GET`/`PATCH /me`, `GET /me/export` | niets |
    | Mijn gegevens downloaden | `GET /me/export` | niets |
    | Bevestig dat jij het bent | `POST /auth/confirm`, of `POST /auth/login-code` en `/verify` | niets |
    | Mijn activiteit | `GET /me/activity` | niets |
    | Koppelingen | `GET`/`POST`/`DELETE /me/tokens` | niets |
    | Meldingen | `GET`/`PATCH /me` (`notifyOpenRounds`) | niets |
    | Lopers | `GET /members` | niets |
    | Hulp en spoed | `GET /config` (spoedtekst, contact, groep) | niets |
    | Privacyverklaring | `GET /config` (`privacyUrl`, de link naar buiten; zonder dat het scherm in aanbouw, beslissing 34) | de tekst in de app, later |
    | Handleiding voor lopers | geen in deze fase (beslissing 34) | de tekst: geen endpoint, `docs/user-guide/` heeft alleen `mcp.md` |

## Voorgestelde beslissingen

### Ontwerp en goedkeuring

1. **Het goedgekeurde ontwerp staat als afbeelding in de repo**, in `docs/design/<rol>/<apparaat>/<scherm>.png`:
   rol `walker`, `coordinator`, `analyst` of `admin`, apparaat `mobile`, `tablet` of `desktop`, zodat een map zegt
   voor wie en op welk toestel een scherm is. Deze fase vult alleen `docs/design/walker/mobile/`. De afbeelding is een
   PNG-export uit het canvas op 2x (een scherm van 390 px breed wordt 780 px), in de toestand van de goedkeuring:
   scherp genoeg om randen en iconen te vergelijken, dus een maat uit de afbeelding gedeeld door twee is de maat in
   CSS-pixels. Een later gewijzigd scherm krijgt een nieuwe export en een nieuwe datum. Het canvas blijft de werkplaats, de repo de vastgelegde versie, zodat de
   frontend ook te controleren is zonder toegang tot het canvas. De schetsen van het eerste plan (statische HTML in
   `docs/design/sketches/`) vervallen.
2. **De status staat in de titel in het canvas**: "· concept" (nog niet goedgekeurd, wordt niet gebouwd),
   "· goedgekeurd 20 nov" (staat als afbeelding in de repo en wordt gebouwd) of "· later (fase 5c)". Een blauwe
   notitie op de pagina "Mobile" legt het uit. Een goedgekeurd scherm dat daarna verandert, gaat terug naar
   "· concept". De README in `docs/design/walker/mobile/` volgt de titel: "draft" of "approved <datum>".
3. **Geen sessies met testlopers in deze fase** (besloten op 18 november 2026). De ontwikkelaar keurt de schermen
   goed. Testen met lopers en hun toestellen doet de vereniging vóór livegang (plan, Frontend).
4. **Het systeemlettertype, op elk toestel** (besloten op 18 november 2026; vastgesteld 13; ADR 0009):
   `system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`, dus San Francisco op een iPhone, Roboto op Android en
   Segoe UI op Windows. Op iOS zet `font: -apple-system-body` op `<html>` de basismaat volgens de tekstgrootte
   die de loper instelde; de 18 px uit het ontwerp wordt een `rem`-waarde daarop, zodat alles meeschaalt. Geen download, geen verzoek naar Google, en het
   lettertype is gemaakt voor dat scherm. Inter zelf hosten viel af: tientallen KB per gewicht en een wisseling van
   lettertype op een trage verbinding. Het canvas gebruikt dezelfde lettertypes als de app, zonder Google Fonts (prompt C).
   Schermen worden op een Mac beoordeeld, goedgekeurd en geëxporteerd, dus de afbeeldingen in `docs/design/` zijn in
   San Francisco, zoals op een iPhone. Op Android en Windows mag een regel anders afbreken; maten, kleuren en volgorde
   niet.
   Alleen het lettertype wisselen maakt een goedgekeurd scherm niet weer concept: het krijgt een nieuwe export met de
   datum in de titel ongewijzigd.
5. **De kalender heeft een wissel Week en Lijst** (vastgesteld 14). Week: de week met dagdelen uit het ontwerp. Lijst:
   per dag alleen de dagen met een ronde, elke ronde op een regel ("Avond 20:00 · Joost · Maatje nodig"), zoals het
   plan een telefoonkalender beschreef. Beide uit `GET /rounds`; de keuze onthoudt de telefoon (`localStorage`, alleen
   gemak). Standaard Week, zoals het ontwerp. Het plan krijgt die afwijking in T15.

### Lit-proef

6. **Lit 3.3.3, de bundel `lit-core.min.js`** (vastgesteld 16 en 17). De nieuwste release, 188 dagen oud, dus ruim
   buiten de cooldown van ADR 0004 (7 dagen voor patch en minor, 14 voor een major); geen nieuwere versie om op te
   wachten. `lit-core` en niet `lit-all`: de directives in `lit-all` (onder meer `repeat`, `live`, `classMap`) kosten
   4 KB gecomprimeerd extra, en de proef laat zien of er een nodig is. Blijkt dat zo, dan wisselen we in de evaluatie,
   met dezelfde controle.
7. **Het bestand komt gecontroleerd binnen met `php tools/vendor-lit.php <versie>`** (PHP, zonder nieuwe
   afhankelijkheden, zoals `tools/opengrep-release.php`). Het script:
   - weigert een versie buiten major 3 en een release jonger dan 7 dagen (de tijd van `lit@<versie>` op npm);
   - leest de commit van tag `v<versie>` in `lit/dist` via de GitHub-API en haalt het bestand op bij die commit-SHA,
     niet bij de tag;
   - haalt hetzelfde bestand bij jsDelivr en eist dezelfde bytes;
   - eist dat de `lit-html`-versie in het bestand de gevraagde versie is;
   - schrijft `frontend/vendor/lit-core.min.js` en `frontend/vendor/vendor.json` (versie, commit, bron-URL, SHA-256,
     releasedatum).
   `VendoredLibraryTest` (PHPUnit, in `composer check`) eist dat het bestand de SHA-256 uit `vendor.json` heeft.
   Grens: de commits van `lit/dist` zijn niet ondertekend en er is geen provenance; twee bronnen vangen geknoei
   onderweg of bij het CDN, niet een gekaapte Lit-repository. Daartegen werkt de cooldown, zoals bij Composer.
8. **Alle componenten in de light DOM** (`createRenderRoot()` geeft het element zelf) met één stylesheet `app.css`
   via `<link>`. Drie redenen: de CSP op iOS 15 (vastgesteld 18), `<label for>` en `aria-describedby` werken niet over
   een shadow-grens (plan, Valkuil bij Lit), en de kleuren van de vereniging gelden overal. Kosten: geen ingekapselde
   stijlen, dus elke component krijgt een eigen klassevoorvoegsel. Alternatief: shadow DOM met `static styles`; dan
   vraagt iOS 15 `'unsafe-inline'` of een nonce per antwoord, en een statische `.htaccess` kan geen nonce maken.
9. **Geen decorators** (die vragen een buildstap): `static properties`. Eigen code blijft binnen ES2021 en de
   grenzen van beslissing 27.
10. **Omvang van de proef**: de app-shell (kop met naam of logo uit `/config`, de kleuren, de navigatie onderaan,
    taal), de router, de API-client met CSRF-header en problem+json, de vertalingen, Inloggen en Code invullen
    (wachtwoord en code per mail, ingelogd blijven) en Start met "Ik loop mee" en ongedaan maken. Alleen schermen die
    goedgekeurd zijn. Niet in de proef: service worker en de andere schermen. De proef draait onder de CSP van
    beslissing 31 en met de browsertests van beslissing 29, zodat die niet achteraf knellen.
11. **Evaluatie**, in dit plan onder T7, daarna wacht ik op je akkoord:
    - axe-core, toetsenbordtest en CSP schoon in Chromium, WebKit en Firefox;
    - de controle op Safari 15.0 (beslissing 27) schoon, en wat die controle aan eigen code tegenhield;
    - grootte van de app-shell, en regels eigen code per scherm;
    - wat lastig was: toestand tussen schermen, formulieren, focus na een routewissel, meldingen voor schermlezers,
      foutafhandeling;
    - hoe dicht het gebouwde scherm bij het goedgekeurde ontwerp ligt;
    - het oordeel: verder met Lit, of nu nog overstappen naar het alternatief uit het plan (Preact met htm).
    Na fase 5 legt ADR 0010 het vast.

### Gevendorde bibliotheken bijwerken

12. **Een wekelijkse controle die een issue opent** (gekozen boven Renovate), zoals bij een nieuwe Opengrep-major
    (ADR 0004). Een job `vendored` in `weekly.yml` leest `frontend/vendor/vendor.json`, vraagt de tags van `lit/dist`
    op, en opent of werkt een issue bij (met `.github/scripts/report-issue.sh`, alleen `issues: write`) zodra er een
    nieuwere release is die 7 dagen oud is; een nieuwe major krijgt een eigen issue. Bijwerken is
    `php tools/vendor-lit.php <versie>` en een pull request, waarop CI gewoon draait. Wekelijks, niet maandelijks: de
    job draait mee in de bestaande run van `weekly.yml` en kost één API-aanroep, en een beveiligingsfix in Lit wacht
    zo hooguit een week na de cooldown in plaats van een maand. Renovate viel af: met `GITHUB_TOKEN` start zijn pull
    request geen CI (regel van GitHub), en daarvoor zou een token met schrijfrechten als geheim in de repo moeten,
    het risico waarom ADR 0004 de app van Mend uitsluit. Leaflet (U9) krijgt later een tweede regel in `vendor.json`.
13. **De Node-pakketten voor de browsertests** (beslissing 29) volgt Dependabot wel: een npm-ecosysteem op
    `/tests/browser` met dezelfde cooldown als Composer (7, 7 en 14 dagen); `dependabot-automerge.yml` behandelt ze
    zoals Composer-pakketten.

### Opbouw van de frontend

14. **Indeling**:

    ```text
    frontend/
      index.html          geen inline script of stijl
      .htaccess           beveiligingsheaders (beslissing 31)
      app.css
      unsupported.js      ES5, alleen voor te oude browsers (beslissing 28)
      sw.js               service worker (na de proef)
      js/main.js, js/api.js, js/router.js, js/i18n.js, js/session.js
      js/screens/<scherm>.js, js/components/<component>.js
      i18n/nl.json, i18n/en.json
      vendor/lit-core.min.js, vendor/vendor.json
    ```
    Lit wordt relatief geïmporteerd (`../vendor/lit-core.min.js`), zonder import map.
15. **Lokaal**: een symlink `public/app` naar `../frontend`, in de repo, zodat de Symfony-server de app op `/app/`
    serveert. Of de Symfony CLI `index.html` voor `/app/` geeft en `.htaccess` negeert, controleer ik in T5; zo niet,
    dan draait lokaal dezelfde PHP-router als in CI (beslissing 29). De release (fase 6b) kopieert in plaats van te
    linken.
16. **Hash-router** met de routes uit de mails (vastgesteld 2): `#/`, `#/login`, `#/welcome/<geheim>`,
    `#/rounds/<id>`, `#/rounds/new`, `#/my-rounds`, `#/report/<id>`, `#/calendar`, `#/more`, `#/account`, `#/help`,
    en wat de goedgekeurde schermen verder vragen. Na elke routewissel gaat de focus naar de kop van het scherm en
    verandert de `<title>`.
17. **Eén API-client** (`js/api.js`) met alle paden als sjablonen in één tabel, relatief (`../api/v1/…`), zodat de app
    ook in een pad werkt. Bij elk schrijvend verzoek een `csrf-token`-header zoals Symfony's eigen
    `csrf_protection_controller.js` dat doet (vastgesteld 4). Een problem+json wordt een fout met `code` en
    veldfouten; een 401 gaat naar inloggen en wist de cache van de service worker.
18. **Vertalingen** in `frontend/i18n/nl.json` en `en.json`; de taal komt uit `/config` en staat in `lang` op `<html>`.
    `TranslationKeysTest` controleert ook deze twee bestanden.
19. **Kleuren via CSSOM** (`document.documentElement.style.setProperty`), wat de CSP toestaat; donkere modus kiest de
    donkere varianten met `prefers-color-scheme`. Systeemlettertype, 18 px basis, `rem` overal.

### Ontbrekende velden

Elke wijziging gaat test eerst, met OpenAPI, Hurl en de pariteitstest van de MCP-tool die dezelfde handler gebruikt.

20. **`/staffing-gaps` krijgt `walkers`**: de `displayName` van elke actieve loper ("Avond · Joost"). Ook in
    `staffing_gaps` via MCP; een weergavenaam is geen contactgegeven. Het ontwerp toont soms alleen een voornaam
    ("Joost", "jij en Daan"); de API geeft de unieke weergavenaam ("Joost K."), en de frontend toont die zoals hij is.
    Alternatief: per open ronde `GET /rounds/{id}`; dat zijn N verzoeken op het startscherm, met telefoonnummers die
    het scherm niet nodig heeft.
21. **`/rounds` en `/me/rounds` krijgen `outcomeKind`** (`nothing_to_report`, `remarks`, `incident` of `null` zonder
    terugmelding), zodat kalender en Mijn rondes "Gelopen, geen bijzonderheden", "Incident gemeld" of "Nog geen
    terugmelding" tonen. Elk lid ziet de uitkomst al in `GetRound` (vastgesteld 7). `reported` blijft in `/me/rounds`.
    `/rounds` krijgt ook `walkers` (beslissing 20), voor de Lijst van de kalender; ook in `list_rounds` via MCP.
22. **`GET /rounds/{id}` krijgt `actions`**: de lijst van wat het vragende lid met deze ronde mag (`sign_up`,
    `withdraw`, `change`, `delete`, `cancel`, `report`, `comment`), uitgerekend met dezelfde Voters. Zo toont de
    frontend alleen knoppen die werken, zonder de regels te kopiëren (vastgesteld 9). Alternatief: de frontend rekent
    het zelf uit rollen en status; dan staat elke regel twee keer en loopt hij uit elkaar.
23. **De hint bij een notitie of opmerking is een vaste tekst** in de vertalingen, onder het veld en gekoppeld met
    `aria-describedby`: "Schrijf geen namen, kentekens of signalementen. Zag je iets strafbaars? Geef het direct door
    aan de politie.", gevolgd door de spoedtekst uit `/config`. Geen nieuwe instelling. Alternatief: een instelbare
    hint per taal (plan: "instelbare tekst"); de spoedtekst is al instelbaar en noemt de nummers.
24. **Tegenhangers in de oude app**, voor de loper:

    | Oude app (`templates/`) | Nieuw scherm |
    | --- | --- |
    | `security/login*`, `security/reset`, `security/token` | Inloggen, Code invullen, Welkom |
    | `security/account`, `password-change`, `permitted-change` | Mijn gegevens, Wachtwoord instellen, Bevestig dat jij het bent |
    | `page/home` | Start |
    | `walk/calendar`, `calendar-day`, `calender-new-round` | Kalender, Ronde plannen |
    | `walk/detail`, `round/detail` | Ronde (vier toestanden), Ronde wijzigen |
    | `walk/walked`, `walk/result-form`, `result/modal*`, `result/inline` | Mijn rondes, Resultaat melden, Opmerking |
    | `member/list` | Lopers |
    | nieuw | Meldingen, Koppelingen, Mijn activiteit, Hulp en spoed, Ronde ging niet door |

    De rest (`round/list`, `round/change-form` voor elke ronde, `member/form`, `user/*`, `result/list`, `config/*`)
    hoort bij fase 5b.

### Toegankelijkheid en oudere toestellen

25. **Tikdoelen en tekst volgen het ontwerp**: 18 px basis, knoppen voor hoofdacties 52 px hoog, andere tikdoelen
    minstens 44 px (WCAG vraagt 24).
26. **Op 320 px breed** moet elk scherm zonder horizontaal scrollen werken, ook al is het ontwerp op 390 getekend; de
    browsertests controleren beide.
27. **Ondergrens Safari 15.0.** Safari 15.0 mist wat 15.4 en later brachten: `<dialog>`, `Array.prototype.at()`,
    `findLast()`, `structuredClone()`, `Object.hasOwn()`, `:has()`, `:focus-visible`, `dvh`/`svh`, `inert` (15.5),
    container queries (16.0), import maps, `adoptedStyleSheets`, `ElementInternals` (`attachInternals()`, eigen
    formuliervelden) en `static {}`-blokken in een klasse (alle vier 16.4), en CSS nesting. Eigen code gebruikt dat
    niet: de onderbladen in het ontwerp ("Afmelden bevestigen", "Ronde ging niet door") worden een eigen
    bevestigingsblok, geen `<dialog>`; `:focus` in plaats van `:focus-visible`; `vh` met een terugval; een
    formuliercomponent rendert een gewoon `<input>` in de light DOM (beslissing 8) in plaats van zelf een veld te zijn.
    Lit zelf vraagt alleen custom elements, shadow DOM en `<template>`, die Safari al sinds 10.1 kent, en valt zonder
    `adoptedStyleSheets` terug op een `<style>`-element (vastgesteld 18).
    Zo wordt iOS 15 bewaakt zonder toestel: `FrontendSourceTest` (PHPUnit) zoekt de namen hierboven in `frontend/` en
    faalt erop, en `acorn` controleert de syntax in CI tegen ES2021 (dat vangt ook `static {}`, ES2022). Er is geen test
    met de hand op een iPhone met iOS 15 (besloten op 18 november 2026): CI heeft geen Safari 15 (WebKit in Playwright
    is de nieuwste), en een dienst als BrowserStack valt af (account, externe dienst). Wat dit niet vangt, is een fout
    in Safari 15 zelf bij iets wat het wel kent; dat risico blijft, en het wordt klein gehouden door op iOS 15 geen
    service worker te draaien (beslissing 32). Alternatief: ondergrens 15.4, met de melding "Werk je iPhone bij"; elke iPhone
    met iOS 15 kan gratis naar 15.8, maar niet elke loper werkt bij.
28. **Te oude browser**: `<script nomodule src="unsupported.js">` (ES5) zet de tekst van het scherm "Browser te oud" in
    de pagina. Ook een `<noscript>`-tekst.
29. **Browsertests in CI**, een nieuwe job "Browser" naast Checks en Database:
    - MariaDB-container, migraties, `app:fixtures:load test`, en de PHP-server met een router
      (`tests/browser/server.php`) die `frontend/` op `/app/` serveert met de headers uit `frontend/.htaccess`, en
      `/api/` doorgeeft aan `public/index.php`;
    - Playwright in Chromium, WebKit en Firefox, op 320 × 568, 360 × 740 en 390 × 844, licht en donker. Dat zijn de
      drie onafhankelijke browser-engines: Chromium (Chrome, Edge, Samsung Internet), WebKit (Safari en elke browser
      op iOS) en Gecko (Firefox, ook op Android). Firefox kost geen nieuw pakket, alleen een browser meer in CI en
      een langere job; zonder die browser vangt niets een fout die alleen in Gecko optreedt, terwijl het plan Firefox
      op de computer belooft. 320 is de breedte van de iPhone SE (1e generatie, iOS 15) en wat WCAG voor reflow vraagt
      (1280 px op 400 % zoom); 360 is de gangbare Android-breedte; 390 de breedte van het ontwerp. Tablet en desktop
      (768 en 1280) komen in fase 5c;
    - `@axe-core/playwright` op elk scherm en elke toestand (fout in een veld, bevestiging open, melding na een
      actie), tags `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`, `wcag22aa`, contrastregel aan; elke overtreding faalt;
    - wat axe niet ziet, als eigen test: de drie opdrachten uit het plan met alleen Tab, Enter en spatie; focus
      zichtbaar en niet onder de kop of de navigatie onderaan (2.4.11); tikdoelen uit beslissing 25; geen horizontaal
      scrollen op 320 px en met tekst op 200 %; een `aria-live`-melding na elke actie; `lang` op `<html>`;
    - een iOS-15-verzoek nagespeeld: zonder `Origin` en `Sec-Fetch-Site`, en toch aangemeld (vastgesteld 4);
    - elke CSP-overtreding (`securitypolicyviolation`) en elke consolefout faalt de test;
    - `acorn` controleert elk JavaScript-bestand tegen ES2021, `unsupported.js` tegen ES5.
    Geen geheimen, draait op elke pull request. Lokaal optioneel (`npm ci` in `tests/browser`), niet op de server.
30. **Met de hand, per release**: een checklist in `docs/testing.md` (alleen toetsenbord, VoiceOver op een iPhone,
    NVDA op Windows, 400 % zoom). Geen iPhone met iOS 15 (beslissing 27).

### Beveiligingsheaders voor `frontend/`

31. **`frontend/.htaccess`** met `Header always set`:
    - `Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:;
      connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` zoals het plan, aangevuld met
      `object-src 'none'`, `manifest-src 'self'` en `worker-src 'self'` (alle drie vallen nu terug op `default-src`;
      expliciet zegt wat de bedoeling is);
    - `Permissions-Policy: camera=(), microphone=(), geolocation=()` zoals het plan; U7 en U9 zetten er later `self`
      neer;
    - daarnaast, als bij de API: `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`,
      `Cross-Origin-Opener-Policy: same-origin`;
    - compressie met `mod_deflate` voor HTML, CSS, JavaScript, JSON en het manifest; dat haalt CSS en JavaScript
      ongeveer 70 tot 80 % kleiner, zonder buildstap. Geen minificatie van eigen code: na compressie scheelt die nog
      een paar KB, en hij vraagt een buildstap of een tweede, gegenereerde kopie die kan afwijken. Lit komt al
      geminificeerd. `AppShellSizeTest` meet de grootte na gzip, dus een te zware shell valt op;
    - `Cache-Control: no-cache` op alles (zonder buildstap hebben bestanden geen hash in de naam, dus de browser vraagt
      met een ETag na; offline helpt de service worker);
    - `X-Robots-Tag: noindex, nofollow` (beslissing 35).
    HSTS zet de API al voor de hele host (`HSTS_MAX_AGE`), dus niet nog eens hier. `FrontendHeadersTest` leest
    `.htaccess` en eist precies deze headers, zonder `'unsafe-inline'` of `'unsafe-eval'`; de browsertests draaien
    achter dezelfde headers. Of Apache ze op een echte host zet, controleert de installer in fase 6b.

### PWA (na de proef)

32. **Service worker `sw.js` in `app/`**, scope `app/` (fase 3, beslissing 13).
    - **Niet op iOS 15** (besloten op 18 november 2026): installeren en offline worden daar niet ondersteund. De app
      registreert de service worker alleen als `HTMLScriptElement.supports?.('importmap')` waar is (Safari 16.4,
      Chrome 89, Firefox 108); dat sluit precies Safari 15.x uit, zonder te kijken naar de user agent. Op iOS 15 werkt
      de app dus als gewone website: zonder bereik toont hij `no-connection`, niet `start-offline`. Waarom: offline op
      iOS 15 is niet te testen zonder toestel (beslissing 27), Safari wist de opslag van een website na 7 dagen zonder
      gebruik (behalve van een app op het beginscherm), en een kapotte service worker op iOS 15 kan een oude versie van de app vasthouden. Een browsertest
      haalt `HTMLScriptElement.supports` weg en controleert dat de app zonder service worker werkt en `no-connection`
      toont. Wat iOS 15 wel heeft: alles online, en "Zet op beginscherm" opent de site zonder browserbalk.
    - **App-shell**: HTML, CSS, JavaScript, vertalingen en Lit staan in de cache, zodat de app direct opent, ook
      zonder bereik. Bij elke start vraagt de browser `sw.js` opnieuw op. `sw.js` draagt een versie: de SHA-256 over
      alle bestanden van de shell. Is iets veranderd, dan is `sw.js` anders, haalt de nieuwe service worker de
      veranderde bestanden één keer op en toont de app "Nieuwe versie beschikbaar, vernieuwen", nooit midden in een
      formulier. Zonder verandering gaat er alleen `sw.js` over de lijn. `ServiceWorkerVersionTest` rekent de versie
      na en faalt als iemand een bestand wijzigt zonder `composer frontend:version`.
    - **Gegevens** (de API) altijd eerst van het netwerk; alleen zonder bereik uit de cache, met de melding uit het
      ontwerp en de tijd van de laatste keer ophalen. In de cache: `GET /me/rounds`, `GET /rounds` en
      `GET /staffing-gaps`.
    - **Namen offline, besloten op 18 november 2026: wel bewaren**: `/rounds` en `/staffing-gaps` bevatten de weergavenamen van wie meeloopt
      (beslissing 20), en "Start, offline" toont ze (vastgesteld 15). Het plan zegt "alleen de eigen rondegegevens;
      nooit ledenlijsten". Een naam als "Joost K." bij een ronde is geen ledenlijst en geen
      contactgegeven, en de cache gaat weg bij uitloggen en bij elke 401. Wat er overblijft op een verloren telefoon
      die nog ingelogd is: voor een paar weken welke weergavenamen wanneer lopen; dat ziet de vinder ook in de app
      zelf.
    - **Nooit in de cache**: `/rounds/{id}` (telefoon), `/members`, `/me`, `/config`, `/me/tokens`, `/admin/`,
      `/auth/`.

### Overige tests

33. PHPUnit, in `composer check`, zonder Node:
    - `VendoredLibraryTest` (beslissing 7);
    - `FrontendSourceTest`: geen `<script>` zonder `src`, geen `style=`-attribuut, geen `on…=`-attribuut in HTML,
      geen namen uit beslissing 27;
    - `FrontendHeadersTest` (beslissing 31);
    - `AppShellSizeTest`: de shell (HTML, CSS, JS en Lit) samen onder 100 KB met gzip;
    - `ServiceWorkerVersionTest` (beslissing 32), vanaf T12;
    - `FrontendApiContractTest` (regel 6): elk pad uit de tabel in `js/api.js` staat in de OpenAPI-spec met dezelfde
      methode;
    - `TranslationKeysTest` ook over `frontend/i18n/`.

### Schermen in aanbouw

34. **Privacyverklaring en Handleiding voor lopers zijn in deze fase een scherm "in aanbouw"** (besloten op 18 november
    2026), want hun tekst heeft nog geen bron. Het ontwerp in het canvas (`privacy`, `handbook`) is de maat voor de
    fase die de tekst levert. De routes `#/privacy` en `#/handbook` bestaan en tonen allebei het goedgekeurde scherm
    `under-construction`: de titel van het scherm, een korte uitleg in B1 uit de vertalingen (nl en en) en een weg
    terug, zodat elke link in de flow (Inloggen, Meer, Hulp) op een werkend scherm uitkomt. Geen kapotte link: de
    browsertests volgen elke link naar `#/…` op elk scherm en falen op een route die de router niet kent.

    De link "Privacyverklaring" opent `privacyUrl` uit `/config` als de beheerder die instelde (alleen `https`, door
    `ChangeSettings` gecontroleerd), in een nieuw tabblad met `rel="noopener"`, zodat de app open blijft; alleen
    zonder `privacyUrl` gaat hij naar `#/privacy`. Wie bij inloggen of in Mijn gegevens persoonsgegevens invult, moet
    de privacyverklaring kunnen lezen (AVG, artikel 13); een scherm in aanbouw volstaat daarvoor alleen zolang er geen
    verklaring is ingesteld. De browsertests dekken beide gevallen: met `privacyUrl` wijst de link daarheen, zonder
    naar `#/privacy`.

### Zoekmachines

35. **Geen toegang voor zoekmachines** (besloten op 18 november 2026). De app is alleen voor leden: zonder inloggen is
    er niets te vinden, en een vindbare inlogpagina laat alleen zien welke vereniging de app gebruikt.
    - `public/robots.txt` met `User-agent: *` en `Disallow: /`, zodat een crawler niets opvraagt. Dat werkt alleen
      als de app op de root van de host staat: in een submap leest een crawler `/robots.txt` van de host, en dat
      bestand is van de hoster.
    - Daarom ook `X-Robots-Tag: noindex, nofollow` op elk antwoord, ook in een submap: in `frontend/.htaccess`
      (beslissing 31) en in `ApiSecurityHeaders` voor `/api/`.
    - Grens: een crawler die `Disallow` volgt, ziet de header niet, en een adres waar ergens openbaar naar gelinkt
      wordt, kan dan als kale link zonder inhoud in de zoekresultaten komen. Een link uit een mail is niet openbaar.
      `robots.txt` is een verzoek, geen slot: de toegang zelf regelt het inloggen.
    `FrontendHeadersTest` en `ApiSecurityHeadersTest` eisen de header; `RobotsTxtTest` eist `Disallow: /`.

## Nieuwe afhankelijkheden (goedgekeurd op 18 november 2026)

Alleen voor tests in CI, in `tests/browser/package.json` met `package-lock.json`, geïnstalleerd met
`npm ci --ignore-scripts`; niets daarvan gaat mee in de release. Versies en datums van npm op 18 november 2026;
cooldown 7 dagen voor patch en minor.

| Pakket | Waarvoor | Licentie | Versie (datum, leeftijd) | Buiten de cooldown |
| --- | --- | --- | --- | --- |
| `@playwright/test` (met `playwright`, `playwright-core`) | Browsertests in Chromium, WebKit en Firefox | Apache-2.0 | 1.65.0 (5 nov 2026, 13 dagen) | ja; 1.65.1 (13 nov, 5 dagen) nog niet |
| `@axe-core/playwright` | axe-core in Playwright | MPL-2.0 | 4.14.0 (15 okt 2026, 34 dagen) | ja |
| `axe-core` (indirect, `~4.14.0`) | De regels van axe | MPL-2.0 | 4.14.0 (5 okt 2026), vastgezet in de lockfile | ja; 4.14.1 (16 nov, 2 dagen) nog niet |
| `acorn` | ES2021- en ES5-controle | MIT | 8.19.0 (5 okt 2026, 44 dagen) | ja |

- De browsers haalt `npx playwright install --with-deps chromium webkit firefox` in CI; niet lokaal nodig.
- MPL-2.0 geldt per bestand en alleen bij verspreiden; axe-core draait in CI en wordt niet verspreid.
- Lit 3.3.3 (BSD-3-Clause) is geen pakket maar een gevendord bestand (beslissingen 6 en 7); de licentie staat in de
  kop van het bestand en in `vendor.json`.

## Taken

Elke taak is één of enkele kleine commits; tests komen vóór de code. Een scherm wordt pas gebouwd als het in
`docs/design/walker/mobile/README.md` goedgekeurd is.

### T1. Branch en taakplan

`feature/phase-5` vanaf `develop`; dit plan.

### T2. Ontwerp goedkeuren

- De status in de titels van het canvas (beslissing 2): gedaan op 18 november 2026, alle schermen "· concept".
- Jij geeft prompt A in de chat van het canvas: Engelse bestandsnamen en de toestanden en foutschermen.
- Jij geeft prompt E in de chat van het canvas: het scherm "In aanbouw" (beslissing 34).
- Jij geeft prompt C in de chat van het canvas: het systeemlettertype in elk scherm (beslissing 4), met nieuwe PNG's
  van de goedgekeurde schermen.
- `docs/design/walker/mobile/README.md`: de afspraken (2x, een maat uit de afbeelding gedeeld door twee is de maat in
  CSS-pixels) en de lijst van schermen met per scherm de status en de hoogte in CSS-pixels.
- Jij keurt de schermen goed (beslissing 3).
- Per goedgekeurd scherm: prompt B in de chat van het canvas, dan prompt D in Claude Code: de PNG's en de status in
  `docs/design/walker/mobile/` (beslissingen 1 en 2).

**Prompt A** (eenmalig):

```text
Werk in dit canvas, verander niets aan het uiterlijk van bestaande schermen.

1. Hernoem de bestanden naar Engelse namen en pas elke link tussen schermen (href) en project/canvas.json mee aan.
   Titels blijven Nederlands, met de status erachter zoals nu ("· concept").
Inloggen.dc.html → login.dc.html
InloglinkVerstuurd.dc.html → login-code.dc.html
Welkom.dc.html → welcome.dc.html
WachtwoordKiezen.dc.html → set-password.dc.html
BrowserOud.dc.html → browser-unsupported.dc.html
Main.dc.html → start.dc.html
Offline.dc.html → start-offline.dc.html
Kalender.dc.html → calendar.dc.html
RondePlannen.dc.html → plan-round.dc.html
RondeOpen.dc.html → round-buddy-needed.dc.html
Ronde.dc.html → round-with-others.dc.html
RondeAlleen.dc.html → round-alone.dc.html
RondeWijzigen.dc.html → change-round.dc.html
RondeVol.dc.html → round-full.dc.html
Opmerking.dc.html → add-comment.dc.html
Afmelden.dc.html → confirm-withdrawal.dc.html
Terugmelden.dc.html → report-result.dc.html
RondeNietDoor.dc.html → round-not-walked.dc.html
MijnRondes.dc.html → my-rounds.dc.html
Meer.dc.html → more.dc.html
Account.dc.html → my-details.dc.html
Bevestigen.dc.html → confirm-identity.dc.html
Activiteit.dc.html → my-activity.dc.html
Koppelingen.dc.html → integrations.dc.html
Meldingen.dc.html → notifications.dc.html
Lopers.dc.html → walkers.dc.html
Hulp.dc.html → help.dc.html
KalenderDesktop.dc.html → calendar-desktop.dc.html
Flow.dc.html → flow.dc.html

2. Voeg deze schermen toe op de pagina Mobile (390 × 844), in dezelfde stijl en met dezelfde voorbeelddata, elk met
   titel "<titel> · concept", naast de schermen waar ze bij horen:
   - login-code-error: "Code invullen" met de melding bij een verkeerde code (met het aantal pogingen dat over is),
     een verlopen code en te veel pogingen (nieuwe code vragen, of wachten);
   - session-expired: terug naar inloggen met "Je bent een tijd niet actief geweest. Log opnieuw in.";
   - start-empty: Start zonder ronde die een loopmaatje nodig heeft;
   - calendar-list: de kalender als lijst, met bovenaan een wissel "Week | Lijst" (ook toevoegen aan calendar);
     per dag alleen de dagen met een ronde, elke ronde op één regel met tijd, dagdeel, namen en status;
   - calendar-past: de week met twee rondes in één dagdeel en afgelopen dagen: "Gelopen, geen bijzonderheden",
     "Incident gemeld", "Nog geen terugmelding", "Niet gelopen";
   - round-just-full: "Ik loop mee" mislukt omdat een ander net eerder aanmeldde;
   - confirm-withdrawal-last: afmelden als enige loper: de ronde vervalt, zonder "Ongedaan maken";
   - my-rounds-empty: Mijn rondes zonder rondes;
   - integration-created: de nieuwe koppeling met de code, één keer getoond, met kopiëren;
   - error: "Er ging iets mis" met opnieuw proberen en de weg naar Hulp en spoed;
   - not-found: een ronde die niet meer bestaat (bijvoorbeeld uit een oude mail);
   - no-access: iets wat deze loper niet mag openen;
   - no-connection: een actie mislukt zonder bereik, buiten Start;
   - form-error: Ronde plannen met een fout bij een veld, de tekst onder het veld;
   - update-available: de melding "Nieuwe versie beschikbaar" met de knop "Vernieuwen".
   Alle tekst in B1-Nederlands, status met tekst en icoon, contrast minstens 4,5 : 1, tikdoelen minstens 44 px.
```

**Prompt B** (bij elke goedkeuring; vul de namen en de datum in):

```text
Deze schermen zijn goedgekeurd op <datum>: <bestand>, <bestand>, ...
1. Zet in project/canvas.json de titel van elk van deze schermen van "· concept" op "· goedgekeurd <datum>".
2. Exporteer elk van deze schermen als PNG op 2x (780 px breed), over de volle hoogte van het scherm, met de
   bestandsnaam van het scherm zonder .dc.html (bijvoorbeeld start.png), en geef ze me om te downloaden.
```

Kan de chat geen PNG maken, dan exporteer je via het menu van het canvas (Share, Export) met dezelfde namen.

**Prompt C** (eenmalig):

```text
Werk in dit canvas. Vervang in elk scherm het lettertype Inter door het systeemlettertype:
1. Verwijder elke link naar Google Fonts (fonts.googleapis.com, fonts.gstatic.com), ook @import en preconnect.
2. Zet overal waar Inter staat: font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
3. Laat maten, gewichten, regelhoogte, kleuren, tekst en titels gelijk, ook "· concept" en "· goedgekeurd <datum>".
4. Controleer op 320 en 390 px breed of tekst nergens over een rand loopt of afgekapt wordt, en noem de schermen waar
   een regel anders afbreekt dan eerst, zonder ze aan te passen.
5. Exporteer elk scherm met "· goedgekeurd" in de titel opnieuw als PNG op 2x (780 px breed), over de volle hoogte
   van het scherm, met de bestandsnaam van het scherm zonder .dc.html, en geef ze me om te downloaden.
```

**Prompt D** (in Claude Code, na elke download uit prompt B of C; vul de titels, namen en de datum in). Jij zet de
PNG's eerst in `shared/` (genegeerd door git): `~/Downloads` is voor Claude Code niet leesbaar (macOS, Privacy en
beveiliging).

```text
Ik heb goedgekeurde schermen uit het canvas geëxporteerd (PNG, 2x) en in shared/ gezet. De tabel in
docs/design/walker/mobile/README.md geeft per scherm de bestandsnaam en de hoogte in CSS-pixels.
Werk op feature/phase-5.

1. Verplaats ze uit shared/ naar docs/design/walker/mobile/:
   - "<titel> · goedgekeurd <datum>*.png" → <bestand>.png
   - ...
   Ontbreekt een bestand of zijn er meer kandidaten, stop en meld het.
2. Controleer elke afbeelding: 780 px breed en precies 2 × de hoogte uit de README (844 wordt 1688).
   Wijkt iets af: niet committen, melden.
3. Zet in die README bij deze schermen de status van "draft" op "approved <JJJJ-MM-DD>".
4. Commit: "Add the approved <bestand> and <bestand> screens".
```

**Prompt E** (eenmalig):

```text
Werk in dit canvas, verander niets aan bestaande schermen behalve de links hieronder.
1. Voeg op de pagina Mobile (390 × 844) het scherm under-construction.dc.html toe, titel "In aanbouw · concept", in
   dezelfde stijl: de kop zoals bij de andere schermen onder Meer, met als titel de naam van het scherm waar de loper
   heen wilde (toon het met "Privacyverklaring"), een korte uitleg in B1 ("Deze pagina komt binnenkort. Heb je een
   vraag? Kijk bij Hulp en spoed.") met een link naar help.dc.html, en een knop "Terug".
2. Laat elke link naar privacy.dc.html of handbook.dc.html (onder meer op login, more en help) naar
   under-construction.dc.html gaan. De schermen privacy en handbook blijven staan als ontwerp voor later, met "· later" in de titel.
3. Noem elke link in het canvas die naar een niet-bestaand scherm gaat.
```

### T3. Lit vendoren (test eerst)

- `VendoredLibraryTest`, dan `tools/vendor-lit.php` en `php tools/vendor-lit.php 3.3.3`.
- De job `vendored` in `weekly.yml` (beslissing 12).

### T4. Ontbrekend veld voor de proef (test eerst)

- `walkers` in `/staffing-gaps` en `staffing_gaps` (beslissing 20), met OpenAPI, Hurl en pariteit.

### T5. Basis van de frontend (test eerst)

- `FrontendSourceTest`, `FrontendHeadersTest`, `AppShellSizeTest`, `FrontendApiContractTest`, de uitbreiding van
  `TranslationKeysTest`.
- `frontend/` volgens beslissing 14, `.htaccess` (beslissing 31), `public/app` (beslissing 15), `unsupported.js`
  (beslissing 28).
- `RobotsTxtTest` en de uitbreiding van `ApiSecurityHeadersTest`, dan `public/robots.txt` en `X-Robots-Tag` in
  `ApiSecurityHeaders` (beslissing 35).
- `tests/browser/` met de pakketten uit de tabel, de router, de job "Browser" in `ci.yml` en Dependabot voor npm
  (beslissing 13).

### T6. Lit-proef (test eerst)

- Browsertests eerst: inloggen met wachtwoord, met een code, een verkeerde code, ingelogd blijven, uitloggen;
  aanmelden vanaf Start in één tik met ongedaan maken; de iOS-15-variant zonder `Origin`; axe en toetsenbord op elke
  toestand.
- App-shell, router, API-client, vertalingen, Inloggen, Code invullen en Start (beslissing 10), alleen als die drie
  goedgekeurd zijn.
- `docs/frontend.md`: de patronen uit de proef (light DOM, eigenschappen, gebeurtenissen, formulieren, focus en
  `aria-live`, fouten, vertalingen).

### T7. Evaluatie

- De punten van beslissing 11 hier in het plan. Daarna stop ik tot je akkoord.

### T8. Overige ontbrekende velden (test eerst)

- `walkers` en `outcomeKind` in `/rounds` (beslissing 21), `outcomeKind` in `/me/rounds`, en `actions` (beslissing
  22), met OpenAPI, Hurl, pariteit en de matrixtests waar de Voters meedoen.

### T9. Ronde, afmelden, opmerking en terugmelden (test eerst)

- De vier toestanden van Ronde, Afmelden bevestigen (met de waarschuwing als de ronde daardoor een loopmaatje zoekt,
  en zonder ongedaan maken als de laatste loper zich afmeldt), Opmerking toevoegen met de hint (beslissing 23).
- Resultaat melden met ongedaan maken, Ronde ging niet door, Mijn rondes.

### T10. Kalender en ronde plannen (test eerst)

- De wissel Week en Lijst (beslissing 5), de week met dagdelen, de lijst per dag, vorige en volgende week, `calendar-list` en `calendar-past`; Ronde plannen vanuit de
  kalender en vanuit een leeg dagdeel; Ronde wijzigen en verwijderen.

### T11. Welkom en Meer (test eerst)

- Welkom en Wachtwoord instellen via de uitnodigingslink.
- Meer, Mijn gegevens, Bevestig dat jij het bent, Mijn activiteit, Koppelingen (het geheim één keer getoond),
  Meldingen, Lopers (telefoon alleen bij toestemming), Hulp en spoed, uitloggen.
- Privacyverklaring (naar `privacyUrl`, anders in aanbouw) en Handleiding voor lopers (in aanbouw), beslissing 34.

### T12. PWA (test eerst)

- Manifest-link, service worker en Start, offline (beslissing 32), met tests voor wat wel en niet in de cache komt en
  het wissen bij uitloggen; "Nieuwe versie beschikbaar"; zonder `HTMLScriptElement.supports` (iOS 15) geen service
  worker en `no-connection`.

### T13. Geen kapotte links

- De browsertests uit beslissing 34: elke link naar `#/…` op elk scherm komt uit op een route van de router, en
  "Privacyverklaring" gaat naar `privacyUrl` als die is ingesteld en anders naar `#/privacy`.

### T14. Documentatie

- `docs/adr/0010-lit-frontend.md` (de evaluatie), `docs/frontend.md`, `docs/architecture.md` (regel 6, de frontend,
  `actions`), `docs/testing.md` (browsertests en de checklist met de hand), `docs/configuration.md`,
  `docs/glossary.md`, `docs/gotchas.md`; `CHANGELOG.md`; `AGENTS.md` (Commands: browsertests).

### T15. Plan bijwerken

- `plan.md`: Fase 5 afvinken, de afwijkingen (kalender als week, systeemlettertype, `walkers`, `outcomeKind`,
  `actions`), de endpointtabel, Fasen en stand.

### T16. Verifiëren en opleveren

- `composer test` zonder database, `symfony composer check`, de browsertests; uitvoer in het verslag, met elke check die
  niet draaide.
- Met de hand: alleen toetsenbord en 320 px op elk scherm.
- Push, titel en beschrijving van de pull request; openen na jouw ja; CI volgen en squash-mergen zodra alles groen is.

## Afspraken

1. Claude commit op `feature/phase-5` in kleine commits en pusht de branch. De pull request gaat pas open na akkoord;
   de merge volgt als squash zodra CI op de laatste commit groen is.
   - Geen `cd`, `pushd`, `-C`, `--git-dir`, `--work-tree` of `GIT_DIR=` in dezelfde aanroep als `git add` of
     `git commit`.
   - Alleen de bestanden van die commit stagen, met letterlijke paden; daarna `git show --stat HEAD`.
2. Eerst de goedkeuring van de schermen (T2) en de proef (T3 tot en met T7); de overige schermen pas na akkoord op de
   evaluatie, en elk alleen als het goedgekeurd is.
3. Het canvas verandert alleen na akkoord op die wijziging.
4. `.env`, `.env.dev` en `.env.test` alleen lezen met `git show HEAD:<bestand>`; `.env.local`, `.env.*.local` en
   `shared/.env.local` nooit lezen of committen.
5. PHPUnit, `symfony console`, Mago, Rector, PHPStan, Composer, npm, `git push` en `gh` draaien buiten de sandbox; logs
   in de scratchpad.
6. Database, server en Mailpit start de ontwikkelaar. Draait er één niet, dan meldt Claude welke en draait de tests
   die hem nodig hebben niet. Voorbeelddata in de dev-database alleen na vragen.
7. Na elke taak wordt de tabel "Fasen en stand" in `docs/plans/plan.md` bijgewerkt.

## Open punten

Gemeld, niet in deze fase opgelost:

- **Fase 5b** bouwt het beheerscherm voor de instellingen met de contrastmelding in gewone taal (open punt van fase 3)
  en de schakelaars voor API-tokens en MCP (open punt van fase 4), en de schermen voor coördinator en analist.
- **Fase 5c** maakt de schermen responsive voor tablet en desktop, met het maandraster (`KalenderDesktop`).
- **Opengrep scant geen JavaScript** (vastgesteld 12). Regels voor `frontend/` (bijvoorbeeld `innerHTML`,
  `unsafeHTML`, `eval`) kunnen erbij als de frontend groeit.
- **Privacyverklaring en Handleiding voor lopers** (beslissing 34): de tekst en zijn bron, het A4 voor lopers in
  `docs/user-guide/` met schermafbeeldingen uit de browsertests, en de schermen volgens het canvas.
- **Het statistiekscherm** op `/stats` blijft U5; offline terugmelden U6; pushmeldingen U4.
- **De release kopieert `frontend/` naar `public/app/`** en de installer controleert de headers op een echte host:
  fase 6b.
- **Leaflet** (U9) komt als tweede regel in `frontend/vendor/vendor.json`, met hetzelfde script.
