# Fase 5: taakplan

Status: goedgekeurd op 18 november 2026, met de wekelijkse controle (beslissing 11) in plaats van Renovate. De
nieuwe afhankelijkheden wachten nog op akkoord.

Bron: `docs/plans/plan.md` (Architectuur, Frontend: PWA, responsive en toegankelijk, Branding en configuratie, Privacy
en AVG, Domeinmodel en API-ontwerp, Fase 5, Risico's en review-focus), de Open punten van `docs/plans/phase-3.md` en
`docs/plans/phase-4.md`, `docs/architecture.md`, ADR 0004 en 0006, `AGENTS.md`, de templates en routes van
`../buurtpreventie-legacy`, de code op `develop` (`589778d`), de npm-registry en de repository `lit/dist` op 18 november
2026.

## Doel en klaar-criterium

Een loper gebruikt de app op de eigen telefoon, ook een iPhone met iOS 15: inloggen, een ronde vinden die een
loopmaatje zoekt, aan- en afmelden, terugmelden. Coördinator, analist en beheerder doen hun werk in dezelfde app. De
frontend is een losse, buildless map `frontend/` met een gevendorde Lit-bundel, die alleen `/api/v1` aanroept. Eerst
komen schetsen en een proef met Lit; de overige schermen pas na akkoord op de evaluatie.

Klaar als:

- elk scherm van de oude app een tegenhanger heeft (tabel bij beslissing 25);
- een loper op een telefoon in drie tikken aanmeldt, ook op een iPhone met iOS 15 (met de hand, door de ontwikkelaar);
- de minst ervaren testloper aanmelden en terugmelden zonder hulp doet, op de eigen telefoon met voorbeelddata;
- axe-core op elk scherm en elke toestand in CI geen enkele overtreding vindt (WCAG 2.2 A en AA), in Chromium en
  WebKit, licht en donker, op 360 en 1280 px breed;
- de hoofdtaken met alleen het toetsenbord lukken (test), en met de hand met VoiceOver en NVDA (checklist);
- geen enkel scherm een CSP-overtreding geeft (test), en `frontend/.htaccess` de headers van beslissing 30 zet;
- de app-shell inclusief Lit onder 100 KB gecomprimeerd blijft (test);
- de evaluatie van Lit in een ADR staat;
- `symfony composer check` groen is, `composer test` zonder database ook, en de pull request naar `develop` groen en
  gemergd is.

## Vastgesteld

### In de code

1. **`frontend/` bestaat nog niet**; `public/` heeft alleen `index.php` en `bundles/`. De release-workflow van fase 6a
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
9. **Regel 6 van `docs/architecture.md`** (de frontend roept alleen endpoints uit de OpenAPI-spec aan) wacht op deze
   fase; `OpenApiCoverageTest` houdt spec, router en Hurl al gelijk.
10. **Dependabot heeft geen npm-ecosysteem** in `.github/dependabot.yml`; `dependabot-automerge.yml` werkt per
    update-soort, niet per ecosysteem, en past dus ook op npm. Node 26 staat lokaal, niet op de server.
11. **De Opengrep-regels** in `.opengrep/rules/` gaan over PHP en YAML; JavaScript scant niemand.

### Lit 3.3.3

12. **De nieuwste release is 3.3.3** (npm `lit`, 14 mei 2026, 188 dagen oud); er is geen Lit 4. Releases kwamen de
    afgelopen anderhalf jaar ongeveer twee keer per jaar (3.3.1 juli 2025, 3.3.2 december 2025, 3.3.3 mei 2026).
13. **De gebundelde versie staat niet op npm** maar in de repository `lit/dist`: tag `v3.3.3`, commit `bd7a6bc`
    ("Bundles for lit@3.3.3", 14 mei 2026), bestanden `core/lit-core.min.js` en `all/lit-all.min.js`. De commits zijn
    van "Lit Robot" en niet ondertekend. jsDelivr (`cdn.jsdelivr.net/gh/lit/dist@3.3.3`) levert hetzelfde bestand,
    byte voor byte.
14. **`lit-core.min.js`**: 15.734 bytes, 6.087 gecomprimeerd (gzip -9), SHA-256
    `f607f470475d8ab790754cb70f72cdbe2390c4a57ab59df6cdb360eeb72bd87c`, licentie BSD-3-Clause in de kop. Hij bevat
    `@lit/reactive-element` 2.1.2, `lit-html` 3.3.3 en `lit-element` 4.2.2 (de versies staan in het bestand), en
    geen `eval` of `new Function`. `lit-all.min.js` (met alle directives) is 29.370 bytes, 10.240 gecomprimeerd.
15. **Zonder `adoptedStyleSheets` zet Lit stijlen als `<style>`-element** in de shadow root (de controle staat aan het
    begin van de bundel). Safari kent `adoptedStyleSheets` pas vanaf 16.4. Op iOS 15 is dat een inline stijl, en de
    CSP uit Fase 5 (`style-src 'self'`, zonder `'unsafe-inline'`) blokkeert die.

### iOS 15 en GitHub

16. **Safari 15.0 mist** wat Safari 15.4 en later brachten: `<dialog>`, `Array.prototype.at()`, `findLast()`,
    `structuredClone()`, `Object.hasOwn()`, `:has()`, `:focus-visible`, `dvh`/`svh`, `inert` (15.5), container queries
    (16.0), import maps (16.4) en CSS nesting. Elke iPhone die iOS 15 draait, kan gratis naar 15.8, maar niet elke
    loper werkt bij.
17. **Een pull request die een workflow opent met `GITHUB_TOKEN` start geen nieuwe workflow-run** (regel van GitHub);
    CI draait er dus niet op. Alleen een persoonlijk token of de sleutel van een GitHub App omzeilt dat.

### Endpoints per scherm

18. Wat de schermen van Fase 5 gebruiken, en wat ontbreekt (vet: nieuw in deze fase, beslissingen 19 tot en met 22):

    | Scherm | Endpoints | Ontbreekt |
    | --- | --- | --- |
    | Inloggen (wachtwoord, code per mail, ingelogd blijven) | `GET /config`, `POST /auth/login`, `POST /auth/login-code`, `POST /auth/login-code/verify`, `GET /me` (ingelogd of niet), `POST /auth/logout` | niets |
    | Welkom (uitnodiging uit de mail) | `POST /auth/invitation`, daarna optioneel `PUT /me/password` | niets |
    | Zoekt een loopmaatje (startscherm) | `GET /staffing-gaps?days=`, `PUT` en `DELETE /rounds/{id}/walkers/me`, `GET /config` (contact, spoed) | **wie al loopt** (beslissing 19) |
    | Ronde (aan- en afmelden, opmerkingen) | `GET /rounds/{id}`, `PUT`/`DELETE /rounds/{id}/walkers/me`, `POST /rounds/{id}/comments`, `DELETE /comments/{id}` | **wat het lid hier mag** (beslissing 21) |
    | Ronde, voor de coördinator | `PUT`/`DELETE /rounds/{id}/walkers/{memberId}`, `GET /members`, `PATCH`/`DELETE /rounds/{id}`, `POST /rounds/{id}/cancellation`, `GET /rounds/{id}/history` | idem |
    | Terugmelden | `GET /me/rounds?view=to_report`, `GET /outcomes`, `POST /rounds/{id}/reports`, `DELETE /reports/{id}` (ongedaan maken) | **de hint** (beslissing 22) |
    | Mijn rondes | `GET /me/rounds?view=upcoming\|to_report\|all`, `DELETE /rounds/{id}/walkers/me` | **de uitkomst van een gelopen ronde** (beslissing 20) |
    | Kalender en ronde toevoegen | `GET /rounds?from=&to=`, `POST /rounds`, `GET /meeting-points` | idem (beslissing 20) |
    | Account | `GET`/`PATCH /me`, `POST /auth/confirm`, `PUT /me/password`, `GET`/`POST`/`DELETE /me/tokens`, `GET /me/activity`, `GET /me/export` | niets |
    | Leden | `GET /members` | niets |
    | Analyse | `GET /reports?from=&to=&outcome=`, `GET /reports/summary` | niets; `/stats` krijgt zijn scherm in U5 |
    | Beheer: instellingen | `GET`/`PATCH /admin/settings` (ook `apiAccess` en `mcpAccess`), `PUT`/`DELETE /admin/settings/images/{logo\|icon}` | niets; de contrastmelding is frontendwerk (beslissing 23) |
    | Beheer: leden | `/admin/members` (lijst, één lid, toevoegen, wijzigen, rollen, (de)activeren, wissen, uitnodigen) | niets |
    | Beheer: startpunten en uitkomsten | `/admin/meeting-points`, `/admin/outcomes` (met archiveren en terugzetten) | niets |
    | Beheer: auditlog | `GET /admin/audit` | niets |

## Voorgestelde beslissingen

### Ontwerp vooraf

1. **Vijf schetsen op telefoonformaat**, met verzonnen voorbeelddata in het Nederlands:
   - **Inloggen**: e-mailadres, "Stuur me een code" als eerste knop, wachtwoord als tweede weg, "ingelogd blijven";
     het scherm met de code van zes cijfers;
   - **Zoekt een loopmaatje**: het startscherm met de drie blokken uit het plan (Zoekt een loopmaatje, Mijn rondes,
     Terugmelden), per open ronde datum, tijd, startpunt en wie al loopt, en "Ik loop mee";
   - **Ronde**: aangemeld, met "Ik kan toch niet"; de waarschuwing vooraf als de ronde daardoor een loopmaatje zoekt;
     "Afgemeld. Ongedaan maken";
   - **Terugmelden**: de uitkomsten als grote keuzes, notitie optioneel met de hint, bevestiging in woorden;
   - **Mijn rondes**: komende rondes en rondes die nog een terugmelding nodig hebben.
   Breedte 360 CSS-pixels (het kleinste gangbare Android-formaat), gecontroleerd op 320.
2. **Formaat: statische HTML met één stylesheet**, zonder JavaScript en zonder buildstap, in `docs/design/sketches/`
   (`login.html`, `staffing-gaps.html`, `round.html`, `round-withdraw.html`, `report.html`, `my-rounds.html`,
   `sketch.css`). Knoppen zijn links naar de volgende toestand, dus de schets is door te tikken. Kleuren, maten en
   lettergrootte (18 px basis, tikdoelen 44 px) staan als custom properties in `sketch.css`, zodat `frontend/app.css`
   ermee begint. Alternatief: Penpot of Figma; dan staat het ontwerp buiten de repo en vraagt het een account.
3. **Twee testlopers bekijken ze op hun eigen telefoon**, samen met de ontwikkelaar, elk apart. De schetsen gaan als
   privé Artifact-link (alleen na jouw akkoord: het verlaat de machine, met alleen verzonnen data); wie liever op papier
   kijkt, krijgt een A4-afdruk op telefoongrootte. Elke loper krijgt de drie opdrachten uit het plan (meld je aan bij
   een ronde die een loopmaatje zoekt, meld je weer af, geef door dat je niets bijzonders zag) en denkt hardop. De
   ontwikkelaar noteert waar iemand aarzelt of iets anders verwacht; de bevindingen gaan zonder namen ("loper A, minst
   ervaren", "loper B") in `docs/design/sketch-review.md`, met wat er in de schetsen veranderde. Pas daarna begint de
   proef. Alternatief: GitHub Pages; dat is openbaar.

### Lit-proef

4. **Lit 3.3.3, de bundel `lit-core.min.js`** (vastgesteld 12 tot en met 14). De nieuwste release, 188 dagen oud, dus
   ruim buiten de cooldown van ADR 0004 (7 dagen voor patch en minor, 14 voor een major); geen nieuwere versie om op
   te wachten. `lit-core` en niet `lit-all`: de directives in `lit-all` (onder meer `repeat`, `live`, `classMap`)
   kosten 4 KB gecomprimeerd extra, en de proef laat zien of er een nodig is. Blijkt dat zo, dan wisselen we in de
   evaluatie, met dezelfde controle.
5. **Het bestand komt gecontroleerd binnen met `php tools/vendor-lit.php <versie>`** (PHP, zonder nieuwe
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
6. **Alle componenten in de light DOM** (`createRenderRoot()` geeft het element zelf) met één stylesheet `app.css`
   via `<link>`. Drie redenen: de CSP op iOS 15 (vastgesteld 15), `<label for>` en `aria-describedby` werken niet over
   een shadow-grens (plan, Valkuil bij Lit), en de kleuren van de vereniging gelden overal. Kosten: geen ingekapselde
   stijlen, dus elke component krijgt een eigen klassevoorvoegsel. Alternatief: shadow DOM met `static styles`; dan
   vraagt iOS 15 `'unsafe-inline'` of een nonce per antwoord, en een statische `.htaccess` kan geen nonce maken.
7. **Geen decorators** (die vragen een buildstap): `static properties`. Eigen code blijft binnen ES2021 en de
   grenzen van beslissing 26.
8. **Omvang van de proef**: de app-shell (kop met naam of logo uit `/config`, de kleuren, vaste plek voor contact en
   "wat te doen bij spoed", taal), de router, de API-client met CSRF-header en problem+json, de vertalingen, inloggen
   (wachtwoord en code per mail, ingelogd blijven) en "Zoekt een loopmaatje" met "Ik loop mee" en ongedaan maken.
   Niet in de proef: service worker en de andere schermen. De proef draait onder de CSP van beslissing 30 en met de
   browsertests van beslissing 28, zodat die niet achteraf knellen.
9. **Evaluatie**, in dit plan onder T7, daarna wacht ik op je akkoord:
   - werkt op een iPhone met iOS 15 (met de hand, door jou: aanmelden in drie tikken, inloggen met een code);
   - axe-core, toetsenbordtest en CSP schoon in Chromium en WebKit;
   - grootte van de app-shell, en regels eigen code per scherm;
   - wat lastig was: toestand tussen schermen, formulieren, focus na een routewissel, meldingen voor schermlezers,
     foutafhandeling;
   - het oordeel: verder met Lit, of nu nog overstappen naar het alternatief uit het plan (Preact met htm).
   Na fase 5 legt ADR 0009 het vast.

### Gevendorde bibliotheken bijwerken

10. **Renovate, zoals het plan voorstelt, ter goedkeuring**: zelf gedraaid in GitHub Actions met
    `renovatebot/github-action` (vastgepind op SHA), wekelijks, met een regex-manager op `frontend/vendor/vendor.json`
    (datasource `github-tags`, `lit/dist`), `minimumReleaseAge: 7 days` en `postUpgradeTasks` die
    `tools/vendor-lit.php` draaien. Nooit de gehoste app van Mend (ADR 0004). Het knelpunt: met `GITHUB_TOKEN` start
    de pull request van Renovate geen CI (vastgesteld 17). Wil je CI op die pull request, dan moet er een persoonlijk
    token of de sleutel van een GitHub App met schrijfrechten als geheim in de repo, precies het risico waarom ADR 0004
    de app van Mend uitsluit. Renovate zelf is een groot Node-pakket dat dan wekelijks met dat geheim draait, voor één
    bestand met twee releases per jaar.
11. **Het alternatief, mijn voorstel: een wekelijkse controle die een issue opent**, zoals bij een nieuwe
    Opengrep-major (ADR 0004). Een job `vendored` in `weekly.yml` leest `frontend/vendor/vendor.json`, vraagt de tags
    van `lit/dist` op, en opent of werkt een issue bij (met `.github/scripts/report-issue.sh`, alleen `issues: write`)
    zodra er een nieuwere release is die 7 dagen oud is; een nieuwe major krijgt een eigen issue. Bijwerken is
    `php tools/vendor-lit.php <versie>` en een pull request, waarop CI gewoon draait. Geen geheimen, geen nieuwe tool.
    Leaflet (U9) krijgt later een tweede regel in `vendor.json` en hetzelfde script. Nadeel: geen automatische pull
    request; bij twee releases per jaar is dat één handeling per half jaar. Wekelijks, niet maandelijks: de job draait
    mee in de bestaande run van `weekly.yml` en kost één API-aanroep, en een beveiligingsfix in Lit wacht zo hooguit
    een week na de cooldown in plaats van een maand.
12. **De Node-pakketten voor de browsertests** (beslissing 28) volgt Dependabot wel: een npm-ecosysteem op
    `/tests/browser` met dezelfde cooldown als Composer (7, 7 en 14 dagen); `dependabot-automerge.yml` behandelt ze
    zoals Composer-pakketten.

### Opbouw van de frontend

13. **Indeling**:

    ```text
    frontend/
      index.html          geen inline script of stijl
      .htaccess           beveiligingsheaders (beslissing 30)
      app.css
      unsupported.js      ES5, alleen voor te oude browsers (beslissing 27)
      sw.js               service worker (na de proef)
      js/main.js, js/api.js, js/router.js, js/i18n.js, js/session.js
      js/screens/<scherm>.js, js/components/<component>.js
      i18n/nl.json, i18n/en.json
      vendor/lit-core.min.js, vendor/vendor.json
    ```
    Lit wordt relatief geïmporteerd (`../vendor/lit-core.min.js`), zonder import map.
14. **Lokaal**: een symlink `public/app` naar `../frontend`, in de repo, zodat de Symfony-server de app op `/app/`
    serveert. Of de Symfony CLI `index.html` voor `/app/` geeft en `.htaccess` negeert, controleer ik in T5; zo niet,
    dan draait lokaal dezelfde PHP-router als in CI (beslissing 28). De release (fase 6a) kopieert in plaats van te
    linken.
15. **Hash-router** met de routes uit de mails (vastgesteld 2): `#/`, `#/login`, `#/welcome/<geheim>`,
    `#/rounds/<id>`, `#/my-rounds`, `#/report/<id>`, `#/calendar`, `#/account`, `#/members`, `#/analysis`, `#/admin/…`,
    `#/help`. Na elke routewissel gaat de focus naar de kop van het scherm en verandert de `<title>`.
16. **Eén API-client** (`js/api.js`) met alle paden als sjablonen in één tabel, relatief (`../api/v1/…`), zodat de app
    ook in een pad werkt. Bij elk schrijvend verzoek een `csrf-token`-header zoals Symfony's eigen
    `csrf_protection_controller.js` dat doet (vastgesteld 4). Een problem+json wordt een fout met `code` en
    veldfouten; een 401 gaat naar inloggen en wist de cache van de service worker.
17. **Vertalingen** in `frontend/i18n/nl.json` en `en.json`; de taal komt uit `/config` en staat in `lang` op `<html>`.
    `TranslationKeysTest` controleert ook deze twee bestanden.
18. **Kleuren via CSSOM** (`document.documentElement.style.setProperty`), wat de CSP toestaat; donkere modus kiest de
    donkere varianten met `prefers-color-scheme`. Systeemlettertype, 18 px basis, `rem` overal.

### Ontbrekende velden en endpoints

Elke wijziging gaat test eerst, met OpenAPI, Hurl en de pariteitstest van de MCP-tool die dezelfde handler gebruikt.

19. **`/staffing-gaps` krijgt `walkers`**: de `displayName` van elke actieve loper (plan: "wie al loopt"). Ook in
    `staffing_gaps` via MCP; een weergavenaam is geen contactgegeven. Alternatief: per open ronde `GET /rounds/{id}`;
    dat zijn N verzoeken op het startscherm, met telefoonnummers die het scherm niet nodig heeft.
20. **`/rounds` en `/me/rounds` krijgen `outcomeKind`** (`nothing_to_report`, `remarks`, `incident` of `null` zonder
    terugmelding), zodat kalender en Mijn rondes "Gelopen, geen bijzonderheden", "Incident gemeld" of "Nog geen
    terugmelding" tonen. Elk lid ziet de uitkomst al in `GetRound` (vastgesteld 7). `reported` blijft in `/me/rounds`.
21. **`GET /rounds/{id}` krijgt `actions`**: de lijst van wat het vragende lid met deze ronde mag (`sign_up`,
    `withdraw`, `sign_up_others`, `change`, `delete`, `cancel`, `report`, `comment`), uitgerekend met dezelfde Voters.
    Zo toont de frontend alleen knoppen die werken, zonder de regels te kopiëren. Alternatief: de frontend rekent het
    zelf uit rollen en status; dan staat elke regel twee keer en loopt hij uit elkaar.
22. **De hint bij een notitie of opmerking is een vaste tekst** in de vertalingen, onder het veld en gekoppeld met
    `aria-describedby`: "Schrijf geen namen, kentekens of signalementen. Zag je iets strafbaars? Geef het direct door
    aan de politie.", gevolgd door de spoedtekst uit `/config`. Geen nieuwe instelling. Alternatief: een instelbare
    hint per taal (plan: "instelbare tekst"); de spoedtekst is al instelbaar en noemt de nummers.
23. **De contrastmelding in gewone taal**: bij 422 `insufficient_contrast` zegt het beheerscherm per veld "Deze kleur
    is te licht op wit. Kies een donkerdere kleur." (of "te donker op de donkere achtergrond"), met de gemeten en de
    vereiste verhouding er klein onder.
24. **De schakelaars voor API en MCP** staan op het beheerscherm van de instellingen, met de uitleg dat MCP standaard
    uit staat en ook niet werkt als API-tokens uit staan (fase 4, beslissing 8).
25. **Elk scherm van de oude app een tegenhanger**:

    | Oude app (`templates/`) | Nieuw scherm |
    | --- | --- |
    | `security/login*`, `security/reset`, `security/token` | Inloggen (code per mail vervangt de reset), Welkom |
    | `security/account`, `password-change`, `permitted-change` | Account |
    | `page/home` | Zoekt een loopmaatje (start) |
    | `walk/calendar`, `calendar-day`, `calender-new-round` | Kalender, ronde toevoegen |
    | `walk/detail`, `round/detail`, `round/change-form` | Ronde (wijzigen voor de coördinator) |
    | `walk/walked`, `walk/result-form`, `result/modal*`, `result/inline` | Mijn rondes, Terugmelden |
    | `result/list`, `round/list` | Analyse, Kalender |
    | `member/list`, `member/form` | Leden, Account |
    | `user/*` | Beheer: leden (met uitnodigen) |
    | `config/meetingpoint_*`, `config/result_*` | Beheer: startpunten, uitkomsten |
    | nieuw | Beheer: instellingen, auditlog; Hulp ("Hoe werkt het?") |

### Toegankelijkheid en oudere toestellen

26. **Ondergrens Safari 15.0.** Eigen code gebruikt niets uit vastgesteld 16: geen `<dialog>` (een eigen
    bevestigingsblok in de pagina), `:focus` in plaats van `:focus-visible`, `vh` met een terugval. `FrontendSourceTest`
    (PHPUnit) zoekt die namen in `frontend/` en faalt erop; de syntax controleert `acorn` in CI tegen ES2021.
    Alternatief: ondergrens 15.4, met de melding "Werk je iPhone bij"; dat spaart weinig en raakt juist de minst
    ervaren loper.
27. **Te oude browser**: `<script nomodule src="unsupported.js">` (ES5) zet een melding in gewone taal in de pagina:
    wat er aan de hand is, wat te doen, en "vraag de coördinator van je buurtpreventie". Ook een `<noscript>`-tekst.
28. **Browsertests in CI**, een nieuwe job "Browser" naast Checks en Database:
    - MariaDB-container, migraties, `app:fixtures:load test`, en de PHP-server met een router
      (`tests/browser/server.php`) die `frontend/` op `/app/` serveert met de headers uit `frontend/.htaccess`, en
      `/api/` doorgeeft aan `public/index.php`;
    - Playwright in Chromium en WebKit, op 360 × 740 en 1280 × 800, licht en donker;
    - `@axe-core/playwright` op elk scherm en elke toestand (fout in een veld, waarschuwing open, bevestiging),
      tags `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`, `wcag22aa`, contrastregel aan; elke overtreding faalt;
    - wat axe niet ziet, als eigen test: de drie opdrachten uit het plan met alleen Tab, Enter en spatie; focus
      zichtbaar en niet onder de vaste kop (2.4.11); tikdoelen minstens 24 px, hoofdacties 44 px; geen horizontaal
      scrollen op 320 px en met tekst op 200 %; een `aria-live`-melding na elke actie; `lang` op `<html>`;
    - een iOS-15-verzoek nagespeeld: zonder `Origin` en `Sec-Fetch-Site`, en toch aangemeld (vastgesteld 4);
    - elke CSP-overtreding (`securitypolicyviolation`) en elke consolefout faalt de test;
    - `acorn` controleert elk JavaScript-bestand tegen ES2021, `unsupported.js` tegen ES5.
    Geen geheimen, draait op elke pull request. Lokaal optioneel (`npm ci` in `tests/browser`), niet op de server.
29. **Met de hand, per release**: een checklist in `docs/testing.md` (alleen toetsenbord, VoiceOver op een iPhone,
    NVDA op Windows, 400 % zoom). Een echte iPhone met iOS 15 test jij bij de proef en vóór de merge; CI heeft geen
    Safari 15 (WebKit in Playwright is de nieuwste), en een dienst als BrowserStack valt af (account, externe dienst).

### Beveiligingsheaders voor `frontend/`

30. **`frontend/.htaccess`** met `Header always set`:
    - `Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:;
      connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` zoals het plan, aangevuld met
      `object-src 'none'`, `manifest-src 'self'` en `worker-src 'self'` (alle drie vallen nu terug op `default-src`;
      expliciet zegt wat de bedoeling is);
    - `Permissions-Policy: camera=(), microphone=(), geolocation=()` zoals het plan; U7 en U9 zetten er later `self`
      neer;
    - daarnaast, als bij de API: `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`,
      `Cross-Origin-Opener-Policy: same-origin`;
    - `Cache-Control: no-cache` op alles (zonder buildstap hebben bestanden geen hash in de naam, dus de browser vraagt
      met een ETag na; offline helpt de service worker).
    HSTS zet de API al voor de hele host (`HSTS_MAX_AGE`), dus niet nog eens hier. `FrontendHeadersTest` leest
    `.htaccess` en eist precies deze headers, zonder `'unsafe-inline'` of `'unsafe-eval'`; de browsertests draaien
    achter dezelfde headers. Of Apache ze op een echte host zet, controleert de installer in fase 6a.

### PWA (na de proef)

31. **Service worker `sw.js` in `app/`**, scope `app/` (fase 3, beslissing 13). In de cache: de app-shell (een lijst
    met versienummer) en alleen `GET /me/rounds` en `GET /rounds`: geen namen, geen contactgegevens. Nooit
    `/rounds/{id}` (telefoon), `/staffing-gaps` (namen, beslissing 19), `/members`, `/me`, `/config`, `/admin/`,
    `/auth/`. Gegevens netwerk eerst; offline toont de app "Je bent offline. Bijgewerkt om 20:14." Uitloggen en elke
    401 wissen de cache. Een nieuwe versie zegt "Nieuwe versie beschikbaar, vernieuwen", nooit midden in een formulier.

### Overige tests

32. PHPUnit, in `composer check`, zonder Node:
    - `VendoredLibraryTest` (beslissing 5);
    - `FrontendSourceTest`: geen `<script>` zonder `src`, geen `style=`-attribuut, geen `on…=`-attribuut in HTML,
      geen namen uit beslissing 26;
    - `FrontendHeadersTest` (beslissing 30);
    - `AppShellSizeTest`: de shell (HTML, CSS, JS en Lit) samen onder 100 KB met gzip;
    - `FrontendApiContractTest` (regel 6): elk pad uit de tabel in `js/api.js` staat in de OpenAPI-spec met dezelfde
      methode;
    - `TranslationKeysTest` ook over `frontend/i18n/`.

## Nieuwe afhankelijkheden (ter goedkeuring)

Alleen voor tests in CI, in `tests/browser/package.json` met `package-lock.json`, geïnstalleerd met
`npm ci --ignore-scripts`; niets daarvan gaat mee in de release. Versies en datums van npm op 18 november 2026;
cooldown 7 dagen voor patch en minor.

| Pakket | Waarvoor | Licentie | Versie (datum, leeftijd) | Buiten de cooldown |
| --- | --- | --- | --- | --- |
| `@playwright/test` (met `playwright`, `playwright-core`) | Browsertests in Chromium en WebKit | Apache-2.0 | 1.65.0 (5 nov 2026, 13 dagen) | ja; 1.65.1 (13 nov, 5 dagen) nog niet |
| `@axe-core/playwright` | axe-core in Playwright | MPL-2.0 | 4.14.0 (15 okt 2026, 34 dagen) | ja |
| `axe-core` (indirect, `~4.14.0`) | De regels van axe | MPL-2.0 | 4.14.0 (5 okt 2026), vastgezet in de lockfile | ja; 4.14.1 (16 nov, 2 dagen) nog niet |
| `acorn` | ES2021- en ES5-controle | MIT | 8.19.0 (5 okt 2026, 44 dagen) | ja |

- De browsers haalt `npx playwright install --with-deps chromium webkit` in CI; niet lokaal nodig.
- MPL-2.0 geldt per bestand en alleen bij verspreiden; axe-core draait in CI en wordt niet verspreid.
- Lit 3.3.3 (BSD-3-Clause) is geen pakket maar een gevendord bestand (beslissingen 4 en 5); de licentie staat in de
  kop van het bestand en in `vendor.json`.
- Renovate (beslissing 10) zou daarnaast `renovatebot/github-action` toevoegen; met het alternatief (beslissing 11)
  niets.

## Taken

Elke taak is één of enkele kleine commits; tests komen vóór de code.

### T1. Branch en taakplan

`feature/phase-5` vanaf `develop`; dit plan. Bouwen pas na akkoord.

### T2. Schetsen

- De vijf schetsen van beslissingen 1 en 2 in `docs/design/sketches/`, gecontroleerd op 320 en 360 px, met
  toetsenbord en met axe (met de hand).
- `docs/design/sketch-review.md` met de drie opdrachten en een lege tabel voor de bevindingen.
- Daarna: jij houdt de sessies (beslissing 3); ik verwerk de bevindingen in de schetsen.

### T3. Lit vendoren (test eerst)

- `VendoredLibraryTest`, dan `tools/vendor-lit.php` en `php tools/vendor-lit.php 3.3.3`.
- De job `vendored` in `weekly.yml` (beslissing 11).

### T4. Ontbrekend veld voor de proef (test eerst)

- `walkers` in `/staffing-gaps` en `staffing_gaps` (beslissing 19), met OpenAPI, Hurl en pariteit.

### T5. Basis van de frontend (test eerst)

- `FrontendSourceTest`, `FrontendHeadersTest`, `AppShellSizeTest`, `FrontendApiContractTest`, de uitbreiding van
  `TranslationKeysTest`.
- `frontend/` volgens beslissing 13, `.htaccess` (beslissing 30), `public/app` (beslissing 14), `unsupported.js`
  (beslissing 27).
- `tests/browser/` met de pakketten uit de tabel, de router, de job "Browser" in `ci.yml` en Dependabot voor npm
  (beslissing 12).

### T6. Lit-proef (test eerst)

- Browsertests eerst: inloggen met wachtwoord, met een code, een verkeerde code, ingelogd blijven, uitloggen;
  aanmelden vanaf het startscherm in één tik met ongedaan maken; de iOS-15-variant zonder `Origin`; axe en toetsenbord
  op elke toestand.
- App-shell, router, API-client, vertalingen, inloggen en "Zoekt een loopmaatje" (beslissing 8).
- `docs/frontend.md`: de patronen uit de proef (light DOM, eigenschappen, gebeurtenissen, formulieren, focus en
  `aria-live`, fouten, vertalingen).

### T7. Evaluatie

- De punten van beslissing 9, met jouw test op iOS 15, hier in het plan. Daarna stop ik tot je akkoord.

### T8. Overige ontbrekende velden (test eerst)

- `outcomeKind` (beslissing 20) en `actions` (beslissing 21), met OpenAPI, Hurl, pariteit en de matrixtests waar de
  Voters meedoen.

### T9. Ronde, Mijn rondes en Terugmelden (test eerst)

- Ronde met aan- en afmelden, waarschuwing vooraf, ongedaan maken (niet na het afmelden van de laatste loper: de ronde
  vervalt dan, en het scherm zegt dat vooraf), opmerkingen met de hint (beslissing 22).
- Mijn rondes, Terugmelden met ongedaan maken.

### T10. Kalender en coördinator (test eerst)

- Lijst per dag op een telefoon, maandraster met dagdelen op een breed scherm, pijltjestoetsen; ronde toevoegen met
  startpunt en opmerking.
- Namens een loper aan- en afmelden, ronde wijzigen, annuleren met reden, verwijderen, geschiedenis.

### T11. Account, Welkom en Leden (test eerst)

- Account: contactgegevens, toestemming, meldingen over open rondes, wachtwoord (met opnieuw bevestigen), tokens (het
  geheim één keer getoond), eigen activiteit, gegevens downloaden.
- Welkom via de uitnodigingslink; Leden met contactgegevens alleen bij toestemming.

### T12. Analyse en beheer (test eerst)

- Analyse: terugmeldingen met filter en de samenvatting.
- Beheer: instellingen met uploads, contrastmelding (beslissing 23) en de schakelaars (beslissing 24); leden met rollen,
  (de)activeren, wissen en uitnodigen; startpunten en uitkomsten met archiveren; het auditlog.

### T13. PWA, hulp en handleiding (test eerst)

- Manifest-link, service worker en offline lezen (beslissing 31), met tests voor wat wel en niet in de cache komt en
  het wissen bij uitloggen.
- "Hoe werkt het?" in de app, de link naar privacy- en toegankelijkheidsverklaring.
- `docs/user-guide/`: één A4 voor lopers met schermafbeeldingen uit de browsertests.

### T14. Documentatie

- `docs/adr/0009-lit-frontend.md` (de evaluatie), `docs/frontend.md`, `docs/architecture.md` (regel 6, de frontend,
  `actions`), `docs/testing.md` (browsertests en de checklist met de hand), `docs/configuration.md`,
  `docs/glossary.md`, `docs/gotchas.md`; `plan.md` (Fase 5 afvinken, afwijkingen, endpointtabel, Fasen en stand);
  `CHANGELOG.md`; `AGENTS.md` (Commands: browsertests).

### T15. Verifiëren en opleveren

- `composer test` zonder database, `symfony composer check`, de browsertests; uitvoer in het verslag, met elke check die
  niet draaide.
- Met de hand: alleen toetsenbord en een smal scherm op elk scherm; jouw test op een iPhone met iOS 15; de test met
  twee lopers op hun eigen telefoon met voorbeelddata (plan, Testen).
- Push, titel en beschrijving van de pull request; openen na jouw ja; CI volgen en squash-mergen zodra alles groen is.

## Afspraken

1. Claude commit op `feature/phase-5` in kleine commits en pusht de branch. De pull request gaat pas open na akkoord;
   de merge volgt als squash zodra CI op de laatste commit groen is.
   - Geen `cd`, `pushd`, `-C`, `--git-dir`, `--work-tree` of `GIT_DIR=` in dezelfde aanroep als `git add` of
     `git commit`.
   - Alleen de bestanden van die commit stagen, met letterlijke paden; daarna `git show --stat HEAD`.
2. Eerst schetsen (T2) en de proef (T3 tot en met T7); de overige schermen pas na akkoord op de evaluatie.
3. `.env`, `.env.dev` en `.env.test` alleen lezen met `git show HEAD:<bestand>`; `.env.local`, `.env.*.local` en
   `shared/.env.local` nooit lezen of committen.
4. PHPUnit, `symfony console`, Mago, Rector, PHPStan, Composer, npm, `git push` en `gh` draaien buiten de sandbox; logs
   in de scratchpad.
5. Database, server en Mailpit start de ontwikkelaar. Draait er één niet, dan meldt Claude welke en draait de tests
   die hem nodig hebben niet. Voorbeelddata in de dev-database alleen na vragen.
6. Nieuwe afhankelijkheden pas na akkoord op de tabel hierboven; de Artifact-link voor de testlopers pas na akkoord.
7. Na elke taak wordt de tabel "Fasen en stand" in `docs/plans/plan.md` bijgewerkt.

## Open punten

Gemeld, niet in deze fase opgelost:

- **Opengrep scant geen JavaScript** (vastgesteld 11). Regels voor `frontend/` (bijvoorbeeld `innerHTML`,
  `unsafeHTML`, `eval`) kunnen erbij als de frontend groeit.
- **Het statistiekscherm** op `/stats` blijft U5; offline terugmelden U6; pushmeldingen U4.
- **De release kopieert `frontend/` naar `public/app/`** en de installer controleert de headers op een echte host:
  fase 6a.
- **Leaflet** (U9) komt als tweede regel in `frontend/vendor/vendor.json`, met hetzelfde script.
