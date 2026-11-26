# Fase 2, aanvulling: te makkelijke wachtwoorden weigeren

Status: goedgekeurd op 26 november 2026, met 15 tekens (open vraag 1) en de controle op uitgelekte wachtwoorden
later, met Have I Been Pwned of een andere dienst na onderzoek (open vraag 3).

Bron: `docs/plans/phase-2.md` (beslissing 15, 16 en 20, T6 en T7), `docs/plans/plan.md` (Privacy per externe dienst),
`docs/configuration.md`, `src/Identity/UseCase/SetPassword/`, `src/Shared/Domain/MemberDirectory.php`,
`src/Shared/Infrastructure/Http/Problem.php` en `ProblemResponsesProcessor.php`, `translations/problems.*.yaml`,
`docs/design/walker/mobile/set-password.png` en `composer.json`, op `develop` (`5e64e88`).

## Doel en klaar-criterium

Een lid kan geen wachtwoord instellen dat makkelijk te raden is: geen bekend wachtwoord, niet de eigen naam of het
eigen e-mailadres, en niet te eenvoudig. Een wachtzin als "fiets lamp regen" blijft mogelijk.

Klaar als:

- `PUT /me/password` een wachtwoord uit de meegeleverde lijst, met de eigen naam of met het deel vóór de @ van het
  eigen e-mailadres, of onder "medium" van Symfony's `PasswordStrength`, weigert met 422 en code `password_too_weak`;
- de vergelijking met lijst, naam en e-mailadres hoofdletters en spaties negeert;
- "fiets lamp regen" geaccepteerd wordt (test);
- de OpenAPI-beschrijving de regels en `password_too_weak` noemt;
- beslissing 34 in `docs/plans/phase-2.md` staat, Have I Been Pwned in de tabel "Privacy per externe dienst" in
  `docs/plans/plan.md`, en `docs/configuration.md` en `CHANGELOG.md` bijgewerkt zijn;
- `symfony composer check` groen is.

## Vastgesteld in de code

1. **De minimumlengte is 15 tekens, niet 12.** `SetPassword` heeft `#[Assert\Length(min: 15)]`, met NIST SP 800-63B
   als reden (15 tekens voor een wachtwoord dat de enige factor is); `SetPasswordTest` en `docs/configuration.md`
   zeggen hetzelfde. Alleen het goedgekeurde scherm `set-password.png` noemt 12. Zie open vraag 1.
2. **Te kort en te lang geven nu 422 `invalid_input`** met een fout per veld (`violations`), via de validator op de
   invoer. Een `InvalidValue` uit de handler geeft 422 met een eigen code (`Problem::classify()`).
3. **Identity kent naam en e-mailadres niet zelf.** `Shared\Domain\MemberDirectory` geeft ze: `profilesOf()` (naam
   en weergavenaam) en `emailOf()`. Er is geen nieuwe interface nodig.
4. **Symfony's sterkteschatting is los aan te roepen**: `PasswordStrengthValidator::estimateStrength()` is publiek en
   statisch. Gemeten: "fiets lamp regen" 2 (medium), "correct horse battery" 2, "ditwachtwoordisgeheim" 2,
   "welkom123456" 1, "Welkom01!Welkom" 1. Dus "ditwachtwoordisgeheim" komt alleen via de lijst niet door.
5. **`NotCompromisedPassword` vraagt `symfony/http-client`**, en die staat niet in `composer.json`.
6. **Er is geen MCP-tool voor wachtwoorden**; alleen REST (`PUT /me/password`) en de voorbeelddata roepen
   `SetPassword` aan. Het voorbeeldwachtwoord (`voorbeeld-wachtwoord-buurt`) en de wachtwoorden in de tests moeten
   door de nieuwe controle komen.
7. **De frontend staat nog niet op `develop`**; de hulptekst bij het veld hoort bij het scherm
   `set-password` van fase 5.

## Open vragen

1. **12 of 15 tekens?** Je schreef "minimaal 12 tekens (blijft)", maar de code eist 15 (vastgesteld 1). Mijn advies:
   15 houden. Een wachtwoord is hier de enige factor, en 15 is de grens die we in fase 2 kozen. De lijst en de
   sterktecontrole maken 12 minder erg, maar niet gelijkwaardig. De hulptekst wordt dan "Minstens 15 tekens. …".
   Kies je 12, dan verlaag ik de grens in dezelfde wijziging en pas ik `SetPasswordTest` en `docs/configuration.md` aan.
2. **Te kort: `invalid_input` of `password_too_weak`?** Advies: lengte blijft `invalid_input` met een fout bij het
   veld (vastgesteld 2). De melding "te makkelijk te raden, kies andere woorden" past slecht bij een te kort
   wachtwoord, en de frontend controleert de lengte al vóór het versturen. `password_too_weak` is dan voor lijst, naam,
   e-mailadres en sterkte samen.
3. **Have I Been Pwned nu bouwen of alleen vastleggen?** Bouwen vraagt een nieuwe afhankelijkheid
   (`symfony/http-client`, vastgesteld 5) en een nieuwe instelling. Advies: nu alleen het besluit in beslissing 34 en
   de rij in de privacytabel; de instelling volgt in een eigen issue. Wil je het wel nu, dan voeg ik
   `symfony/http-client` toe en een instelling `security.password_breach_check` (standaard uit, met
   `skipOnError`, zodat een onbereikbare dienst niemand blokkeert).
4. **Welke lijst?** Voorstel: `10k-most-common.txt` uit SecLists (MIT, bronvermelding in de kop van het bestand),
   plus een eigen Nederlandse lijst. Nadeel: van de 10.000 zijn er bijna geen 15 tekens of langer, dus die lijst houdt
   weinig tegen wat de lengte niet al tegenhoudt; het Nederlandse deel doet het meeste werk. Alternatief: uit een
   grotere lijst van SecLists alleen de wachtwoorden van minstens 15 tekens (na weghalen van spaties). Ik tel het na
   het downloaden en noem de aantallen bij de review.

   Uitkomst: van de 10.000 is er één van 15 tekens of langer. De grotere lijsten (`Pwdb_top-1000000.txt`: 5.307 van
   15 tekens of langer, de Nederlandse lijst van SecLists: 215.254) bevatten herkenbare namen van echte mensen, en die
   horen niet in de repo (`AGENTS.md`, Data en secrets). Dus: de 10.000 van SecLists plus 69 eigen Nederlandse
   wachtwoorden: 10.070 regels (SecLists heeft er 10.001).

## Beslissing 34 (tekst voor `docs/plans/phase-2.md`)

Komt direct na beslissing 16, onder "Inloggen", met het nummer 34 zodat de andere nummers blijven kloppen:

> 34. **Een te makkelijk wachtwoord wordt geweigerd** (besloten op 26 november 2026). Bij wachtwoord instellen, ook
>     bij wijzigen:
>     - minimaal 15 tekens (beslissing 16, T6);
>     - niet in een meegeleverde lijst met veelgebruikte wachtwoorden (de 10.000 meest gebruikte, plus Nederlandse
>       als "welkom123456" en "ditwachtwoordisgeheim"); de lijst staat in de repo, er gaat niets naar een externe
>       dienst;
>     - niet met de naam of het deel vóór de @ van het e-mailadres van het lid erin;
>     - minstens "medium" volgens Symfony's `PasswordStrength`, als extra vangnet; "fiets lamp regen" mag.
>
>     Lijst, naam en e-mailadres worden vergeleken zonder hoofdletters en spaties. Elke weigering geeft 422 met één
>     code, `password_too_weak`: welke regel het was, blijft buiten het antwoord. Een controle op uitgelekte
>     wachtwoorden (`NotCompromisedPassword`, Have I Been Pwned) komt er alleen als instelling per vereniging,
>     standaard uit (`plan.md`, Privacy per externe dienst).

(De "15" volgt open vraag 1.)

## Taken

### T1. Branch en taakplan

- Branch `feature/guessable-passwords` vanaf `develop` (`5e64e88`), dit plan. Wachten op akkoord.

### T2. De controle in het domein (test eerst)

- Tests (`tests/Identity/Domain/GuessablePasswordsTest.php`, in memory, met een lijst en een schatter als fake):
  een wachtwoord uit de lijst wordt geweigerd, ook met andere hoofdletters en spaties; een wachtwoord met de eigen
  naam (voornaam, achternaam of weergavenaam) wordt geweigerd; een wachtwoord met het deel vóór de @ wordt geweigerd;
  een wachtwoord onder "medium" wordt geweigerd; "fiets lamp regen" wordt geaccepteerd; een naamdeel korter dan
  4 tekens telt niet mee (zie hieronder).
- Code:
  - `Identity\Domain\GuessablePasswords` (final readonly): `isGuessable(string $password, list<string>
    $personalWords): bool`. Normaliseren: `mb_strtolower`, alle witruimte weg. Naam en e-mailadres worden in delen
    gesplitst (spatie, punt, streepje, liggend streepje, plus), en elk deel van minstens 4 tekens mag niet in het
    wachtwoord staan. Waarom 4: anders weigert "Jan" ook "januari" en "van" ook "vanavond", en een naam van 2 of
    3 tekens maakt een wachtzin niet makkelijker te raden.
  - `Identity\Domain\CommonPasswords` (interface): `contains(string $normalised): bool`.
  - `Identity\Domain\PasswordStrengthEstimate` (interface): `isAtLeastMedium(string $password): bool`.
  - `Identity\Domain\PasswordTooWeak` (`InvalidValue`), code `password_too_weak`.

### T3. Lijst en sterkte in de infrastructuur (test eerst)

- Tests: `CommonPasswordListTest` leest het echte bestand en vindt "welkom123456", "ditwachtwoordisgeheim" en een
  Engels voorbeeld, genormaliseerd; slaat commentaarregels over. `SymfonyPasswordStrengthEstimateTest`:
  "fiets lamp regen" is medium, "welkom123456" niet.
- Code: `Identity\Infrastructure\Password\common-passwords.txt` (één per regel, al genormaliseerd, kop met bron en
  licentie), `CommonPasswordList` (leest het bestand één keer per verzoek in een set) en
  `SymfonyPasswordStrengthEstimate` (`PasswordStrengthValidator::estimateStrength()` tegen
  `PasswordStrength::STRENGTH_MEDIUM`). De lijst wordt bij het downloaden genormaliseerd en ontdubbeld.

### T4. De use case (test eerst)

- Tests in `SetPasswordHandlerTest`: in de lijst, met de eigen naam, met het eigen e-mailadres geven
  `PasswordTooWeak`; dan staat er geen hash, geen auditregel en blijft de sessie zoals hij was. Een goede wachtzin
  wordt opgeslagen. `SetPasswordTest` houdt de lengtegrens (te kort).
- Code: `SetPasswordHandler` krijgt `MemberDirectory` en `GuessablePasswords`, en controleert na de autorisatie en de
  recente sessie, vóór het hashen. Naam, weergavenaam en e-mailadres komen uit `profilesOf()` en `emailOf()`.
- `SetPasswordControllerTest`: `PUT /me/password` met een wachtwoord uit de lijst geeft 422 met code
  `password_too_weak` en de Nederlandse titel.
- `translations/problems.nl.yaml`: `password_too_weak: Dit wachtwoord is te makkelijk te raden. Kies andere woorden.`;
  `problems.en.yaml`: `This password is too easy to guess. Choose other words.`

### T5. OpenAPI

- `SetPassword::$password` krijgt een `OA\Property`-beschrijving met de regels. `SetPasswordController` krijgt een
  422 met de hand: `invalid_input` voor de lengte, `password_too_weak` voor lijst, naam, e-mailadres en sterkte (een
  antwoord met de hand blijft staan, `ProblemResponsesProcessor`).
- De OpenAPI-tests (`OpenApiDocumentTest`, `OpenApiCoverageTest`, `OpenApiNamingTest`) blijven groen.

### T6. Documentatie

- Beslissing 34 in `docs/plans/phase-2.md`.
- `docs/plans/plan.md`, tabel "Privacy per externe dienst", een rij:

  | Dienst | Welke gegevens gaan erheen | Risico | Maatregel |
  | --- | --- | --- | --- |
  | Have I Been Pwned (controle op uitgelekte wachtwoorden) | De eerste 5 tekens van de SHA-1-hash van het nieuwe wachtwoord en het IP-adres van de server; nooit het wachtwoord, de naam of het e-mailadres | De dienst ziet dat er op dat moment een wachtwoord wordt ingesteld, niet welk | Standaard uit, per vereniging aan te zetten; een onbereikbare dienst blokkeert niemand |

  En dezelfde dienst in de tabel "Welke functie hangt waarvan af" (zonder de dienst: alleen de lijst in de repo).
- `docs/configuration.md`: de rij Password noemt lijst, naam, e-mailadres en sterkte.
- `CHANGELOG.md`: een regel.
- De hulptekst "Minstens 15 tekens. Kies een paar woorden die niet bij elkaar horen, bijvoorbeeld “fiets lamp
  regen”. Geen bekende zin, en niet je naam." staat in beslissing 34 als tekst voor het veld. Het scherm
  `set-password` (canvas en afbeelding) toont nog "12" en de oude tekst: dat valt onder fase 5 en is hier buiten
  scope.

### T7. Verifiëren en opleveren

- `symfony composer check` volledig, plus de tests die de wijziging raakt (HttpMatrixTest, OpenAPI-tests,
  ArchitectureRulesTest, ModuleStructureTest).
- Commit `feat(identity): reject guessable passwords`. Vóór push vraag ik akkoord.

## Kritiek en grenzen

- **Alleen exacte treffers in de lijst.** "welkom123456!" staat er niet in en komt alleen via de sterkteschatting
  niet door als die laag uitvalt. Delen van een lijstwoord zoeken geeft veel valse treffers op wachtzinnen; dat doe ik
  niet.
- **Eén foutcode verbergt welke regel het was.** Goed tegen uitproberen, minder hulp voor het lid; de hulptekst noemt
  daarom naam en bekende zinnen.
- **De naamcontrole kost twee opvragingen per wachtwoordwijziging**; dat is verwaarloosbaar.
- **Bestaande wachtwoorden blijven geldig**; de controle werkt alleen bij instellen en wijzigen.
