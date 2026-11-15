# Architectuur

Buurtpreventie is een modulaire monoliet: één Symfony-app met drie ingangen (REST, MCP en de
scheduler) die allemaal dezelfde use cases aanroepen. Een regel zoals "minimaal twee lopers" staat
daardoor één keer in de code en geldt voor browser, AI-client en cron tegelijk. De frontend staat los
in `frontend/` en praat alleen met `/api/v1`.

De keuze voor deze structuur staat in [ADR 0001](adr/0001-modular-monolith-with-use-case-folders.md).

## Mapindeling

```text
src/
  <Module>/
    Domain/           entiteiten, value objects, regels, repository-interfaces
    UseCase/
      <Naam>/         één map per use case, zie hieronder
    Infrastructure/   Doctrine-repositories, e-mail, Symfony-koppelingen, console-commando's
  Shared/
    Domain/           bouwstenen voor elke module: klok, ID's, interfaces zoals AuditTrail
    Infrastructure/   gedeelde techniek, zoals de generator app:make:use-case
  Kernel.php
tests/                dezelfde mappen als src/, plus Architecture/
```

| Module | Gaat over |
| --- | --- |
| `Patrol` | Rondes, aanmelden, loopmaatjes, terugmelden |
| `Membership` | Leden, rollen, toestemming, certificaat |
| `Identity` | Inloggen, inloglinks, wachtwoorden, API-tokens |
| `Audit` | Auditlog vastleggen en tonen |
| `Settings` | Branding en teksten |
| `Maintenance` | Gezondheidscheck, herinneringen, updates, installer, conversie uit v3 |

Elke module heeft precies `Domain/`, `UseCase/` en `Infrastructure/`. `Shared` is geen module: het
heeft geen use cases. `tests/Architecture/ModuleStructureTest.php` bewaakt dit; PHPat ziet alleen
klassen, geen lege mappen.

### Een use case: vaste bestanden

Een map `UseCase/<Naam>/` bevat alleen deze bestanden, allemaal `final readonly`:

| Bestand | Rol | Verplicht |
| --- | --- | --- |
| `<Naam>.php` | Invoer: het contract, met Validator-regels | ja |
| `<Naam>Handler.php` | Rechten, domeinregels en auditlog; geeft de uitvoer terug | ja |
| `<Naam>Output.php` | Uitvoer: alleen scalars en arrays, geen entiteiten | als er uitvoer is |
| `<Naam>Controller.php` | REST-adapter: verzoek naar invoer, handler aanroepen, JSON terug | als er een REST-ingang is |
| `<Naam>Tool.php` | MCP-adapter: toolaanroep naar invoer, handler aanroepen | als er een MCP-ingang is |

De tool krijgt zijn MCP-koppeling in fase 5; tot dan is het een gewone klasse die de handler aanroept.

## Uitgewerkt voorbeeld: CheckHealth

`Maintenance/UseCase/CheckHealth` beantwoordt `GET /api/v1/health` met `{"status":"ok"}`. De map is
gemaakt met de generator en daarna ingevuld.

```bash
bin/console app:make:use-case Maintenance CheckHealth
```

**Invoer** (`CheckHealth.php`). De gezondheidscheck vraagt niets, dus de invoer is leeg. De handler
krijgt hem toch, zodat REST en MCP hem op dezelfde manier aanroepen.

```php
final readonly class CheckHealth
{
}
```

**Uitvoer** (`CheckHealthOutput.php`). Alleen publieke, gewone velden: de controller zet het object
direct om naar JSON.

```php
final readonly class CheckHealthOutput
{
    public function __construct(public string $status)
    {
    }
}
```

**Handler** (`CheckHealthHandler.php`). Hier zitten de regels. Het endpoint is openbaar, daarom
staat er geen versie of omgeving in het antwoord.

```php
final readonly class CheckHealthHandler
{
    public function __invoke(CheckHealth $input): CheckHealthOutput
    {
        return new CheckHealthOutput(status: 'ok');
    }
}
```

**Controller** (`CheckHealthController.php`). Dun: route, invoer maken, handler aanroepen. De
prefix `/api/v1` komt uit `config/routes.yaml`.

```php
#[AsController]
final readonly class CheckHealthController
{
    public function __construct(private CheckHealthHandler $handler)
    {
    }

    #[Route('/health', name: 'maintenance_check_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new CheckHealth()));
    }
}
```

Een use case met invoer krijgt die via `#[MapRequestPayload]`; de generator zet dat klaar.

**Tool** (`CheckHealthTool.php`). Even dun als de controller, met dezelfde handler.

**Tests** in `tests/Maintenance/UseCase/CheckHealth/`: een unit-test voor de handler, een test dat de
tool de uitvoer van de handler ongewijzigd teruggeeft, en een functionele test voor het endpoint
(antwoord en het weigeren van `POST`).

## Nieuwe functie toevoegen

1. Kies de module: waar hoort het begrip thuis volgens [glossary.md](glossary.md)?
2. Maak de map: `bin/console app:make:use-case <Module> <Naam>`, met een naam in PascalCase die zegt
   wat er gebeurt, zoals `SignUpForRound`.
3. Schrijf eerst de test van de handler in `tests/<Module>/UseCase/<Naam>/`, en zie hem falen.
4. Vul invoer en uitvoer in, en daarna de handler. Een schrijvende actie krijgt een Voter, een
   auditregel en een rij in de matrixtest (vanaf fase 1 en 2).
5. Pas in de controller route en methode aan. Verwijder controller of tool als de use case die ingang
   niet heeft.
6. Draai `composer check`. Een architectuurfout noemt de regel en verwijst naar de sectie hieronder.
7. Werk `docs/` en `CHANGELOG.md` bij.

## Regels

De regels staan in `tests/Architecture/ArchitectureRules.php` en draaien als
[PHPat](https://www.phpat.dev/)-regels in PHPStan. Elke foutmelding verwijst naar een sectie
hieronder. `tests/Architecture/ArchitectureRulesTest.php` bewijst met opzettelijke overtredingen in
`tests/Architecture/Fixtures/` dat elke regel afgaat. Nieuwe modules en use cases vallen er vanzelf
onder: de regels lezen de mappen onder `src/`.

### Regel 1: domein

Klassen in `<Module>\Domain` mogen alleen afhangen van de eigen `Domain`, van `Shared\Domain`, van
Doctrine-mapping-attributen en van Doctrine Collections. Voor `Shared\Domain` geldt hetzelfde.

**Waarom.** Domeinregels zijn dan in milliseconden te testen met in-memory repositories, zonder HTTP,
MCP of database. Een domeinklasse die een repository of een andere module kent, maakt elke test
zwaar en elke wijziging riskant.

**Oplossen.** Zet een interface in `Domain` en de implementatie in `Infrastructure`. Heb je iets van
een andere module nodig, roep dan diens use case aan vanuit de handler.

### Regel 2: invoer en handler

Invoer- en handlerklassen hangen niet af van HttpFoundation, HttpKernel, Routing, de MCP SDK of
welke `Infrastructure` dan ook.

**Waarom.** REST en MCP roepen dezelfde handler aan met dezelfde invoer. Weet de handler welke ingang
hem aanriep, dan gaan de twee uit elkaar lopen en geldt een regel maar voor één van beide.

**Oplossen.** Zet wat de ingang levert (gebruiker, kanaal) in de invoer of laat de handler het via een
interface uit `Domain` ophalen.

### Regel 3: controller, tool en uitvoer

- Een controller hangt alleen af van klassen in de eigen use-case-map en van Symfony's
  HttpFoundation, `HttpKernel\Attribute` en `Routing\Attribute`.
- Een tool hangt alleen af van klassen in de eigen use-case-map en van de MCP SDK.
- Een `Output` hangt van geen enkele `Domain` af.

**Waarom.** Adapters zijn dun, zodat REST en MCP precies hetzelfde toestaan. De uitvoer is het
publieke contract: een entiteit daarin zou velden lekken zoals contactgegevens die alleen met
toestemming zichtbaar mogen zijn.

**Oplossen.** Verplaats logica naar de handler. Zet in de uitvoer alleen de velden die de client
nodig heeft, als scalars of arrays.

### Regel 4: modules onderling

Een module gebruikt een andere module alleen via diens use cases (invoer, handler en uitvoer) of via
een interface in `Shared\Domain` die een module implementeert, zoals `AuditTrail`. `Shared` hangt van
geen enkele module af.

**Waarom.** Elke module kan zijn binnenkant dan vrij veranderen. `Shared` bevat bouwstenen voor alle
modules; als het zelf een module kent, ontstaat een kring.

**Oplossen.** Roep de handler van de andere module aan, of definieer een interface in
`Shared\Domain` en laat de module die het weet hem implementeren.

### Regel 5: Twig

Alleen klassen in `<Module>\Infrastructure\Mail` en `Maintenance\Infrastructure\Installer` gebruiken
Twig.

**Waarom.** Schermen staan in `frontend/` en gebruiken alleen de API. De backend rendert alleen
e-mails en de eenmalige installer.

**Oplossen.** Geef de gegevens terug via een use case en bouw het scherm in `frontend/`.

### Regel 6: frontend en OpenAPI

De frontend roept alleen endpoints aan die in de OpenAPI-spec staan. Deze contracttest komt met de
frontend in fase 4.
