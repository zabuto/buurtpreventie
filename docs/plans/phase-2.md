# Fase 2: taakplan

Status: goedgekeurd op 15 november 2026.

Bron: `docs/plans/plan.md` (Samenvatting, API en MCP in Symfony, Domeinmodel en API-ontwerp, Auditlog, Privacy en
AVG, Overdraagbaarheid, Fase 2, Risico's en review-focus), `docs/plans/phase-1.md` (T7 tot en met T16 en alles wat
naar fase 2 verwijst), `docs/architecture.md`, `docs/testing.md`, ADR 0001 tot en met 0005, `AGENTS.md`, en de code
op `develop`.

## Doel en klaar-criterium

De REST-API `/api/v1` staat: inloggen in de browser (wachtwoord, code per mail, uitnodiging, ingelogd blijven) en met een persoonlijk
API-token, en een controller per use case voor elk endpoint uit het API-ontwerp dat bij deze fase hoort, met
`problem+json`-fouten en een OpenAPI-spec.

Klaar als:

- de OpenAPI-spec op `/api/v1/docs.json` elk endpoint dekt, en een test faalt als een route ontbreekt in de spec of
  andersom;
- de HTTP-matrixtest (rol × endpoint) en de scopetest (scope × methode) groen zijn, naast de bestaande matrixtest op
  de handlers;
- `curl` met een token een lid aanmeldt voor een ronde, als databasetest en met de hand tegen de demo;
- review-focus 2, 3 en 4 voor deze fase een test hebben (T9, T11, T5): dezelfde rondes rond de tijdwissel via de
  API, een lid zonder toestemming toont nooit telefoon, e-mail of adres, en een ingetrokken token of gedeactiveerd lid
  wordt meteen geweigerd, ook in een lopende sessie;
- elke nieuwe schrijvende handler een auditregel, een Voter en rijen in de matrixtest heeft;
- ADR 0006 het inloggen vastlegt;
- `symfony composer check` groen is, `composer test` zonder database ook, en de pull request naar `develop` groen en
  gemergd is.

## Vastgesteld in de code

1. **Wie de actie uitvoert, komt uit de token-opslag.** `TokenExecutionContext` leest kanaal (tokenattribuut
   `channel`) en lid (`MemberUser`); zonder kanaal gooit hij een `LogicException`. Een authenticator moet dus
   `MemberUser` als gebruiker zetten en het kanaal als attribuut.
2. **`MemberUser` draagt alleen lid-id en rollen**, geen wachtwoordhash. Symfony's `json_login` vraagt een gebruiker
   met `PasswordAuthenticatedUserInterface`.
3. **Identity heeft `Account` (hash, laatste login) en `ApiToken`** (SHA-256 als `BINARY(32)`, scopes, verloop,
   intrekking, `markUsed()`, `isUsableAt()`). Er is geen use case om tokens te tonen, geen inloglink en geen
   wachtwoord-reset. `CreateApiToken` laat de verloopdatum leeg toe: zo'n token verloopt nooit.
4. **`VerifyPassword` vernieuwt geen hash.** Het plan wil een v3-hash bij inloggen vernieuwen; dat ontbreekt nog.
5. **`SetPassword` vraagt het huidige wachtwoord niet.** Het plan vraagt opnieuw het wachtwoord bij gevoelige acties.
6. **Membership kan een lid niet op e-mailadres vinden**: `MemberRepository` heeft geen `ofEmail()`, en
   `MemberDirectory` geen opzoeking voor inloggen.
7. **`GetMemberSummaries` geeft ook inactieve leden** (met `active`) en geen contactgegevens. T7 van fase 1 vraagt
   dat de ledenlijst gedeactiveerde leden weglaat; het plan vraagt contactgegevens bij toestemming.
8. **Domeinfouten zijn losse `DomainException`-klassen** in de modules (`RoundIsFull`, `LastAdmin`, ...).
   `Shared\Infrastructure` mag geen module kennen (regel 4), dus een foutvertaler daar kan ze niet bij naam vangen.
9. **Eigen acties nemen het lid uit de uitvoerder als het id leeg is** (`SignUpForRound`, `CreateRound`), maar
   `ChangeContactConsent`, `ChangeOpenRoundNotifications`, `CreateApiToken` en `SetPassword` eisen een `memberId`.
   Een controller kent de uitvoerder niet (regel 3).
10. **Regel 3 laat in een controller alleen HttpFoundation, `HttpKernel\Attribute` en `Routing\Attribute` toe.**
    OpenAPI-attributen vallen daarbuiten.
11. **Er zijn geen vertalingen**: geen `symfony/translation`, geen `translations/`. Validatorfouten zijn Engels.
12. **De `LoggingNotifier` logt alle parameters.** Een code of uitnodigingsgeheim in de parameters komt zo in het log.
13. **Lezen ontbreekt voor**: eigen rondes, startpunten en uitkomsten voor een lid, terugmeldingen voor de analist,
    tokens van een lid, het eigen profiel, en het auditlog (`AuditEntryRepository` kent alleen `eraseActorName()`).
14. **Auditregels van aanmelden en terugmelden hebben de ronde als onderwerp** (`'round'`, ronde-id), dus de
    geschiedenis van een ronde is één query op onderwerp.
15. **Voorbeeldleden hebben geen wachtwoord** en het token van het demolid is na het laden onbekend.

## Voorgestelde beslissingen

### Inloggen

1. **Twee firewalls op `/api/v1`, gekozen op de header.**
   - `api_token`: verzoeken met `Authorization: Bearer`, stateless, zonder sessie en zonder CSRF. Eén authenticator
     die elk verzoek op deze firewall aanneemt, zoals vastgelegd in Fase 2 van het plan.
   - `browser`: al het andere op `/api/v1`, met sessie, CSRF en ingelogd blijven. Een verzoek zonder sessie op een
     beschermd endpoint krijgt 401 via het entry point, dat het ook logt.
   - Fase 4 zet `/mcp` op een eigen tokenfirewall met kanaal `mcp`, met dezelfde authenticator.
2. **Een eigen authenticator voor tokens, geen `access_token` met handler.** `access_token` slaat een verzoek zonder
   header over, zodat een ontbrekend token niet in ons log komt; de eigen versie is één klasse met de `match` over de
   redenen uit het plan (onbekend, ingetrokken, verlopen, lid gedeactiveerd of gewist). Weigering: 401 zonder body,
   met `WWW-Authenticate: Bearer`; de reden alleen in het log. `markUsed()` schrijft hooguit eens per minuut, zodat
   niet elk verzoek een schrijfactie is; dat is technische staat, zonder auditregel.
3. **Een eigen authenticator voor het wachtwoord, geen `json_login`** (vastgesteld 2): `MemberUser` blijft zonder
   hash. `POST /auth/login` met `{email, password}`. Een onbekend adres doet een hashcontrole op een vaste dummyhash,
   zodat de responstijd niet verraadt of een adres bestaat. Bij succes vernieuwt de authenticator een verouderde hash
   (vastgesteld 4) en zet `Account.lastLoginAt`. De melding bij falen is altijd dezelfde.
4. **Identity vindt een lid via `Shared\Domain\MemberDirectory`, niet via een use case.** Afwijking van fase 1: de
   user provider zoekt bij elk verzoek met sessie het lid op (actief, rollen). Een use case vraagt een Voter-besluit
   op een moment dat niemand ingelogd is, dus `RunAs::system()` bij elk verzoek. `MemberDirectory` krijgt
   `identify(EmailAddress): ?MemberIdentity` en `identity(Uuid): ?MemberIdentity` (lid-id, rollen, actief), uit
   Membership, met een repositorymethode `ofEmail()` en een integratietest.
5. **Elke sessie wordt per verzoek ververst.** `refreshUser()` laadt rollen en status opnieuw; een gedeactiveerd of
   gewist lid valt meteen uit de sessie (review-focus 4), een rolwijziging werkt bij het volgende verzoek.
6. **Kanaal:** `web` voor de browserfirewall, `api` voor tokens, `mcp` in fase 4.
7. **CSRF met `symfony/security-csrf` (`SameOriginCsrfTokenManager`)**, op elk verzoek van de browserfirewall met een
   muterende methode, ook `POST /auth/login` (login-CSRF). Hij controleert `Sec-Fetch-Site` en `Origin`, en valt terug
   op een cookie plus header die de frontend zet. Die terugval is nodig: Safari op iOS 15 stuurt geen
   `Sec-Fetch-Site`. Een eigen controle op alleen `Origin` zou die toestellen ofwel buitensluiten ofwel niet
   beschermen.
8. **Sessiecookie** `Secure`, `HttpOnly`, `SameSite=Lax`, eigen naam (`bp_session`), sessies in bestanden onder
   `var/`; een nieuwe sessie-id na inloggen. De map in `shared/` volgt met de mapindeling in fase 6a.
9. **Ingelogd blijven 90 dagen met Symfony's `remember_me`, op handtekening.** De handtekening dekt het lid-id en een
   nieuw veld `Account.credentialsChangedAt`, dat een nieuw wachtwoord zet: dan vervalt elk onthouden apparaat. Een gedeactiveerd lid valt via beslissing 5 af. Alternatief: tokens in de database (meer tabellen en
   opruimwerk) of geen "onthouden" (tegen het plan).
10. **Inloggen per mail met een cijfercode, niet met een link.** Een code van 6 cijfers die het lid overneemt in
    het scherm waar het hem aanvroeg. Dat is de extra controle: de code werkt alleen samen met de aanvraag uit die
    browser, dus een doorgestuurde mail, een vooraf geopende link van een virusscanner of iemand die alleen de mail
    ziet, logt niet in. Afwijking van het API-ontwerp (`POST /auth/login-link`, `GET /auth/login-link/{token}`):
    - `POST /auth/login-code` met `{email}` geeft altijd 202 met een `challengeId` (128 willekeurige bits), ook bij een
      onbekend of inactief adres, zodat het antwoord niet verraadt of een adres bestaat; de frontend bewaart het id in de browser;
    - `POST /auth/login-code/verify` met `{challengeId, code}` logt in;
    - **er is geen aparte wachtwoord-reset.** "Wachtwoord vergeten" is inloggen met een code; daarna zet het lid in
      de recente sessie een nieuw wachtwoord zonder het oude (beslissing 16). Eén soort code met één termijn is
      makkelijker uit te leggen, en even veilig: een recente sessie geeft dezelfde macht als een reset-code, en een
      nieuw wachtwoord logt elk onthouden apparaat uit. Afwijking van het API-ontwerp
      (`POST /auth/password-reset`, `POST /auth/password-reset/{token}`). Een wachtwoord is nooit verplicht.
11. **Bruikbaar voor elke loper.** De code volgt WCAG 2.2 succescriterium 3.3.8 (toegankelijk inloggen): overnemen
    met plakken en automatisch invullen mag, er is geen puzzel.
    - Zes cijfers, geen letters: geen verwarring tussen `0` en `O` of `1` en `l`, en op een telefoon het
      cijfertoetsenbord (`inputmode="numeric"`, `autocomplete="one-time-code"`).
    - Eén invoerveld, geen zes losse vakjes: die breken plakken, schermlezers en vergroting. Spaties en streepjes in
      wat het lid typt of plakt, tellen niet mee.
    - In de mail groot en in twee groepen (`482 913`), bovenaan, met in gewone taal waar hij in te vullen is; de code
      niet in het onderwerp, want dat staat in meldingen op een vergrendeld scherm.
    - Het scherm noemt het adres waar de code heen ging, met "Code niet ontvangen? Stuur een nieuwe" (na 60 seconden)
      en "Kijk ook in je spam"; een link "Wachtwoord vergeten?" bij het wachtwoordveld leidt naar dezelfde code.
    - Een foute code: "Deze code klopt niet. Je kunt het nog 4 keer proberen." Een verlopen of opgebruikte code: een
      melding in gewone taal met de knop "Stuur een nieuwe code".
    - De frontend bewaart de aanvraag in `localStorage`, zodat de code ook werkt na het sluiten van de browser, op
      hetzelfde toestel, zolang hij geldig is.
12. **Opslag en geldigheid.** Entiteit `LoginChallenge` (id, lid of geen, codehash, `expiresAt`, `attempts`,
    `usedAt`).
    - Een code van 6 cijfers heeft maar een miljoen mogelijkheden: een gewone SHA-256 (beslissing 20 van fase 1) is
      met een kopie van de database in een seconde terug te rekenen. Daarom HMAC-SHA-256 met `APP_SECRET` over
      `challengeId` en code, vergeleken met `hash_equals()`. De code komt uit `random_int()`.
    - Hooguit 5 pogingen per aanvraag; daarna is de aanvraag ongeldig. De kans om te raden: 5 op een miljoen per aanvraag, en
      aanvragen zijn begrensd (beslissing 13).
    - Eén keer bruikbaar; een nieuwe aanvraag maakt eerdere van hetzelfde lid ongeldig.
    - **Een code is 60 minuten geldig.** Het lid staat op het scherm te wachten, dus een lange termijn helpt niet;
      opnieuw aanvragen is één tik. Korter is te krap: mailservers houden mail van een onbekende afzender vaak 5 tot
      30 minuten vast (greylisting), en een minder ervaren loper zoekt de mail soms nog in de spam of vraagt hulp. De
      5 pogingen gelden per aanvraag, dus de termijn maakt raden niet makkelijker. Elke aanvraag en elk gebruik staat
      in het auditlog.
    - **De uitnodiging blijft een link**, 48 uur geldig: een nieuw lid heeft nog geen scherm waarin het een code kan
      aanvragen. Entiteit `Invitation` met het hash-patroon van `ApiToken` (geheim `bpi_` plus 64 hex-tekens,
      SHA-256). De link wijst naar de frontend met het geheim achter een `#` (`…/app/#/welcome/bpi_…`): dat deel
      stuurt de browser nooit naar de server, dus het komt niet in toegangslogs of een `Referer`. De pagina toont een
      knop "Inloggen" en doet `POST /auth/invitation` met het geheim in de body; een `GET` zou door virusscanners
      verbruikt worden.
    - Geen Symfony `login_link`: die is ondertekend en niet opgeslagen, dus niet één keer bruikbaar zonder extra
      opslag. API-scripts en MCP gebruiken altijd het persoonlijke token, nooit een code of sessie.
13. **Beperkt aantal pogingen met `symfony/rate-limiter`** (cache in bestanden): inloggen 5 mislukte pogingen per
    15 minuten per adres en IP (`login_throttling`), en daarnaast 30 mislukte pogingen per uur per IP over alle
    adressen, tegen het proberen van één wachtwoord op veel adressen; code-aanvragen 3 per uur per adres en
    10 per uur per IP; daarna 429 met `Retry-After` en een melding in gewone taal. Een geslaagde inlog telt niet mee. Het plan zette
    de rate limiter bij MCP (fase 4); inloggen heeft hem nu nodig.
14. **Auditregels voor inloggen**: `account.logged_in` en `account.login_failed` met een ingekort IP-adres
    (`IpUtils::anonymize()`: laatste byte van IPv4, laatste 80 bits van IPv6) in de gewijzigde velden. Een mislukte
    poging op een onbekend adres heeft geen onderwerp en gaat alleen naar het applicatielog, zonder het getypte
    adres. De bewaartermijn van 90 dagen voor deze regels hoort bij het opruimen in fase 6a.
15. **Opnieuw bevestigen bij gevoelige acties**, achter een kleine interface `Shared\Domain\RecentAuthentication`
    (`@upgrade-8.4`, sudo mode). Een sessie die in de laatste 15 minuten met wachtwoord, code of uitnodiging inlogde of
    bevestigde, telt als recent; ingelogd blijven telt nooit. `POST /auth/confirm` met het wachtwoord maakt een sessie
    recent. Nodig voor: token aanmaken, rollen wijzigen, lid wissen. Een API-token is nooit recent, dus die acties
    kunnen alleen in de browser.
16. **Wachtwoord wijzigen vraagt het huidige wachtwoord, of een recente sessie.** In een recente sessie
    (beslissing 15, ook na inloggen met een code) zet het lid een nieuw wachtwoord zonder het oude: zo werkt
    "wachtwoord vergeten" (beslissing 10). Daarbuiten vraagt het het huidige wachtwoord.

### Tokens en scopes

17. **Scopes per verzoek, niet in de Voters.** Afwijking van Fase 2 in het plan ("vertaald voor de Voters"): een
    listener op de tokenfirewall eist `read` voor `GET` en `HEAD`, `write` voor de andere methodes, en `admin` voor
    `/api/v1/admin/`. De Voters blijven alleen over rollen gaan, de policies in memory ongewijzigd, en de scopetest
    dekt alles op één plek. Rol én scope moeten kloppen.
18. **Tokens beheren alleen in de browser.** `/me/tokens` weigert een verzoek met een token: een gelekt token kan zo
    geen nieuw token maken.
19. **Een token verloopt altijd**, binnen 365 dagen; `CreateApiToken` eist de datum (vastgesteld 3). De frontend
    stelt 90 dagen voor.

### API

20. **Foutvertaling via `Shared\Domain\RuleViolation`** (vastgesteld 8): een interface met `code()` (vaste naam,
    zoals `round_is_full`) en `kind()` (`conflict` of `invalid`). Elke domeinfout implementeert hem. Eén listener in
    `Shared\Infrastructure\Http` maakt `application/problem+json` (RFC 9457):

    | Fout | Status |
    | --- | --- |
    | Validatie van de invoer | 422, met `violations` per veld |
    | `RuleViolation` `invalid` / `conflict` | 422 / 409 |
    | `NotFound` | 404 |
    | `AccessDenied` | 403 |
    | Niet ingelogd | 401 |
    | Te veel pogingen | 429 |
    | Al het andere | 500, zonder detail; de fout gaat naar het log |

    `type` is `urn:buurtpreventie:problem:<code>`; `title` vertaald. Een test eist een vertaling in `nl` en `en` voor
    elke code, en dat elke `DomainException` in `src/` `RuleViolation` of `NotFound` is.
21. **`symfony/translation` voor API-meldingen en validatorfouten**, `nl` en `en` in `translations/`, taal uit
    `Accept-Language`, standaard `nl`. De instelbare taal volgt in fase 3.
22. **Eigen acties zonder lid-id in de route**: `memberId` wordt optioneel in `ChangeContactConsent`,
    `ChangeOpenRoundNotifications`, `CreateApiToken` en `SetPassword`, leeg is de uitvoerder, zoals in
    `SignUpForRound` (vastgesteld 9).
23. **OpenAPI met NelmioApiDocBundle, alleen JSON op `/api/v1/docs.json`**, zonder Swagger UI (die vraagt Twig en
    assets). Regel 3 staat in een controller ook `OpenApi\Attributes` en `Nelmio\ApiDocBundle\Attribute` toe, alleen
    voor documentatie, met een overtreding in de fixtures voor wat er nog steeds niet mag. Invoer en uitvoer leest
    Nelmio uit `#[MapRequestPayload]`, `#[MapQueryString]` en de `…Output`-klassen.
24. **Datums in de uitvoer altijd met de offset van `Europe/Amsterdam`** (`2026-10-24T22:00:00+02:00`); invoer met
    elke offset, ook `Z`.
25. **Endpoints die niet in de tabel van het plan staan, wel nodig zijn**: `POST /rounds/{id}/cancellation`
    (`CancelRound`), `GET /staffing-gaps` (`FindStaffingGaps`, het blok "Zoekt een loopmaatje"),
    `GET /meeting-points` en `GET /outcomes` (keuzelijsten voor een lid; `/config` van fase 3 levert ze niet),
    `POST /auth/confirm` (beslissing 15), `POST /admin/members/{id}/invitation`, en de lees-endpoints van de catalogi
    onder `/admin/`.
26. **`/members` voor een lid**: alleen actieve leden; weergavenaam, en telefoon en adres alleen bij toestemming; een
    e-mailadres nooit (het plan geeft e-mail alleen aan de beheerder). Coördinator en beheerder zien ook de volledige
    naam, zoals nu. De beheerder ziet alles via `/admin/members`.
27. **Wat een lid zelf wijzigt** (`PATCH /me`): telefoon, adres, toestemming en meldingen over open rondes. Naam,
    weergavenaam en e-mailadres wijzigt alleen de beheerder: een gekaapte sessie kan zo het adres voor een inlogcode niet
    omzetten.
28. **Uitnodigen door de beheerder** (`POST /admin/members/{id}/invitation`) maakt een uitnodiging (48 uur geldig,
    beslissing 12) en stuurt hem via de
    `Notifier`. De link staat alleen in het antwoord voor een lid dat nog nooit inlogde, zodat de beheerder hem zelf
    kan doorgeven als er geen mail is (plan, Afhankelijkheden). Voor elk ander lid zou dat de beheerder laten inloggen
    als dat lid. De coördinator kan een nieuwe uitnodiging laten sturen, maar ziet de link nooit; een lid dat al inlogde, vraagt
    zelf een code aan.
29. **De `LoggingNotifier` logt type en ontvanger, geen parameters** (vastgesteld 12). Inlogcode en uitnodiging gaan
    als echte mail via `symfony/mailer`, in tekst zonder branding; de opgemaakte templates volgen in fase 3. Lokaal
    vangt Mailpit (`axllent/mailpit`, vaste versie) in `compose.yaml` elke mail op, zodat inloggen met een code te
    testen is zonder dat er iets het toestel verlaat. Besloten op 16 november 2026, in plaats van een `DevCodeNotifier`
    die code en link naar het log schreef.
30. **Inloggen met de demo** (vastgesteld 15): de sets `demo` en `edge` geven elk voorbeeldlid hetzelfde verzonnen
    wachtwoord, dat alleen in de fixture-code staat en dat het commando toont, net als het geheim van het token van het
    demolid. Alleen `dev` en `test`.

### Testen

31. **Endpointtests in memory.** Een testkernel in `tests/` (`InMemoryKernel`, een subklasse van `Kernel`, dat zelf
    blijft zoals geleverd) koppelt de repository-interfaces aan de fakes uit `tests/<Module>/Fake/`. Zo draaien de
    functionele tests (groep `functional`) ook zonder database, en snel. Een klein aantal databasetests loopt het hele
    pad door Doctrine: het `curl`-scenario, review-focus 2 via de API, en het ingetrokken token.
32. **De HTTP-matrixtest** (`tests/Shared/HttpMatrixTest.php`) zet per route niet ingelogd, geen rol, loper,
    coördinator, analist en beheerder tegen de verwachte status (401, 403 of geslaagd), naast de matrix op de
    handlers. Een bewakingstest eist een rij voor elke route uit de router.
33. **De scopetest** zet elke scope tegen `GET`, een schrijvend endpoint en een `/admin/`-endpoint.

### Opengrep-regels voor deze fase

34. Elke regel met een regeltest:
    - **`request-value-in-query`** (taint): een waarde uit `Request` (`get`, `query`, `request`, `getContent`,
      `toArray`) die een query bereikt, ook via de querybuilder;
    - **`secret-compared-with-equals`**: `==`, `===` of `strcmp` op een variabele met `token`, `secret` of `hash` in de
      naam; altijd `hash_equals()`;
    - **`security-disabled`**: `security: false` op een andere firewall dan `dev`, en `csrf_protection: false`;
    - **`insecure-session-cookie`**: `cookie_secure: false` of `cookie_samesite: none` buiten `when@test`.

## Nieuwe afhankelijkheden (ter goedkeuring)

Versies en datums van Packagist op 15 november 2026. De Composer-cooldown van 24 uur geldt.

| Pakket | Waarvoor | Licentie | Soort | Releaseritme | Nu |
| --- | --- | --- | --- | --- | --- |
| `nelmio/api-doc-bundle` | OpenAPI-spec uit routes, invoer en uitvoer | MIT | runtime | Patch elke paar weken, minor elke paar maanden | v5.13.2 (30 okt) |
| `zircote/swagger-php` (via Nelmio) | OpenAPI-model en attributen | Apache-2.0 | runtime | Vaak: vier minors in de laatste twee weken; de 6-lijn krijgt nog releases | 6.20.4 (11 nov), zie T2 |
| `phpdocumentor/reflection-docblock`, `phpdocumentor/reflection-common`, `phpdocumentor/type-resolver`, `phpstan/phpdoc-parser`, `radebatz/type-info-extras`, `webmozart/assert` (via Nelmio en swagger-php) | Typen uit docblocks | MIT | runtime | Een paar releases per jaar; type-info-extras vaker | type-info-extras 1.0.14 (12 nov) |
| `symfony/security-csrf` | CSRF voor de browser (beslissing 7) | MIT | runtime | Symfony 7.4 LTS; dit onderdeel verandert zelden | v7.4.8 (24 mrt) |
| `symfony/rate-limiter` (+ `symfony/options-resolver`) | Inlogpogingen en link-aanvragen (beslissing 13); fase 4 gebruikt hem voor MCP | MIT | runtime | Symfony 7.4 LTS, patch ongeveer maandelijks | v7.4.23 (1 nov) |
| `symfony/translation` | API-meldingen en validatorfouten in `nl` en `en` (beslissing 21) | MIT | runtime | Symfony 7.4 LTS | v7.4.23 (1 nov) |
| `symfony/mailer` (+ `symfony/mime`, `egulias/email-validator`, `symfony/polyfill-intl-idn`) | Inlogcode en uitnodiging per mail (beslissing 29); goedgekeurd op 16 november 2026 | MIT | runtime | Symfony 7.4 LTS | v7.4.22 |

`nikic/php-parser` (nu dev, via PHPStan en Rector) en `symfony/type-info` en `symfony/finder` (al runtime) komen
mee. Nelmio en swagger-php zijn het zwaarste deel: samen een stuk of zes pakketten, waarvan swagger-php snel
uitbrengt. Alternatief zonder afhankelijkheden: een met de hand bijgehouden `openapi.yaml` plus de dekkingstest. Dat
loopt uit de pas met de velden van invoer en uitvoer; het plan kiest Nelmio, en dit taakplan volgt dat.
`SymfonyReleaseLineTest` dekt de nieuwe Symfony-pakketten.

## Taken

Elke taak is één of enkele kleine commits; tests komen vóór de code.

### T1. Branch en taakplan

`feature/phase-2` vanaf `develop`; dit plan. Bouwen pas na akkoord.

### T2. Afhankelijkheden en configuratie

- `composer require` van de goedgekeurde pakketten; recepten nalopen, receptbestanden ongewijzigd.
- `framework.yaml`: sessie (beslissing 8), vertaling met `nl` als standaard; `rate_limiter.yaml`;
  `nelmio_api_doc.yaml` met alleen `/api/v1` en de route `/api/v1/docs.json`.
- Test eerst: `SymfonyReleaseLineTest` dekt de nieuwe pakketten; `composer deprecations` blijft schoon.
- Uitgevoerd op 15 november 2026, met deze keuzes:
  - `zircote/swagger-php` blijft op de 6-lijn (`conflict` op `>=7` in `composer.json`): Nelmio breidt
    `OpenApi\Generator` uit, die in 7.0 verouderd is, en `composer deprecations` faalt daarop. De regel gaat weg
    zodra Nelmio `OpenApi\Builder` gebruikt; `docs/gotchas.md` noemt het;
  - `phpdocumentor/reflection-common` en `webmozart/assert` kwamen mee en staan in de tabel;
  - het recept van Nelmio staat in `recipes-contrib` en wordt niet toegepast (`allow-contrib: false`): bundel,
    `nelmio_api_doc.yaml` en de route staan er met de hand; de titel van de spec is Engels, zoals de API;
  - sessie en rate limiter worden ingesteld in T5, met de tests die ze nodig hebben; vertaling staat op `nl` met
    `en` erbij, `translations/` is nog leeg;
  - `OpenApiDocumentTest`: de spec is JSON zonder inloggen, met de volledige paden onder `/api/v1`, zonder zijn
    eigen route.

### T3. Foutafhandeling en testopzet (test eerst)

- `Shared\Domain\RuleViolation`; alle domeinfouten implementeren hem (beslissing 20); de listener voor
  `problem+json`; vertalingen `nl` en `en` voor elke code en voor de validator.
- Tests: elke rij van de tabel in beslissing 20 als functionele test op een testroute; de dekkingstest voor
  vertalingen en domeinfouten; een 500 bevat geen exceptietekst.
- `InMemoryKernel` en een basisklasse voor endpointtests met `actingAs(rol)` en `withToken(scopes)` (beslissing 31).
- Uitgevoerd op 15 november 2026, met deze keuzes:
  - `RuleViolation` heeft twee markeringen in plaats van de methodes `code()` en `kind()`: `StateConflict` (409)
    en `InvalidValue` (422). De code is de korte klassenaam in snake case (`ProblemCode`), zodat geen enkele fout
    een methode met een vaste tekst nodig heeft;
  - waardeobjecten die invoer weigeren, gooien een eigen `InvalidValue` in plaats van `InvalidArgumentException`
    (`InvalidPhoneNumber`, `InvalidCoordinates`, `EmptyComment`, `InvalidStaffingRule`, `InvalidPeriod`,
    `InvalidStaffingDefaults`, `InvalidEmailAddress`, `InvalidName`); anders werd verkeerde invoer een 500. Ze
    breiden `InvalidArgumentException` uit, dus bestaande tests blijven gelden. Een `InvalidArgumentException` die
    een programmeerfout meldt (een geheim veld in het auditlog, een token-geheim in het verkeerde formaat) blijft
    een 500;
  - de taal komt uit `Accept-Language` (`set_locale_from_accept_language`), met `nl` voor een onbekende taal of
    geen header; validatorfouten komen uit de vertalingen van Symfony;
  - de rij "niet ingelogd" (401) test T5, als de browserfirewall er is; `actingAs` en `withToken` komen in T4 en
    T5 met de firewalls die ze nodig hebben;
  - `InMemoryKernel` vervangt alle repositories, de transactie, het auditlog en de notifier; de probe-controller
    met zijn routes bestaat alleen in die kernel;
  - Mago sluit `src/Kernel.php` alleen nog uit van opmaak en lint: de analyzer heeft hem nodig voor de testkernel;
  - opgemerkt voor T8: `#[MapQueryString]` geeft bij een validatiefout standaard 404; elke controller zet
    `validationFailedStatusCode: 422`.

### T4. Tokens: authenticator en scopes (test eerst)

- Tests: geldig token geeft kanaal `api` en het lid als uitvoerder; elke weigerreden uit beslissing 2 geeft 401
  zonder body met `WWW-Authenticate: Bearer`, en een logregel met de reden; ook een ontbrekend of misvormd token
  (`Bearer` zonder geheim, ander formaat); een ingetrokken token werkt bij het eerstvolgende verzoek niet meer, en
  een token van een gedeactiveerd of gewist lid ook niet (review-focus 4, uit T7 van fase 1); `markUsed` hooguit eens
  per minuut.
- De scopetest (beslissing 33) en de listener (beslissing 17).
- Firewall `api_token` met request matcher op de header.
- Uitgevoerd op 16 november 2026, met deze keuzes:
  - elk API-verzoek met een `Authorization`-header gaat naar de tokenfirewall, ook `Basic`: zo wordt een verkeerde
    header daar geweigerd en gelogd, in plaats van als verzoek zonder sessie door te gaan;
  - waarom een bekend token niet werkt (ingetrokken, verlopen, lid inactief) staat in `ApiToken::refusalAt()`, met
    domeintests; misvormd en onbekend beslist de authenticator. Ingetrokken gaat vóór de andere redenen;
  - `ApiToken::markUsed()` schrijft hooguit eens per minuut en geeft terug of het schreef; de authenticator roept hem
    aan in een `Transaction`;
  - `MemberDirectory::identity()` komt nu, met `MemberIdentity` (lid, huidige rollen, actief); `identify()` op
    e-mailadres volgt in T5;
  - een ontbrekende scope is een 403 met eigen code `insufficient_scope`, via `NamedProblem` in `Shared`. `admin`
    geldt alleen voor `/api/v1/admin/`: een token met alleen `admin` leest elders niet;
  - `InMemoryKernel` vervangt de diensten in een compiler pass (`InMemoryServices`), zet de klok stil op een vast
    moment en bewaart wat de app logt (`RecordingLogger`); een tweede probe-controller toont uitvoerder en kanaal;
  - het ontbrekende token zonder header gaat via de browserfirewall: dat test T5 met het entry point.

### T5. Inloggen met wachtwoord en sessie (test eerst)

- `MemberDirectory::identify()` en `identity()`, `MemberRepository::ofEmail()` met integratietest (beslissing 4).
- Tests: `POST /auth/login` met goed wachtwoord geeft een sessie met kanaal `web` en `account.logged_in`; fout
  wachtwoord, onbekend adres en gedeactiveerd lid geven dezelfde 401; de zesde mislukte poging binnen 15 minuten
  geeft 429; een v3-achtige hash wordt bij inloggen vernieuwd; een lid dat gedeactiveerd wordt, is bij het volgende
  verzoek uitgelogd (review-focus 4); een gewijzigde rol werkt bij het volgende verzoek; een muterend verzoek zonder
  geldige CSRF-controle geeft 403; `POST /auth/logout` wist sessie en cookie.
- Authenticator, user provider, CSRF-listener, entry point; auditregels uit beslissing 14.
- Uitgevoerd op 16 november 2026, met deze keuzes:
  - de firewall heet `browser`; alles onder `/api/` vraagt een ingelogd lid, behalve `/api/v1/health`,
    `/api/v1/docs.json` en `/api/v1/auth/`. Inloggen antwoordt 204 zonder body; uitloggen ook. Beide alleen `POST`,
    als routes zonder controller die de firewall beantwoordt;
  - lid en wachtwoord worden gecontroleerd als stappen van het paspoort (`PasswordCheck`), zodat de rate limiter
    van Symfony (`login_throttling` met `DefaultLoginRateLimiter`) eerst beslist: een geweigerde poging hasht niets.
    In Symfony 7.4 telt hij alleen mislukte pogingen, en een geslaagde inlog wist alleen de telling per adres en IP;
  - een onbekend adres, inactief lid of lid zonder wachtwoord kost één hash, zodat de tijd niets verraadt. Een
    wachtwoord boven 4.096 tekens wordt vooraf geweigerd: de hasher van Symfony gooit daar een fout;
  - `account.login_failed` heeft geen uitvoerder (`RunAs::system()` op kanaal `web`): niemand bewees wie het
    probeerde. Het IP-adres wordt ingekort met `IpUtils::anonymize($ip, 1, 10)`; de standaard houdt 64 bits van IPv6;
  - `Retry-After` komt uit de minuten van Symfony, naar boven afgerond, dus 15 minuten kan 960 seconden zijn;
  - `MemberUser` is gelijk aan een ander `MemberUser` van hetzelfde lid (`EquatableInterface`); anders logt Symfony
    een lid uit zodra de rollen veranderen;
  - `SameOriginGuard` draait vóór de firewalls en gebruikt `SameOriginCsrfTokenManager` met `check_header`; de
    header heet `csrf-token`. Een verzoek met een `Authorization`-header gaat ongecontroleerd naar de tokenfirewall;
  - `ProblemResponses` bouwt elk problem-antwoord, ook dat van de firewall; nieuwe codes `login_failed` en
    `csrf_check_failed`;
  - de sessiecookie is altijd `Secure`; lokaal over `http://localhost` nemen Chrome en Firefox hem aan;
  - `InMemoryKernel` telt inlogpogingen in een eigen `ArrayAdapter` per kernel; `ApiTestCase` stuurt
    `Sec-Fetch-Site: same-origin` en heeft `actingAs()`. De probe toont ook de rollen;
  - `MemberRepository::ofEmail()` heeft een eigen integratietest (`DoctrineMemberRepositoryOfEmailTest`): de
    bestaande testklasse zat aan de grens van Mago voor het aantal methodes.

### T6. Ingelogd blijven, code per mail en uitnodiging (test eerst)

- `LoginChallenge` en `Invitation` met migratie (alleen toevoegen) en `Account.credentialsChangedAt` (beslissingen 9,
  10 en 12).
- Use cases (Identity): `RequestLoginCode`, `IssueInvitation`; inloggen met code of uitnodiging en opnieuw
  bevestigen zijn authenticators (zie Uitgevoerd); `InviteMember` in Membership roept de uitnodiging aan.
- Tests: elke code en uitnodiging één keer bruikbaar; een code verloopt na 60 minuten, een uitnodiging na 48 uur;
  een code werkt alleen met zijn eigen `challengeId`; na 5 foute pogingen werkt ook de goede code niet meer; een
  nieuwe aanvraag maakt de vorige ongeldig; spaties en een streepje in de code tellen niet mee; 202 met een
  `challengeId` bij een onbekend adres, zonder verschil in antwoord; in de database staat geen code die met SHA-256
  alleen terug te rekenen is; na inloggen met een code zet het lid een nieuw wachtwoord zonder het oude; na een
  nieuw wachtwoord werkt een onthouden apparaat niet meer; `remember_me` houdt een sessie 90 dagen vast maar telt
  niet als recent (beslissing 15).
- `LoggingNotifier` zonder parameters; inlogcode en uitnodiging per mail, lokaal in Mailpit (beslissing 29).
- Uitgevoerd op 16 november 2026, met deze keuzes:
  - opslag: `LoginChallenge` (id van 128 willekeurige bits als 32 hex-tekens, `CodeAttempt` `Accepted`/`Wrong`/`Ended`),
    `Invitation` (`bpi_`), `LoginCode::typed()`/`shown()`, `Account::changePassword()` met `credentialsChangedAt` en
    `renewPasswordHash()`, `LoginCodeHasher` (HMAC-SHA-256 met `APP_SECRET` over id en code), migratie
    `Version20261116002055` (alleen toevoegen);
  - `RequestLoginCode` (`POST /auth/login-code`, 202 met `challengeId`): een onbekend adres of inactief lid krijgt een
    aanvraag zonder lid, waar geen code op past; een getypte code telt daar als fout, dus het antwoord verschilt niet.
    Een adres in een verkeerd formaat geeft 422. Voterattribuut `account.request_login_code` voor iedereen, ook
    zonder sessie; auditregel `account.login_code_requested` alleen voor een bekend lid;
  - `LoginCodeRequestThrottle` begrenst code-aanvragen op 3 per uur per adres (geteld op een hash, zodat de cache
    geen adres bevat) en 10 per uur per IP-adres, met 429 en `Retry-After`. Elke aanvraag telt, met of zonder lid;
  - inloggen met een code, met een uitnodiging en opnieuw bevestigen zijn authenticators op de firewall `browser`
    (`LoginCodeAuthenticator`, `InvitationAuthenticator`, `ConfirmPasswordAuthenticator`), met `LoginCodeCheck`,
    `InvitationCheck` en `PasswordCheck`, geen use cases (zoals beslissing 4). De `login_throttling` van Symfony telt
    ook hun mislukte pogingen, per aanvraag-id of uitnodiging en IP-adres, en in de 30 per uur per IP-adres;
  - probleemcodes: `login_code_wrong` (401 met `attemptsLeft`), `login_code_ended` (onbekend, gebruikt, verlopen,
    opgebruikt, of het lid is inmiddels gedeactiveerd) en `invitation_ended`. De uitnodiging van een gedeactiveerd lid
    blijft ongebruikt;
  - `LoginRecorder` zet de manier (`password`, `code`, `invitation`) in de auditregel. Bevestigen is geen inloggen:
    alleen een mislukte poging komt in het auditlog;
  - `RecentAuthentication` (`@upgrade-8.4`) leest het tokenattribuut `authenticated_at`, dat de authenticators zetten;
    recent is tot en met 15 minuten. `BrowserTokenChannel` zet kanaal `web` op elk token van de firewall `browser`, ook
    dat van `remember_me`, dat geen moment krijgt;
  - `TokenExecutionContext` geeft kanaal `web` zonder actor voor een HTTP-verzoek zonder token;
  - `remember_me`: 90 dagen, cookie `bp_remember` (`Secure`, `HttpOnly`, `Lax`), aan met `"rememberMe": true`,
    ondertekend met `MemberUser::$credentialsVersion` (het moment van `credentialsChangedAt`). Het vinkje staat in de
    frontend standaard uit: wie een gedeeld toestel gebruikt, kiest zelf (besloten op 16 november 2026);
  - een nieuw wachtwoord beëindigt ook elke lopende sessie (besloten op 16 november 2026): `MemberUser::isEqualTo()`
    vergelijkt ook de `credentialsVersion`. De sessie die het wachtwoord zette, blijft via `CurrentSession`
    (`TokenCurrentSession`), en een onthoud-cookie van dat verzoek wordt opnieuw ondertekend. De hele weg via
    `PUT /me/password` test T7;
  - een inlog zonder e-mailadres geeft 422 `invalid_input` met een veldfout op `email`: Symfony neemt geen lege
    gebruikersnaam;
  - **afwijking van beslissing 16, goedgekeurd op 16 november 2026**: `SetPassword` vraagt nooit het huidige wachtwoord, maar eist een recente sessie
    (anders 403 `reauthentication_required`, `Shared\Domain\ReauthenticationRequired`). Het huidige wachtwoord gaat
    via `POST /auth/confirm`, achter de login throttling en met een auditregel bij falen; een controle in
    `SetPassword` zelf zou een onbegrensde manier zijn om wachtwoorden te raden. `memberId` is optioneel (beslissing 22);
  - `MailNotifier` stuurt `account.login_code` en `account.invitation` als platte tekst in het Nederlands, uit
    `translations/mail.nl.yaml` (een Engelse versie staat klaar). Platte tekst kent geen grote letters: de code staat
    in twee groepen op een eigen, ingesprongen regel bovenaan. Geldigheid gaat als parameter mee (`minutes`, `hours`).
    Afzender `MAILER_FROM` (standaard `noreply@localhost`), link `DEFAULT_URI` plus `/app/#/welcome/bpi_…`. De
    `LoggingNotifier` logt geen parameters meer. `MemberDirectory::emailOf()` geeft geen adres voor een inactief lid;
  - `IssueInvitation` (`account.issue_invitation`) en `InviteMember` (`member.invite`) voor coördinator en beheerder;
    het geheim komt alleen terug voor een beheerder bij een lid dat nooit inlogde. Een inactief lid geeft 409
    `invitee_inactive`;
  - de test "na inloggen met een code zet het lid een nieuw wachtwoord zonder het oude" staat op de handler (een
    recente sessie); de hele weg via `PUT /me/password` volgt in T7;
  - in tests gaat mail altijd naar `null://null`: de Symfony CLI zet `MAILER_DSN` en `DEFAULT_URI` als hij draait.

### T7. Eigen account (test eerst)

- `GET /me`, `PATCH /me` (beslissing 27, met `ChangeOwnContactDetails`), `PUT /me/password` (beslissing 16),
  `GET/POST/DELETE /me/tokens` (`ListApiTokens` nieuw; beslissingen 15, 18 en 19), `GET /me/activity`,
  `GET /me/export` (eigen profiel, aanmeldingen, terugmeldingen, opmerkingen, tokens zonder geheim, eigen auditregels).
- Tests: het geheim staat alleen in het antwoord van `POST /me/tokens`, nooit in `GET`; een token kan geen token
  maken; zonder recente aanmelding geeft `POST /me/tokens` 403 met `reauthentication_required`; de export bevat geen
  gegevens van andere leden behalve weergavenamen.
- Uitgevoerd op 16 november 2026, met deze keuzes:
  - `PUT /me/password` roept `SetPassword` aan zonder `memberId` (afwijking van beslissing 16, zie T6). De hele weg
    staat in `SetPasswordControllerTest`: na inloggen met een code zet het lid een nieuw wachtwoord zonder het oude;
    de sessie die het zette blijft, ook met haar onthoud-cookie; een sessie en een onthoud-cookie van een ander
    toestel niet meer;
  - de controllers onder `/me` geven nooit een `memberId` door: een lid-id in de body telt niet. `memberId` is
    optioneel in `CreateApiToken`, `SetPassword` en `ChangeOwnContactDetails` (beslissing 22). `ChangeContactConsent`
    en `ChangeOpenRoundNotifications` houden een verplicht `memberId`: ze hebben geen eigen endpoint, `PATCH /me`
    roept ze aan;
  - `PATCH /me` is `ChangeOwnContactDetails` (beslissing 27): telefoon, adres, toestemming en meldingen. Een veld dat
    ontbreekt of `null` is, blijft; lege tekst wist telefoon of adres. Toestemming en meldingen gaan via hun eigen
    use case, zodat de auditacties dezelfde blijven, en alleen als de waarde verandert (de frontend stuurt het hele
    formulier). Telefoon en adres krijgen `member.contact_details_changed` met de veldnamen zonder waarden, zoals
    `ChangeMember`. Voterattribuut `member.change_contact_details`, alleen voor het lid zelf;
  - `CreateApiToken` eist een recente sessie en een verloopdatum (beslissingen 15 en 19). `ApiToken::issue()`
    weigert een datum die niet na het aanmaken ligt of meer dan 365 dagen later (`InvalidTokenExpiry`, 422
    `invalid_token_expiry`). De auditregel noemt ook de verloopdatum. Migratie `Version20261116120000` maakt
    `api_token.expires_at` verplicht en geeft een token zonder datum eerst een einde 365 dagen na het aanmaken;
    `down()` maakt de kolom weer nullable;
  - `RunAs::member()` telt als recente aanmelding: wie een consolecommando draait, bewees wie hij is door de server te
    bereiken. Zo maken de voorbeelddata hun token nog via `CreateApiToken`, nu met 90 dagen geldigheid;
  - beslissing 18 zit in `TokenManagementGuard`, naast `TokenScopeGuard`: elk verzoek met een token onder
    `/api/v1/me/tokens` krijgt 403 `browser_only`, ook `GET` en `DELETE`. Dat volgt beslissing 17 (regels per
    verzoek, niet in de Voters);
  - `ListApiTokens` (nieuw) toont de tokens die niet ingetrokken zijn, nieuwste eerst, ook verlopen tokens, zodat het
    lid ziet waarom een script stopte. `DELETE /me/tokens/{tokenId}` antwoordt 204; een token van een ander lid
    geeft 403;
  - regel 3 staat in een controller ook `Symfony\Component\Routing\Requirement` toe, voor `Requirement::UUID` in
    een pad;
  - `GET /me/activity` is `ListOwnActivity` (Audit), met `AuditEntryRepository::byActor()` en een integratietest:
    50 regels per pagina (`?page=`), nieuwste eerst, met `nextPage`. Van een regel over een ander lid
    (`AuditEntry::isAboutAnotherMemberThan()`) blijven de actie en het soort onderwerp, niet het id en niet de
    wijzigingen;
  - `GET /me/export` is `ExportOwnData` (Membership) als JSON-bestand (`Content-Disposition: attachment`). Het roept
    `GetOwnProfile`, `ExportOwnPatrolData` (nieuw, Patrol), `ListApiTokens` en elke pagina van `ListOwnActivity` aan.
    Patrol kreeg `RoundRepository::withWalker()` met een integratietest, en `byAuthor()` voor terugmeldingen en
    opmerkingen. Van een loopmaatje staat alleen de weergavenaam in de export;
  - de lees-handlers onder `/me` nemen altijd de uitvoerder en hebben geen voterattribuut: er is geen ander lid om
    te kiezen. Zonder uitvoerder gooien ze `AccessDenied`;
  - de Postman-collectie loopt na het inloggen met een code het eigen account door, met de 403 voor een nieuw token
    of wachtwoord zodra alleen de onthoud-cookie over is. Ze zet nooit een geldig nieuw wachtwoord.

### T8. Rondes en aanmelden (test eerst)

- `GET /rounds`, `GET /rounds/{id}`, `POST /rounds`, `PATCH /rounds/{id}`, `DELETE /rounds/{id}`,
  `POST /rounds/{id}/cancellation`, `GET /staffing-gaps`, `GET /me/rounds?walked=` (`ListMyRounds` nieuw),
  `PUT/DELETE /rounds/{id}/walkers/me` en `/walkers/{memberId}`.
- Tests: aan- en afmelden zijn idempotent (tweede `PUT` en tweede `DELETE` geven hetzelfde antwoord); volle ronde 409
  `round_is_full`; namens aanmelden alleen voor de coördinator; `GET /rounds` boven 93 dagen geeft 422; elke ronde
  in de uitvoer heeft `status`, `walkerCount`, `minimumWalkers`, `maximumWalkers`, `openSpots` en `daypart`.
- Uitgevoerd op 16 november 2026, met deze keuzes:
  - een controller per use case; `SignUpForRound` en `WithdrawFromRound` hebben elk twee routes (`/walkers/me` en
    `/walkers/{memberId}`), beide met 204, ook bij herhalen. Een tweede `DELETE` na de laatste loper geeft 404: de
    ronde is dan weg (beslissing uit fase 1, "meldt de laatste loper zich af, dan vervalt de ronde");
  - `PATCH /rounds/{id}` volgt `ChangeRound`: start, startpunt en startnotitie worden vervangen, een weggelaten veld
    wordt leeg. Geen samenvoegen per veld, zoals bij `PATCH /me`; de frontend stuurt het hele formulier. Ter
    goedkeuring: `PUT` past beter bij die betekenis, maar wijkt af van het API-ontwerp;
  - het ronde-id komt alleen uit het pad: `ChangeRound` en `CancelRound` krijgen in de controller een leeg id via
    `default_constructor_arguments` van de serializer (een tekst, geen klasse van buiten regel 3), en de controller
    vervangt het door het id uit het pad; een id in de body telt niet. Geen aparte payloadklasse: een use-casemap
    bevat alleen de vaste bestanden;
  - `POST /rounds/{id}/cancellation` mag zonder body; `POST /rounds` antwoordt 201 met `{id}`;
  - `ListMyRounds` (nieuw, Patrol) leest `RoundRepository::withWalker()` (al met integratietest uit T7), op volgorde
    van start, zonder voterattribuut, zoals de andere lees-handlers onder `/me`. `walked=1` geeft alleen gelopen
    rondes, `walked=0` alle andere, zonder parameter alles; elke ronde heeft ook `reported`. Geen paginering: een lid
    loopt hooguit enkele honderden rondes;
  - `FindStaffingGaps` geeft ook `status` en `maximumWalkers`, zodat elke ronde in de uitvoer dezelfde zes velden
    heeft;
  - in een querystring leest een `+` als spatie: een offset gaat als `%2B` of `Z` (`docs/architecture.md`);
  - de Postman-collectie leest na het inloggen met wachtwoord de rondes van de komende week, één ronde, de gaten en de
    eigen gelopen rondes, en controleert de 422 voor meer dan 93 dagen en de 404 voor aanmelden bij een onbekende
    ronde. Ze meldt niemand aan.

### T9. Review-focus 2 via de API (test eerst)

Dezelfde rondes als T5 van fase 1, nu via de API, in memory en één keer door de database: een ronde om 22:00 op de
dag vóór en na beide wissels, aangemaakt met `+01:00`, `+02:00` en `Z`, komt terug als 22:00 met de juiste offset en
dagdeel `evening`, in `GET /rounds/{id}`, `GET /rounds` en `GET /me/rounds`.

- Uitgevoerd op 16 november 2026, met deze keuzes:
  - de wissels van 2027 (28 maart en 31 oktober) in plaats van die van 2026: een ronde in het verleden wordt
    geweigerd, en de klok van de tests staat op 16 november 2026. Elke avond gaat via `POST /rounds` met de offset
    van die dag, de andere offset en `Z` (`ClockChangeEvenings`);
  - in memory (`ClockChangeEndpointsTest`): elke schrijfwijze apart, terug in `GET /rounds/{id}`, `GET /rounds` (de
    lokale dag, in UTC gevraagd) en `GET /me/rounds`;
  - door de database (`ClockChangeDatabaseTest`, groepen `database` en `functional`): de vier avonden, elk in een
    andere schrijfwijze, in één test, met de entity manager geleegd vóór het lezen. Het is de eerste endpointtest op
    de echte kernel: hij zet de klok van de app stil met `Clock::set()` op `InMemoryKernel::NOW`, want de dienst
    `clock` is de globale klok van Symfony (`docs/testing.md`);
  - geen nieuwe endpoints, dus geen nieuwe stap in de Postman-collectie.

### T10. Na de ronde en analyse (test eerst)

- `POST /rounds/{id}/reports`, `DELETE /reports/{id}`, `POST /rounds/{id}/comments`, `DELETE /comments/{id}`,
  `GET /reports?from=&to=&outcome=` (`ListRoundReports` nieuw: analist en coördinator, periode hooguit 93 dagen),
  `GET /rounds/{id}/history` (`GetRoundHistory` nieuw, coördinator).
- Tests: tweede terugmelding 409; notitie alleen zichtbaar voor de lopers van die ronde, coördinator en analist; de
  geschiedenis toont een gewist lid als "verwijderd lid".
- Uitgevoerd op 16 november 2026, met deze keuzes:
  - de notitie bij een terugmelding én de opmerkingen zijn alleen voor de lopers van de ronde, de coördinator, de
    analist en het systeem (`ReportReadingPolicy::maySeeNotes()`), zoals de tabel in Privacy en AVG van het plan. `GET
    /rounds/{id}` gaf ze aan elk lid; nu krijgt een ander lid de uitkomst met notitie `null` en geen opmerkingen. Een
    beheerder zonder die rollen ziet ze dus ook niet. De regel staat met `mayListReports()` in een eigen
    `ReportReadingPolicy`: `RoundPolicy` werd te complex voor Mago;
  - `ListRoundReports` (nieuw, Patrol, voterattribuut `report.list` voor analist en coördinator, rij in de matrix)
    leest `RoundRepository::between()` en neemt de rondes met een terugmelding: geen nieuwe query. `outcome` is het
    id van een uitkomst, niet het soort. De periode gaat over de start van de ronde, hooguit 93 dagen; die regel staat
    in `ListingPeriod`, dat `ListRounds` ook gebruikt (verplaatst, niet veranderd);
  - `GetRoundHistory` (nieuw, Audit, `audit.view_round_history` alleen voor de coördinator, rij in de matrix) leest
    `AuditEntryRepository::bySubject()` (nieuw, met integratietest; de index op `subject_id` bestond al), oudste
    eerst, zonder paginering. Het leest alleen het log, dus een verwijderde ronde houdt haar geschiedenis; zonder
    regels is het 404. De uitvoer noemt de uitvoerder `member`, `removed_member` of `system`; de frontend maakt er
    "verwijderd lid" en "systeem" van, zodat de API geen Nederlandse tekst geeft. De wijzigingen staan er zoals in
    het log, dus met lid-id's van lopers;
  - `POST /rounds/{id}/reports` en `/comments` nemen het ronde-id uit het pad, zoals in T8, en antwoorden 201 met
    `{id}`; `DELETE /reports/{id}` en `/comments/{id}` 204;
  - de Postman-collectie controleert als voorbeeldbeheerder de 403 voor `/reports` en de geschiedenis, en de 404 voor
    een opmerking bij of het wissen van iets dat niet bestaat. Ze plaatst niets.

### T11. Leden en review-focus 3 (test eerst)

- `GET /members` (beslissing 26), `/admin/members`: lijst met inactieve leden, `GET`, `POST`, `PATCH`,
  `PUT /admin/members/{id}/roles`, `POST .../deactivation`, `DELETE .../deactivation` (heractiveren),
  `DELETE /admin/members/{id}` (wissen), `POST .../invitation` (beslissing 28).
- Tests: een lid zonder toestemming toont in `/members` en `GET /rounds/{id}` nooit telefoon, e-mail of adres, ook niet
  aan de coördinator via `/members`; met toestemming telefoon en adres, nooit e-mail; gedeactiveerde en gewiste leden
  staan niet in `/members` (uit T7 van fase 1); rollen wijzigen en wissen vragen een recente aanmelding; de link in
  het antwoord van de uitnodiging alleen voor een lid dat nooit inlogde.

### T12. Catalogi (test eerst)

`GET /meeting-points` en `GET /outcomes` (alleen actief, `ListMeetingPoints` en `ListOutcomes` nieuw),
`/admin/meeting-points` en `/admin/outcomes`: lijst met gearchiveerde, `POST`, `PATCH`, `POST .../archive`,
`DELETE .../archive` (terugzetten). Tests: een gearchiveerd item staat niet in de lijst voor leden; een botsende naam
geeft 409 met het bestaande item.

### T13. Auditlog voor de beheerder (test eerst)

`GET /admin/audit` met filters op lid, actie en periode, gepagineerd, en `?format=csv` (`ListAuditEntries` nieuw).
Repositoryquery's met integratietest op beide databases. Tests: filters, een gewist lid als "verwijderd lid", geen
volledig IP-adres in de uitvoer, CSV met een vaste kolomvolgorde en formules geneutraliseerd (een cel die met `=`,
`+`, `-` of `@` begint krijgt een `'` ervoor).

### T14. OpenAPI (test eerst)

- De dekkingstest: elke route onder `/api/v1` staat in `docs.json` met zijn methode, en elk pad in de spec bestaat;
  elk endpoint documenteert zijn problem-antwoorden.
- Elk request in `postman/buurtpreventie.postman_collection.json` (methode en pad onder `/api/v1`) staat in
  `docs.json`, zodat de collectie niet uit de pas loopt met de API; zonder nieuwe afhankelijkheid.
- Regel 3 uitgebreid (beslissing 23) met een overtreding in de fixtures en een regel in `docs/architecture.md`.

### T15. HTTP-matrixtest en het `curl`-scenario (test eerst)

- `HttpMatrixTest` en zijn bewakingstest (beslissing 32).
- Databasetest: lid logt in, maakt een token, en `curl`-gelijk verzoek `PUT /rounds/{id}/walkers/me` met dat token
  meldt het aan; met `read` alleen geeft het 403.
- Voorbeelddata: wachtwoord en token van het demolid (beslissing 30); `docs/getting-started.md` beschrijft het
  `curl`-scenario.
- Het wachtwoord is naar voren gehaald, op 16 november 2026, na T7, voor de Postman-collectie. Afwijking van
  beslissing 30, besloten op 16 november 2026: niet elk voorbeeldlid krijgt het, want echt hashen kost een halve
  seconde per lid. De eerste beheerder van elke set (ook `test`) heet `beheer@example.org`; in `demo` krijgen ook
  de analist, de coördinator en drie lopers het (een met een naamgenoot, een die de API gebruikt, een die gestopt
  is). Elk zet het via `SetPassword`; het wachtwoord staat in `SamplePassword` en het commando toont het. De lokale
  Postman-omgeving logt in als `beheer@example.org`. Het token van het demolid volgt hier nog.

### T16. Opengrep-regels

Per regel eerst het testbestand, dan de regel (beslissing 34). Lokaal gedraaid zoals in T14 van fase 1.

Overgeslagen op verzoek; volgt later.

### T17. ADR 0006 en documentatie

- `docs/adr/0006-authentication.md`: beslissingen 1 tot en met 19 (twee firewalls, eigen authenticators, geen JWT,
  hash-patroon voor elk geheim, CSRF met terugval voor iOS 15, recent ingelogd, scopes per verzoek).
- `docs/architecture.md`: `RuleViolation`, `RecentAuthentication`, de uitbreiding van `MemberDirectory`, regel 3,
  de firewalls; `docs/testing.md`: `InMemoryKernel`, HTTP- en scopematrix, de nieuwe Opengrep-regels;
  `docs/configuration.md`: sessie, rate limits, geldigheid van links en tokens; `docs/glossary.md`: inlogcode,
  uitnodiging, `LoginChallenge`, `Invitation`, scope, kanaal `web`; `docs/gotchas.md`: wat opvalt.
- `docs/plans/plan.md`: Fase 2 afvinken, de endpointtabel bijwerken (beslissingen 10, 12, 25), de afwijkingen van
  beslissingen 4 en 17 in Fase 2; `CHANGELOG.md`; `AGENTS.md` (Commands) als er een commando bijkomt.

### T18. Verifiëren en opleveren

- `composer test` zonder database (de databasetests slaan over), `symfony composer test` met database en
  `symfony composer check`; uitvoer in het verslag, met elke check die niet draaide.
- Push en pull request naar `develop` na akkoord; CI groen (alle jobs); merge als squash na akkoord.

## Afspraken

1. Claude commit in deze privérepo, in kleine commits. Push, pull request en merge alleen na akkoord.
   - De commit-guard van de anke-plugin laat `git add` en `git commit` alleen toe als de werkmap een repo van
     `github.com/zabuto` is en het commando zelf niet van map wisselt: geen `cd`, `pushd`, `-C`, `--git-dir`,
     `--work-tree` of `GIT_DIR=` in dezelfde aanroep. `cd` gaat dus in een eigen Bash-aanroep.
   - Alleen de bestanden van die commit stagen, met letterlijke paden; daarna `git show --stat HEAD` om te zien dat er
     niets anders in zit. `git mv` en `git rm` zetten een wijziging direct in de index.
   - Push, pull request en merge als squash pas na akkoord, en de merge pas als CI groen is.
2. `.env`, `.env.dev` en `.env.test` staan in de publieke repo en bevatten geen geheimen: lezen en wijzigen mag. De
   sandbox weigert elk bestand dat op `.env*` lijkt, dus lezen gaat met `git show HEAD:.env` (of `HEAD:.env.test`).
   `git status` meldt daarom "Operation not permitted" voor die drie; de uitkomst klopt. `.env.local`, `.env.*.local`
   en `shared/.env.local` nooit lezen of committen.
3. De database start de ontwikkelaar. Draait hij niet, dan meldt Claude dat en draait de databasetests niet.
   Voorbeelddata in de dev-database alleen na vragen.
4. Nieuwe afhankelijkheden pas na akkoord op de tabel hierboven.
5. Na elke taak wordt de tabel "Fasen en stand" in `docs/plans/plan.md` bijgewerkt.

## Open punten uit het plan

Gemeld, niet in deze fase opgelost:

- **Berichten gaan binnen de transactie uit**; versturen na de commit staat in fase 3. In deze fase komen er
  berichten bij (inlogcode, uitnodiging), die zo ook binnen de transactie vertrekken.
- **`anonymisedAt` uit beslissing 8 van fase 1** volgt in fase 5b of 6a.
- **Het slot op alle ledenrijen in `activeAdminsForUpdate()`** is een geaccepteerd risico (ADR 0005).
- **De hashketen in het auditlog** (beslissing 16 van fase 1) is uitgewerkt als taak in Fase 3 van `docs/plans/plan.md`, vóór de conversie van fase 5b.
- **De schakelaar die API-tokens en MCP uitzet** (plan, Datalek en Beveiligingsupdates) hoort bij de instellingen
  van fase 3; de tokenauthenticator krijgt dan een controle erop.
- **Links in mails met de ingestelde basis-URL** (review-focus 5) volgen in fase 3 en 6; in deze fase wijst een link
  naar een vast pad van de frontend.
- **`.htaccess` met de `Authorization`-header en de sessiemap in `shared/`** volgen met de mapindeling in fase 6a.
- **`/config`, `/admin/settings` en `/media`** (fase 3), **`/stats`** (fase 4 en U5), **`/system/tick`** (fase 6a) en
  **`/admin/maintenance`** (fase 6b) horen niet bij deze fase.
- **Bewaartermijnen** van inlogregels (90 dagen) en van gebruikte of verlopen eenmalige links worden opgeruimd door de
  scheduler in fase 6a.
