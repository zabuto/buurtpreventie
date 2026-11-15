# Fase 2: taakplan

Status: ter goedkeuring.

Bron: `docs/plans/plan.md` (Samenvatting, API en MCP in Symfony, Domeinmodel en API-ontwerp, Auditlog, Privacy en
AVG, Overdraagbaarheid, Fase 2, Risico's en review-focus), `docs/plans/phase-1.md` (T7 tot en met T16 en alles wat
naar fase 2 verwijst), `docs/architecture.md`, `docs/testing.md`, ADR 0001 tot en met 0005, `AGENTS.md`, en de code
op `develop`.

## Doel en klaar-criterium

De REST-API `/api/v1` staat: inloggen in de browser (wachtwoord, inloglink, ingelogd blijven) en met een persoonlijk
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
12. **De `LoggingNotifier` logt alle parameters.** Een inloglink of reset-geheim in de parameters komt zo in het log.
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
   nieuw veld `Account.credentialsChangedAt`, dat wachtwoord wijzigen en resetten zet: dan vervalt elk onthouden
   apparaat. Een gedeactiveerd lid valt via beslissing 5 af. Alternatief: tokens in de database (meer tabellen en
   opruimwerk) of geen "onthouden" (tegen het plan).
10. **Inloglink en wachtwoord-reset volgen het hash-patroon van `ApiToken`** (beslissing 20 van fase 1): entiteit
    `OneTimeLink` (lid, doel `log_in` of `reset_password`, SHA-256 als `BINARY(32)`, `expiresAt`, `usedAt`). Geheim
    `bpl_` of `bpr_` plus 64 hex-tekens, zodat een reset-geheim nooit als inloglink werkt. Eén keer bruikbaar; een
    inloglink 15 minuten geldig, een reset 60 minuten; een nieuwe aanvraag maakt eerdere ongebruikte links van
    hetzelfde doel ongeldig. Geen Symfony `login_link`: die is ondertekend, niet opgeslagen, en volgt dus niet
    beslissing 20.
11. **Inloglink inwisselen met `POST`, niet `GET`.** Afwijking van het API-ontwerp (`GET /auth/login-link/{token}`):
    een `GET` die inlogt is een muterende `GET`, en virusscanners van maildiensten openen links vooraf en zouden de
    eenmalige link verbruiken. De link in de mail wijst naar een pagina van de frontend met een knop, die
    `POST /auth/login-link/{token}` doet.
12. **Aanvragen verraden niets.** `POST /auth/login-link` en `POST /auth/password-reset` antwoorden altijd 202, ook
    bij een onbekend of inactief adres.
13. **Beperkt aantal pogingen met `symfony/rate-limiter`** (cache in bestanden): inloggen 5 mislukte pogingen per
    15 minuten per adres en IP (`login_throttling`); link- en reset-aanvragen 3 per uur per adres en 10 per uur per
    IP; daarna 429 met `Retry-After`. Het plan zette de rate limiter bij MCP (fase 4); inloggen heeft hem nu nodig.
14. **Auditregels voor inloggen**: `account.logged_in` en `account.login_failed` met een ingekort IP-adres
    (`IpUtils::anonymize()`: laatste byte van IPv4, laatste 80 bits van IPv6) in de gewijzigde velden. Een mislukte
    poging op een onbekend adres heeft geen onderwerp en gaat alleen naar het applicatielog, zonder het getypte
    adres. De bewaartermijn van 90 dagen voor deze regels hoort bij het opruimen in fase 6a.
15. **Opnieuw bevestigen bij gevoelige acties**, achter een kleine interface `Shared\Domain\RecentAuthentication`
    (`@upgrade-8.4`, sudo mode). Een sessie die in de laatste 15 minuten met wachtwoord of inloglink inlogde of
    bevestigde, telt als recent; ingelogd blijven telt nooit. `POST /auth/confirm` met het wachtwoord maakt een sessie
    recent. Nodig voor: token aanmaken, rollen wijzigen, lid wissen. Een API-token is nooit recent, dus die acties
    kunnen alleen in de browser.
16. **Wachtwoord wijzigen vraagt het huidige wachtwoord**; een lid zonder wachtwoord (alleen inloglinks) zet het eerste
    wachtwoord in een recente sessie.

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
    weergavenaam en e-mailadres wijzigt alleen de beheerder: een gekaapte sessie kan zo het adres voor een reset niet
    omzetten.
28. **Uitnodigen door de beheerder** (`POST /admin/members/{id}/invitation`) maakt een inloglink en stuurt hem via de
    `Notifier`. De link staat alleen in het antwoord voor een lid dat nog nooit inlogde, zodat de beheerder hem zelf
    kan doorgeven als er geen mail is (plan, Afhankelijkheden). Voor elk ander lid zou dat de beheerder laten inloggen
    als dat lid. De coördinator kan een inloglink laten sturen ("inloglink opnieuw sturen"), maar ziet hem nooit.
29. **De `LoggingNotifier` logt type en ontvanger, geen parameters** (vastgesteld 12). In `dev` schrijft een aparte
    `DevLinkNotifier` de link naar het log, zodat inloggen met een link lokaal te testen is; nooit in `prod`.
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
| `zircote/swagger-php` (via Nelmio) | OpenAPI-model en attributen | Apache-2.0 | runtime | Vaak: vier minors in de laatste twee weken | 7.6.0 (12 nov) |
| `phpdocumentor/reflection-docblock`, `phpdocumentor/type-resolver`, `phpstan/phpdoc-parser`, `radebatz/type-info-extras` (via Nelmio en swagger-php) | Typen uit docblocks | MIT | runtime | Een paar releases per jaar; type-info-extras vaker | type-info-extras 1.0.14 (12 nov) |
| `symfony/security-csrf` | CSRF voor de browser (beslissing 7) | MIT | runtime | Symfony 7.4 LTS; dit onderdeel verandert zelden | v7.4.8 (24 mrt) |
| `symfony/rate-limiter` (+ `symfony/options-resolver`) | Inlogpogingen en link-aanvragen (beslissing 13); fase 4 gebruikt hem voor MCP | MIT | runtime | Symfony 7.4 LTS, patch ongeveer maandelijks | v7.4.23 (1 nov) |
| `symfony/translation` | API-meldingen en validatorfouten in `nl` en `en` (beslissing 21) | MIT | runtime | Symfony 7.4 LTS | v7.4.23 (1 nov) |

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

### T3. Foutafhandeling en testopzet (test eerst)

- `Shared\Domain\RuleViolation`; alle domeinfouten implementeren hem (beslissing 20); de listener voor
  `problem+json`; vertalingen `nl` en `en` voor elke code en voor de validator.
- Tests: elke rij van de tabel in beslissing 20 als functionele test op een testroute; de dekkingstest voor
  vertalingen en domeinfouten; een 500 bevat geen exceptietekst.
- `InMemoryKernel` en een basisklasse voor endpointtests met `actingAs(rol)` en `withToken(scopes)` (beslissing 31).

### T4. Tokens: authenticator en scopes (test eerst)

- Tests: geldig token geeft kanaal `api` en het lid als uitvoerder; elke weigerreden uit beslissing 2 geeft 401
  zonder body met `WWW-Authenticate: Bearer`, en een logregel met de reden; ook een ontbrekend of misvormd token
  (`Bearer` zonder geheim, ander formaat); een ingetrokken token werkt bij het eerstvolgende verzoek niet meer, en
  een token van een gedeactiveerd of gewist lid ook niet (review-focus 4, uit T7 van fase 1); `markUsed` hooguit eens
  per minuut.
- De scopetest (beslissing 33) en de listener (beslissing 17).
- Firewall `api_token` met request matcher op de header.

### T5. Inloggen met wachtwoord en sessie (test eerst)

- `MemberDirectory::identify()` en `identity()`, `MemberRepository::ofEmail()` met integratietest (beslissing 4).
- Tests: `POST /auth/login` met goed wachtwoord geeft een sessie met kanaal `web` en `account.logged_in`; fout
  wachtwoord, onbekend adres en gedeactiveerd lid geven dezelfde 401; de zesde mislukte poging binnen 15 minuten
  geeft 429; een v3-achtige hash wordt bij inloggen vernieuwd; een lid dat gedeactiveerd wordt, is bij het volgende
  verzoek uitgelogd (review-focus 4); een gewijzigde rol werkt bij het volgende verzoek; een muterend verzoek zonder
  geldige CSRF-controle geeft 403; `POST /auth/logout` wist sessie en cookie.
- Authenticator, user provider, CSRF-listener, entry point; auditregels uit beslissing 14.

### T6. Ingelogd blijven, inloglink en wachtwoord-reset (test eerst)

- `OneTimeLink` met migratie (alleen toevoegen) en `Account.credentialsChangedAt` (beslissingen 9 en 10).
- Use cases (Identity): `RequestLoginLink`, `LogInWithLink`, `RequestPasswordReset`, `ResetPassword`,
  `ConfirmPassword`.
- Tests: elke link één keer bruikbaar; verlopen na 15 of 60 minuten; een reset-geheim werkt niet als inloglink; een
  nieuwe aanvraag maakt de vorige ongeldig; 202 bij een onbekend adres zonder verschil in antwoord; na een reset en na
  wachtwoord wijzigen werkt een onthouden apparaat niet meer; `remember_me` houdt een sessie 90 dagen vast maar telt
  niet als recent (beslissing 15).
- `LoggingNotifier` zonder parameters, `DevLinkNotifier` in `dev` (beslissing 29).

### T7. Eigen account (test eerst)

- `GET /me`, `PATCH /me` (beslissing 27, met `ChangeOwnContactDetails`), `PUT /me/password` (beslissing 16),
  `GET/POST/DELETE /me/tokens` (`ListApiTokens` nieuw; beslissingen 15, 18 en 19), `GET /me/activity`,
  `GET /me/export` (eigen profiel, aanmeldingen, terugmeldingen, opmerkingen, tokens zonder geheim, eigen auditregels).
- Tests: het geheim staat alleen in het antwoord van `POST /me/tokens`, nooit in `GET`; een token kan geen token
  maken; zonder recente aanmelding geeft `POST /me/tokens` 403 met `reauthentication_required`; de export bevat geen
  gegevens van andere leden behalve weergavenamen.

### T8. Rondes en aanmelden (test eerst)

- `GET /rounds`, `GET /rounds/{id}`, `POST /rounds`, `PATCH /rounds/{id}`, `DELETE /rounds/{id}`,
  `POST /rounds/{id}/cancellation`, `GET /staffing-gaps`, `GET /me/rounds?walked=` (`ListMyRounds` nieuw),
  `PUT/DELETE /rounds/{id}/walkers/me` en `/walkers/{memberId}`.
- Tests: aan- en afmelden zijn idempotent (tweede `PUT` en tweede `DELETE` geven hetzelfde antwoord); volle ronde 409
  `round_is_full`; namens aanmelden alleen voor de coördinator; `GET /rounds` boven 93 dagen geeft 422; elke ronde
  in de uitvoer heeft `status`, `walkerCount`, `minimumWalkers`, `maximumWalkers`, `openSpots` en `daypart`.

### T9. Review-focus 2 via de API (test eerst)

Dezelfde rondes als T5 van fase 1, nu via de API, in memory en één keer door de database: een ronde om 22:00 op de
dag vóór en na beide wissels, aangemaakt met `+01:00`, `+02:00` en `Z`, komt terug als 22:00 met de juiste offset en
dagdeel `evening`, in `GET /rounds/{id}`, `GET /rounds` en `GET /me/rounds`.

### T10. Na de ronde en analyse (test eerst)

- `POST /rounds/{id}/reports`, `DELETE /reports/{id}`, `POST /rounds/{id}/comments`, `DELETE /comments/{id}`,
  `GET /reports?from=&to=&outcome=` (`ListRoundReports` nieuw: analist en coördinator, periode hooguit 93 dagen),
  `GET /rounds/{id}/history` (`GetRoundHistory` nieuw, coördinator).
- Tests: tweede terugmelding 409; notitie alleen zichtbaar voor de lopers van die ronde, coördinator en analist; de
  geschiedenis toont een gewist lid als "verwijderd lid".

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
- Regel 3 uitgebreid (beslissing 23) met een overtreding in de fixtures en een regel in `docs/architecture.md`.

### T15. HTTP-matrixtest en het `curl`-scenario (test eerst)

- `HttpMatrixTest` en zijn bewakingstest (beslissing 32).
- Databasetest: lid logt in, maakt een token, en `curl`-gelijk verzoek `PUT /rounds/{id}/walkers/me` met dat token
  meldt het aan; met `read` alleen geeft het 403.
- Voorbeelddata: wachtwoord en token van het demolid (beslissing 30); `docs/getting-started.md` beschrijft het
  `curl`-scenario.

### T16. Opengrep-regels

Per regel eerst het testbestand, dan de regel (beslissing 34). Lokaal gedraaid zoals in T14 van fase 1.

### T17. ADR 0006 en documentatie

- `docs/adr/0006-authentication.md`: beslissingen 1 tot en met 19 (twee firewalls, eigen authenticators, geen JWT,
  hash-patroon voor elk geheim, CSRF met terugval voor iOS 15, recent ingelogd, scopes per verzoek).
- `docs/architecture.md`: `RuleViolation`, `RecentAuthentication`, de uitbreiding van `MemberDirectory`, regel 3,
  de firewalls; `docs/testing.md`: `InMemoryKernel`, HTTP- en scopematrix, de nieuwe Opengrep-regels;
  `docs/configuration.md`: sessie, rate limits, geldigheid van links en tokens; `docs/glossary.md`: inloglink,
  wachtwoord-reset, `OneTimeLink`, scope, kanaal `web`; `docs/gotchas.md`: wat opvalt.
- `docs/plans/plan.md`: Fase 2 afvinken, de endpointtabel bijwerken (beslissingen 11, 25), de afwijkingen van
  beslissingen 4 en 17 in Fase 2; `CHANGELOG.md`; `AGENTS.md` (Commands) als er een commando bijkomt.

### T18. Verifiëren en opleveren

- `composer test` zonder database (de databasetests slaan over), `symfony composer test` met database en
  `symfony composer check`; uitvoer in het verslag, met elke check die niet draaide.
- Push en pull request naar `develop` na akkoord; CI groen (alle jobs); merge als squash na akkoord.

## Afspraken

1. Claude commit in deze privérepo, in kleine commits. Push, pull request en merge alleen na akkoord.
2. De database start de ontwikkelaar. Draait hij niet, dan meldt Claude dat en draait de databasetests niet.
   Voorbeelddata in de dev-database alleen na vragen.
3. Nieuwe afhankelijkheden pas na akkoord op de tabel hierboven.
4. Na elke taak wordt de tabel "Fasen en stand" in `docs/plans/plan.md` bijgewerkt.

## Open punten uit het plan

Gemeld, niet in deze fase opgelost:

- **Berichten gaan binnen de transactie uit**; versturen na de commit staat in fase 3. In deze fase komen er
  berichten bij (inloglink, reset, uitnodiging), die zo ook binnen de transactie vertrekken.
- **`anonymisedAt` uit beslissing 8 van fase 1** volgt in fase 5b of 6a.
- **Het slot op alle ledenrijen in `activeAdminsForUpdate()`** is een geaccepteerd risico (ADR 0005).
- **De hashketen in het auditlog** (beslissing 16 van fase 1) staat nog open.
- **De schakelaar die API-tokens en MCP uitzet** (plan, Datalek en Beveiligingsupdates) hoort bij de instellingen
  van fase 3; de tokenauthenticator krijgt dan een controle erop.
- **Links in mails met de ingestelde basis-URL** (review-focus 5) volgen in fase 3 en 6; in deze fase wijst een link
  naar een vast pad van de frontend.
- **`.htaccess` met de `Authorization`-header en de sessiemap in `shared/`** volgen met de mapindeling in fase 6a.
- **`/config`, `/admin/settings` en `/media`** (fase 3), **`/stats`** (fase 4 en U5), **`/system/tick`** (fase 6a) en
  **`/admin/maintenance`** (fase 6b) horen niet bij deze fase.
- **Bewaartermijnen** van inlogregels (90 dagen) en van gebruikte of verlopen eenmalige links worden opgeruimd door de
  scheduler in fase 6a.
