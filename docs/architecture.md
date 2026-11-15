# Architecture

Buurtpreventie is a modular monolith: one Symfony app with three entry points (REST, MCP and the
scheduler) that all call the same use cases. A rule such as "at least two walkers" therefore lives
in the code once and holds for the browser, an AI client and cron alike. The frontend lives apart in
`frontend/` and talks only to `/api/v1`.

The reasons for this structure are in [ADR 0001](adr/0001-modular-monolith-with-use-case-folders.md).
Dutch domain terms and their code names are in [glossary.md](glossary.md).

## Folder layout

```text
src/
  <Module>/
    Domain/           entities, value objects, rules, repository interfaces
    UseCase/
      <Name>/         one folder per use case, see below
    Infrastructure/   Doctrine repositories, e-mail, Symfony integrations, console commands
  Shared/
    Domain/           building blocks for every module: value objects and interfaces such as AuditTrail
    Infrastructure/   shared technical code: Doctrine types and transaction, Symfony authorization, the generator
  Kernel.php
tests/                the same folders as src/, plus Architecture/
```

| Module | Covers |
| --- | --- |
| `Patrol` | Rounds, signing up, buddies, round reports |
| `Membership` | Members, roles, consent, certificate |
| `Identity` | Login, login links, passwords, API tokens |
| `Audit` | Recording and showing the audit log |
| `Settings` | Branding and texts |
| `Maintenance` | Health check, reminders, updates, installer, v3 import |

Every module has exactly `Domain/`, `UseCase/` and `Infrastructure/`. `Shared` is not a module: it
has no use cases. `tests/Architecture/ModuleStructureTest.php` guards this; PHPat sees classes only,
not empty folders.

### A use case: fixed files

A `UseCase/<Name>/` folder holds only these files, all `final readonly`:

| File | Role | Required |
| --- | --- | --- |
| `<Name>.php` | Input: the contract, with Validator constraints | yes |
| `<Name>Handler.php` | Permissions, domain rules and audit log; returns the output | yes |
| `<Name>Output.php` | Output: scalars and arrays only, no entities | when there is output |
| `<Name>Controller.php` | REST adapter: request to input, call the handler, return JSON | when there is a REST entry |
| `<Name>Tool.php` | MCP adapter: tool call to input, call the handler | when there is an MCP entry |

The tool gets its MCP binding in phase 5; until then it is a plain class that calls the handler.

## Worked example: CheckHealth

`Maintenance/UseCase/CheckHealth` answers `GET /api/v1/health` with `{"status":"ok"}`. The folder
was created with the generator and filled in afterwards.

```bash
bin/console app:make:use-case Maintenance CheckHealth
```

**Input** (`CheckHealth.php`). The health check asks for nothing, so the input is empty. The handler
still takes it, so REST and MCP call it the same way.

```php
final readonly class CheckHealth
{
}
```

**Output** (`CheckHealthOutput.php`). Public, plain fields only: the controller turns the object into
JSON directly.

```php
final readonly class CheckHealthOutput
{
    public function __construct(public string $status)
    {
    }
}
```

**Handler** (`CheckHealthHandler.php`). The rules live here. The endpoint is public, so the answer
carries no version or environment details.

```php
final readonly class CheckHealthHandler
{
    public function __invoke(CheckHealth $input): CheckHealthOutput
    {
        return new CheckHealthOutput(status: 'ok');
    }
}
```

**Controller** (`CheckHealthController.php`). Thin: route, build the input, call the handler. The
`/api/v1` prefix comes from `config/routes.yaml`.

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

A use case with input receives it through `#[MapRequestPayload]`; the generator sets that up.

**Tool** (`CheckHealthTool.php`). As thin as the controller, with the same handler.

**Tests** in `tests/Maintenance/UseCase/CheckHealth/`: a unit test for the handler, a test that the
tool returns the handler output unchanged, and a functional test for the endpoint (the answer, and
that `POST` is refused).

## Adding a feature

1. Pick the module: where does the concept belong according to [glossary.md](glossary.md)?
2. Create the folder: `bin/console app:make:use-case <Module> <Name>`, with a PascalCase name that
   says what happens, such as `SignUpForRound`.
3. Write the handler test first in `tests/<Module>/UseCase/<Name>/` and watch it fail.
4. Fill in input and output, then the handler. A write action gets a Voter, an audit entry and a row
   in the matrix test (from phases 1 and 2).
5. Set the route and method in the controller. Delete the controller or tool when the use case has
   no such entry point.
6. Run `composer check`. An architecture error names the rule and links to the section below.
7. Update `docs/` and `CHANGELOG.md`.

## Shared building blocks

A handler reaches storage, rights, the audit log and messages only through interfaces in
`Shared\Domain`, so its tests run in memory with a small fake per interface.

| `Shared\Domain` | What it does | Implementation |
| --- | --- | --- |
| `Transaction` | Runs an operation as one unit: a change and its audit entry are stored together or not at all | `Shared\Infrastructure\Doctrine\DoctrineTransaction` |
| `ExecutionContext`, `Channel` | The member performing the action with their roles (an `Actor`), or none for the system, and the channel (`web`, `api`, `mcp`, `cron`, `console`) | the entry point: console commands and tests in phase 1, HTTP and MCP in phases 2 and 5 |
| `AuditTrail`, `AuditedAction` | Records an action; time, actor and channel come from the clock and the execution context | `Audit\Infrastructure\Persistence\DoctrineAuditTrail` |
| `MemberNames` | The display name of a member, or none for an erased one, so another module can record or show who acted | `Membership\Infrastructure\RepositoryMemberNames` |
| `Authorization`, `AccessDenied` | `denyUnlessGranted()` asks the voters of every module and throws `AccessDenied` on a refusal | `Shared\Infrastructure\Security\SymfonyAuthorization` |
| `Notifier`, `Notification` | Sends a member a message by type and parameters; the domain holds no texts | `Shared\Infrastructure\Notification\LoggingNotifier` in phase 1, e-mail in phase 3 |
| `Role`, `Actor` | The fixed roles `walker`, `coordinator`, `analyst` and `admin`, without inheritance, and a member with their roles, as the policies of every module decide on them | |
| `NotFound` | No item of a kind has a given id; entry points turn it into their own answer, such as HTTP 404 | |
| `LocalTime` | Converts an instant to `Europe/Amsterdam`, the clock of dayparts, calendar days and everything a walker reads | |
| `EmailAddress` | An address trimmed and in lower case, with accents kept | |
| `LookupKey` | The search and uniqueness key of a name: no case, accents or extra whitespace | |

Time comes from `Psr\Clock\ClockInterface` in handlers and as a `DateTimeImmutable $now` argument in
domain methods. Instants are stored with the Doctrine type `utc_datetime_immutable`
(`Shared\Infrastructure\Doctrine\UtcDateTimeImmutableType`), which writes and reads UTC whatever the
time zone of PHP.

When an operation in `Transaction` throws, Doctrine rolls back and closes the entity manager. A
process that handles more than one unit of work, such as a worker, resets it in between.

A handler that calls the handler of another module, such as `DeactivateMember` calling
`WithdrawMemberFromUpcomingRounds`, `RevokeMemberApiTokens` and `EraseActorName`, runs that handler's transaction inside
its own. Doctrine nests it with a savepoint, so a failure in either undoes both.

## Rules

The rules live in `tests/Architecture/ArchitectureRules.php` and run as [PHPat](https://www.phpat.dev/)
rules inside PHPStan. Every error message links to a section below.
`tests/Architecture/ArchitectureRulesTest.php` proves with deliberate violations in
`tests/Architecture/Fixtures/` that each rule fires. New modules and use cases are covered
automatically: the rules read the folders under `src/`.

### Rule 1: domain

Classes in `<Module>\Domain` depend only on their own `Domain`, on `Shared\Domain`, on Doctrine
mapping attributes, on Doctrine Collections and on Symfony Uid. The same holds for `Shared\Domain`.

**Why.** Domain rules can then be tested in milliseconds with in-memory repositories, without HTTP,
MCP or a database. A domain class that knows a repository implementation or another module makes
every test heavy and every change risky. Identifiers are UUIDv7 values from Symfony Uid: a class of
its own per entity would only wrap the same value. The rest of Doctrine ORM, such as the entity
manager, stays out.

**Fix.** Put an interface in `Domain` and the implementation in `Infrastructure`. If you need
something from another module, call its use case from the handler.

### Rule 2: input and handler

Input and handler classes do not depend on HttpFoundation, HttpKernel, Routing, the MCP SDK,
Doctrine ORM, Doctrine DBAL or any `Infrastructure`.

**Why.** REST and MCP call the same handler with the same input. A handler that knows which entry
point called it lets the two drift apart, so that a rule holds for only one of them. A handler that
talks to Doctrine needs a database in every test; through interfaces its rules run in memory.

**Fix.** Put what the entry point provides (user, channel) in the input, or let the handler fetch it
through an interface in `Domain`, such as `ExecutionContext`. Load and save through a repository
interface in the module's `Domain`, and wrap a change and its audit entry in `Transaction`.

### Rule 3: controller, tool and output

- A controller depends only on classes in its own use-case folder and on Symfony's HttpFoundation,
  `HttpKernel\Attribute` and `Routing\Attribute`.
- A tool depends only on classes in its own use-case folder and on the MCP SDK.
- An `Output` depends on no `Domain` at all.

**Why.** Adapters stay thin, so REST and MCP allow exactly the same. The output is the public
contract: an entity in it would leak fields such as contact details that are visible only with
consent.

**Fix.** Move logic into the handler. Put only the fields the client needs in the output, as scalars
or arrays.

### Rule 4: modules and Shared

A module uses another module only through its use cases (input, handler and output) or through an
interface in `Shared\Domain` that a module implements, such as `AuditTrail`. `Shared` depends on no
module.

**Why.** Each module can then change its internals freely. `Shared` holds building blocks for all
modules; if it knew a module itself, the two would form a cycle.

**Fix.** Call the other module's handler, or define an interface in `Shared\Domain` and let the module
that has the knowledge implement it.

### Rule 5: Twig

Only classes in `<Module>\Infrastructure\Mail` and `Maintenance\Infrastructure\Installer` use Twig.

**Why.** Screens live in `frontend/` and use only the API. The backend renders e-mails and the one-off
installer, nothing else.

**Fix.** Return the data through a use case and build the screen in `frontend/`.

### Rule 6: frontend and OpenAPI

The frontend calls only endpoints listed in the OpenAPI spec. This contract test arrives with the
frontend in phase 4.
