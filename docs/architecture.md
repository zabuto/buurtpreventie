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
    Domain/           entities with their mapping, value objects, rules, guards, policies, repository interfaces
    UseCase/
      <Name>/         one folder per use case, see below
    Infrastructure/
      Persistence/    Doctrine repositories: the only place for queries
      Security/       voters
                      and other integrations: console commands, sample data, e-mail
  Shared/
    Domain/           building blocks for every module: value objects and interfaces such as AuditTrail
    Infrastructure/   shared technical code: Doctrine types and transaction, Symfony authorization, the generator
  Kernel.php
tests/                the same folders as src/, plus Architecture/, Database/, Dependencies/ and Tools/
```

| Module | Covers |
| --- | --- |
| `Patrol` | Rounds, signing up, buddies, round reports |
| `Membership` | Members, roles, consent, certificate |
| `Identity` | Login, login links, passwords, API tokens |
| `Audit` | Recording and showing the audit log |
| `Settings` | The default minimum and maximum walkers of a new round; branding and texts from phase 3 |
| `Maintenance` | Health check, updates, installer, legacy import |
| `SampleData` | Made-up sample data, loaded through the use cases of the other modules; see [Sample data](#sample-data) |

Every module has exactly `Domain/`, `UseCase/` and `Infrastructure/`. `Shared` is not a module: it
has no use cases. `tests/Architecture/ModuleStructureTest.php` guards this; PHPat sees classes only,
not empty folders.

### A use case: fixed files

A `UseCase/<Name>/` folder holds only these files, all `final readonly`:

| File | Role | Required |
| --- | --- | --- |
| `<Name>.php` | Input: the contract, with Validator constraints | yes |
| `<Name>Handler.php` | Orchestration: permission, transaction, domain calls, audit entry, notifications; returns the output | yes |
| `<Name>Output.php` | Output: scalars and arrays only, no entities | when there is output |
| `<Name>Controller.php` | REST adapter: request to input, call the handler, return JSON | when there is a REST entry |
| `<Name>Tool.php` | MCP adapter: tool call to input, call the handler | when there is an MCP entry |

The tool gets its MCP binding in phase 4; until then it is a plain class that calls the handler.

## Worked example: CheckHealth

`Maintenance/UseCase/CheckHealth` answers `GET /api/v1/health` with `{"status":"ok"}`. The folder
was created with the generator and filled in afterwards.

```bash
symfony console app:make:use-case Maintenance CheckHealth
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
2. Create the folder: `symfony console app:make:use-case <Module> <Name>`, with a PascalCase name that
   says what happens, such as `SignUpForRound`.
3. Write the handler test first in `tests/<Module>/UseCase/<Name>/` and watch it fail.
4. Fill in input and output, then the handler. A write handler carries `#[WriteAction]` with its voter
   attribute and audit action, a rule in the policy of its module, and rows in
   `tests/Shared/AuthorizationMatrixTest.php`; a read handler carries `#[ReadAction]`.
   `tests/Shared/HandlerGuardTest.php` fails when one of these is missing.
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
| `ExecutionContext`, `Channel` | The member performing the action with their roles (an `Actor`), or none for the system, and the channel (`web`, `api`, `mcp`, `cron`, `console`) | `Shared\Infrastructure\Security\TokenExecutionContext`, which reads Symfony's token storage; `RunAs` fills it for console commands, fixtures and the scheduler, an authenticator for HTTP and MCP |
| `AuditTrail`, `AuditedAction` | Records an action; time, actor and channel come from the clock and the execution context | `Audit\Infrastructure\Persistence\DoctrineAuditTrail` |
| `MemberNames` | The display name of a member, or none for an erased one, so another module can record or show who acted | `Membership\Infrastructure\RepositoryMemberNames` |
| `MemberDirectory`, `MemberProfile`, `MemberIdentity` | Whether a member is active, who wants to hear about open rounds, a member's names and phone number (only with consent), and the identity the login works with, by id or e-mail address: id, current roles and whether the member may log in | `Membership\Infrastructure\RepositoryMemberDirectory` |
| `Authorization`, `AccessDenied` | `denyUnlessGranted()` asks the voters of every module and throws `AccessDenied` on a refusal | `Shared\Infrastructure\Security\SymfonyAuthorization` |
| `Notifier`, `Notification` | Sends a member a message by type and parameters; the domain holds no texts | `Shared\Infrastructure\Notification\LoggingNotifier`, which only logs |
| `Role`, `Actor` | The fixed roles `walker`, `coordinator`, `analyst` and `admin`, without inheritance, and a member with their roles, as the policies of every module decide on them | |
| `NotFound` | No item of a kind has a given id; entry points turn it into their own answer, such as HTTP 404 | |
| `RuleViolation`, `StateConflict`, `InvalidValue` | A domain rule refused the action: the state forbids it (`RoundIsFull`) or a value breaks a rule (`StartInPast`). Every domain exception is one of these or `NotFound`; its code is the short class name in snake case | |
| `LocalTime` | Converts an instant to `Europe/Amsterdam`, the clock of dayparts, calendar days and everything a walker reads | |
| `LocalDay` | A calendar day on that clock, from midnight to midnight: 23 or 25 hours on the days the clock changes | |
| `WriteAction`, `ReadAction` | Mark a handler as changing something, with its voter attribute and audit action, or as only reading | |
| `BackedEnumValues` | Gives an enum `values()`, so an input constraint takes its choices from the enum (`Assert\Choice(callback: ...)`) | |
| `EmailAddress` | An address trimmed and in lower case, with accents kept | |
| `LookupKey` | The search and uniqueness key of a name: no case, accents or extra whitespace | |

Time comes from `Psr\Clock\ClockInterface` in handlers and as a `DateTimeImmutable $now` argument in
domain methods. Instants are stored with the Doctrine type `utc_datetime_immutable`
(`Shared\Infrastructure\Doctrine\UtcDateTimeImmutableType`), which writes and reads UTC whatever the
time zone of PHP.

When an operation in `Transaction` throws, Doctrine rolls back and closes the entity manager; the
outermost run resets it, so the next unit of work in the same process gets a working one.

**Rights.** The rules of each module live in a policy in its `Domain`: `RoundPolicy`, `MemberPolicy`,
`CredentialPolicy` and `AuditPolicy`. A voter per subject in `<Module>\Infrastructure\Security`
reads the actor from the token with `TokenActor` and asks the policy, so handler and voters always see
the same actor. Scheduled actions are granted only to `ROLE_SYSTEM`, the identity `RunAs::system()`
sets; an action no voter decides on is refused.

**Errors.** Under `/api/`, `Shared\Infrastructure\Http\ProblemResponder` turns every failure into
`application/problem+json` (RFC 9457): a `StateConflict` is 409, an `InvalidValue` 422, `NotFound` 404,
`AccessDenied` 403, invalid input 422 with a `violations` list per field, and anything unexpected 500. The `type`
is `urn:buurtpreventie:problem:<code>`, the `title` comes from `translations/problems.<locale>.yaml` in the language
of `Accept-Language` (Dutch by default). The exception message never reaches the client: it holds ids and
internals, and goes to the log only. A new domain exception therefore implements `StateConflict` or `InvalidValue`
and gets a title in both files; `RuleViolationCoverageTest` fails otherwise.

A handler that calls the handler of another module, such as `DeactivateMember` calling
`WithdrawMemberFromUpcomingRounds`, `RevokeMemberApiTokens` and `EraseActorName`, runs that handler's transaction inside
its own. Doctrine nests it with a savepoint, so a failure in either undoes both.

## Authentication

Two firewalls guard `/api/`, picked by the request:

| Firewall | Takes | Authenticator | Channel |
| --- | --- | --- | --- |
| `api_token` | Every API request with an `Authorization` header, whatever its scheme | `Identity\Infrastructure\Security\ApiTokenAuthenticator` | `api` |
| `browser` | Every other request, with a session | `Identity\Infrastructure\Security\PasswordAuthenticator` | `web` |

The token firewall is stateless. Its authenticator takes every request, so a broken or unknown header is refused
like a wrong token: an empty 401 with `WWW-Authenticate: Bearer`, and the reason only in the log. It looks the
token up by the SHA-256 hash of the secret on every request, so a revocation or a deactivation counts at once.
`ApiToken::refusalAt()` decides why a known token lets nobody in (revoked, expired, member inactive); the member
comes from `MemberDirectory::identity()`, read afresh, so a changed role counts on the next request.

Under `/api/` a request needs a logged-in member, except `/api/v1/health`, `/api/v1/docs.json` and everything under
`/api/v1/auth/`. Without one, `SessionEntryPoint` answers with the 401 problem `unauthorized` and logs the path.

`POST /api/v1/auth/login` takes `{email, password}` and answers 204 with a session cookie (`bp_session`); the session
gets a new id at login. `PasswordCheck` runs as two steps of the passport, after Symfony's login throttling: it
finds the active member by address through `MemberDirectory::identify()`, then checks the password and renews a
hash made with an older algorithm or cost, such as one of the legacy app. Every refusal is the same 401 problem
`login_failed`; an unknown address or inactive member costs one hash too, so neither answer nor timing shows whether
an address belongs to a member. `LoginRecorder` writes `account.logged_in` with the member as actor and
`account.login_failed` for a known member without an actor, nobody having proved who tried; both carry the IP
address without its last byte (IPv4) or last 80 bits (IPv6). An attempt on an unknown address goes to the log only,
without the address. Five failed attempts on one address from one IP address within 15 minutes, or 30 from one IP
address within an hour, give 429 with `Retry-After`; a successful login clears the first count only.
`POST /api/v1/auth/logout` ends the session and clears its cookie. Login and logout take `POST` only.

`MemberUserProvider` reloads the member of a session on every request: a deactivated or erased member drops out at
once, and a changed role counts from the next request without a new login (`MemberUser` is the same user as long as
the member is).

`SameOriginGuard` runs before both firewalls and refuses a changing request for the browser firewall, the login
included, unless it comes from the same origin: by `Sec-Fetch-Site`, else by `Origin` or `Referer`, else by a
`csrf-token` header of at least 24 characters, which the frontend sets for browsers that send neither, such as
Safari on iOS 15. Symfony's `SameOriginCsrfTokenManager` decides. A refusal is the 403 problem `csrf_check_failed`.
A request with an API token carries no cookie and is not checked.

`TokenScopeGuard` runs right after the firewall and holds a token request to its scopes: `read` for `GET` and
`HEAD`, `write` for every other method, `admin` for everything under `/api/v1/admin/`. A missing scope is a 403
problem `insufficient_scope`. The voters then decide on the roles as for any request, so scope and role must both
allow an action.

## Handler and domain

A handler orchestrates: it builds value objects from the input, asks `Authorization`, runs the change
in `Transaction`, loads and saves through repositories, records the audit entry, calls the handlers
of other modules and sends notifications. Whether a member of another module is active is asked
there too, through `MemberDirectory`. Every rule lives in the domain, so it holds for every handler
that calls it and its test needs no handler:

| Kind | Holds | Examples |
| --- | --- | --- |
| Entity method | A rule about the state of one aggregate | `Round::signUp()` refuses a full or started round, `Round::ensureDeletable()`, `Round::withdraw()` reports a drop below the minimum |
| Value object | A rule about one value | `StaffingRule` (maximum at least the minimum), `Coordinates::fromOptional()` (both or neither), `LookupKey` |
| Guard | A rule that needs a repository to answer | `UniqueCatalogueName`, `UniqueMemberDetails`, `MeetingPointInUseGuard`, `LastAdminGuard`, `FirstAdminGuard` |
| Policy | Who may do what; the voters ask it | `RoundPolicy` (also who sees full names), `MemberPolicy`, `CredentialPolicy`, `AuditPolicy` |

A handler with an `if` that decides something about the domain is a rule in the wrong place: move it
into one of these, with a test in `tests/<Module>/Domain/`.

### Repositories per module

Each module owns its tables. Its repository interfaces sit in its `Domain`, one per aggregate
(`RoundRepository`), with only the methods its use cases need; the Doctrine implementations sit in
`Infrastructure/Persistence/` (`DoctrineRoundRepository`). That way:

- handler tests run on small in-memory repositories in `tests/<Module>/Fake/`;
- every query of a module is in one folder, with an integration test on MariaDB and MySQL, and the
  Opengrep rule `sql-outside-repository` refuses a query anywhere else;
- another module reaches the data only through a use case or an interface in `Shared\Domain`, never
  through a repository.

A repository that guards a unique index (members, meeting points, outcomes) writes at once in `add()`
and `update()`, so a clash with another request surfaces inside the handler as the same error its
check gives. See [ADR 0005](adr/0005-database-approach.md).

### Mapping in the domain

Entities carry their Doctrine mapping as attributes (`#[ORM\Entity]`, `#[ORM\Column]`) in `Domain`, on
purpose: rule 1 allows `Doctrine\ORM\Mapping` and nothing else of Doctrine ORM. The type, length and
collation of a column stand next to the field they store, so a change to a field and to its column
is one change in one file. XML mapping in `Infrastructure` would keep the attributes out of the domain
at the cost of a second file per entity that has to follow every field by hand. The attributes do
nothing without Doctrine, so domain tests stay in memory. `config/packages/doctrine.yaml` maps
`src/<Module>/Domain` per module, subfolders included.

### Subfolders per aggregate

A `Domain` stays flat until it holds several aggregates; then each gets a subfolder, and the tests
follow the same layout. `Patrol/Domain` has `Round/` (rounds, participations, reports, comments,
staffing, reminders, buddy calls and `RoundPolicy`) and `Catalogue/` (meeting points and outcomes).
The other modules are flat. The Doctrine mapping and the PHPat rules read the subfolders: PHPat
compares on the start of the namespace.

### The module boundary in the database

There is no foreign key between the tables of two modules. A reference to a member from another
module is the member's UUID with an index of its own (`Participation.memberId`,
`RoundReport.authorId`, `AuditEntry.actorId`, ...), never a Doctrine relation, which would let one
module load the entities of another. A member is never deleted: erasing anonymises it, so every
reference keeps pointing at an existing row. Within a module, relations and foreign keys stay. See
[ADR 0005](adr/0005-database-approach.md).

## Sample data

The module `SampleData` holds `app:fixtures:load <set>`, which exists only in `dev` and `test`
(`#[When]`). It is a module and not part of `Shared`, because it calls the use cases of every module
and `Shared` depends on no module ([rule 4](#rule-4-modules-and-shared)). It has no use cases and no
domain of its own; everything sits in `Infrastructure`.

The command empties the database with Doctrine's `ORMPurger` and builds the set through the handlers,
each step run by whoever would take it: the system adds the first admin (`RunAs::system()`, channel
`console`), the admin adds members, members sign up and report (`RunAs::member()`). `Timeline` sets
the clock for each step, so a round in the past is created, joined and reported as it would have been
then, and its audit entries carry that moment; a step after the start of the load is refused. A
change to a rule or the model therefore breaks the sample data at once, instead of leaving rows that
no use case could have written.

| Set | Contents |
| --- | --- |
| `demo` | Twenty members with every role, rounds from three months back to six weeks ahead, mostly in the evening, more than a quarter without a buddy, a few full ones, reports with now and then an incident, a deactivated member and an erased member in the history |
| `test` | An admin, a meeting point and the standard outcomes; each test builds the rest |
| `edge` | One of each hard case: rounds around the last clock change, a last walker who withdraws, an erased member in the history, a member without consent, rounds just under the minimum, at the maximum and with one spot left, two members who share their full name |

A fixed seed gives the same people and pattern on every load; only the dates follow today. Names are
made up and international, with letters the lookup key keeps (`ı`, `ł`); a display name is the first
name, with an initial only where two members share it. Addresses are on `example.org`.

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
frontend in phase 5.
