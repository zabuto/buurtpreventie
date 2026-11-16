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
| `Identity` | Login, login codes, invitations, passwords, API tokens |
| `Audit` | Recording and showing the audit log |
| `Settings` | What an association sets for itself: name, colours, logo and icon, contact, texts, walkers of a new round, reminder, language and access with API tokens |
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
    #[OA\Response(
        response: 200,
        description: 'The app answers.',
        content: new OA\JsonContent(ref: new Model(type: CheckHealthOutput::class)),
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new CheckHealth()));
    }
}
```

A use case with input receives it through `#[MapRequestPayload]`; the generator sets that up. Nelmio cannot read the
output from a `JsonResponse`, so `#[OA\Response]` names the successful answer: the status with the `…Output` class
as its schema, or a 204 without a body.

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
| `Transaction` | Runs an operation as one unit: a change and its audit entry are stored together or not at all. `afterCommit()` holds a callback until the outermost run has committed and drops it when that run fails; `beforeCommit()` runs one as the last step inside the outermost run, for a lock that must come last | `Shared\Infrastructure\Doctrine\DoctrineTransaction` |
| `ExecutionContext`, `Channel` | The member performing the action with their roles (an `Actor`), or none for the system, and the channel (`web`, `api`, `mcp`, `cron`, `console`) | `Shared\Infrastructure\Security\TokenExecutionContext`, which reads Symfony's token storage; `RunAs` fills it for console commands, fixtures and the scheduler, an authenticator for HTTP and MCP. A member run through `RunAs` counts as a recent login: whoever runs a console command proved who they are by reaching the server |
| `AuditTrail`, `AuditedAction` | Records an action; time, actor and channel come from the clock and the execution context | `Audit\Infrastructure\Persistence\DoctrineAuditTrail` |
| `MemberNames` | The display name of a member, or none for an erased one, so another module can record or show who acted | `Membership\Infrastructure\RepositoryMemberNames` |
| `MemberDirectory`, `MemberProfile`, `MemberIdentity` | Whether a member is active, who wants to hear about open rounds, a member's names and phone number (only with consent), the address mail goes to (none for an inactive member), and the identity the login works with, by id or e-mail address: id, current roles and whether the member may log in | `Membership\Infrastructure\RepositoryMemberDirectory` |
| `Authorization`, `AccessDenied` | `denyUnlessGranted()` asks the voters of every module and throws `AccessDenied` on a refusal | `Shared\Infrastructure\Security\SymfonyAuthorization` |
| `Notifier`, `Notification` | Sends a member a message by type and parameters; the domain holds no texts | `Shared\Infrastructure\Notification\AfterCommitNotifier`, which hands each message on after the commit and logs a delivery that fails rather than throwing, as the change is stored by then; then `MailNotifier`, which mails every type in HTML and text through the `MailComposer` of its type; neither logs the parameters, which can hold a code or secret |
| `RecentAuthentication`, `ReauthenticationRequired` | Whether the member acting proved who they are in the last 15 minutes (`@upgrade-8.4`, sudo mode), and the refusal of a sensitive action without that proof | `Shared\Infrastructure\Security\TokenRecentAuthentication`, which reads the token attribute `authenticated_at` |
| `Role`, `Actor` | The fixed roles `walker`, `coordinator`, `analyst` and `admin`, without inheritance, and a member with their roles, as the policies of every module decide on them | |
| `NotFound` | No item of a kind has a given id; entry points turn it into their own answer, such as HTTP 404 | |
| `RuleViolation`, `StateConflict`, `InvalidValue` | A domain rule refused the action: the state forbids it (`RoundIsFull`) or a value breaks a rule (`StartInPast`). Every domain exception is one of these or `NotFound`; its code is the short class name in snake case | |
| `RuleViolationDetails` | A refusal that tells the client what it needs to act on it, such as the item that already holds a name; `ProblemResponder` adds its `details()` to the problem answer | |
| `LocalTime` | Converts an instant to `Europe/Amsterdam`, the clock of dayparts, calendar days and everything a walker reads | |
| `LocalDay` | A calendar day on that clock, from midnight to midnight: 23 or 25 hours on the days the clock changes | |
| `WriteAction`, `ReadAction` | Mark a handler as changing something, with its voter attribute and audit action, or as only reading | |
| `Unchanged` | The default of an input field that a JSON Merge Patch leaves out, so a handler keeps the current value where null clears it; `fieldsSetIn()` gives the fields to validate | |
| `BackedEnumValues` | Gives an enum `values()`, so an input constraint takes its choices from the enum (`Assert\Choice(callback: ...)`) | |
| `Branding` | Name, sender name, primary colour, language, mail logo and contact address of the association, read afresh for each mail | `Settings\Infrastructure\Branding\SettingsBranding` |
| `AppUrl` | The base URL of the app from `DEFAULT_URI`, on a subdomain or under a path; `to('app/#/…')` makes every absolute link that leaves the app outside a request, such as in a mail, so the path of the installation is in each | |
| `EmailAddress` | An address trimmed and in lower case, with accents kept | |
| `PhoneNumber` | A phone number as a member types it, with whitespace tidied: digits, spaces, brackets and hyphens, an optional leading plus, at least six digits. Shared, as members and the contact of the association both have one | |
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
`AccessDenied` 403, `ReauthenticationRequired` 403 with the code `reauthentication_required`, invalid input 422 with a `violations` list per field, and anything unexpected 500. The `type`
is `urn:buurtpreventie:problem:<code>`, the `title` comes from `translations/problems.<locale>.yaml` in the language
of `Accept-Language` (Dutch by default). The exception message never reaches the client: it holds ids and
internals, and goes to the log only. A new domain exception therefore implements `StateConflict` or `InvalidValue`
and gets a title in both files; `RuleViolationCoverageTest` fails otherwise.

A handler that calls the handler of another module, such as `DeactivateMember` calling
`WithdrawMemberFromUpcomingRounds`, `RevokeMemberApiTokens` and `EraseActorName`, runs that handler's transaction inside
its own. Doctrine nests it with a savepoint, so a failure in either undoes both.

## Authentication

The reasons for this design are in [ADR 0006](adr/0006-authentication.md). Two firewalls guard `/api/`, picked by
the request:

| Firewall | Takes | Authenticator | Channel |
| --- | --- | --- | --- |
| `api_token` | Every API request with an `Authorization` header, whatever its scheme | `Identity\Infrastructure\Security\ApiTokenAuthenticator` | `api` |
| `browser` | Every other request, with a session | `PasswordAuthenticator`, `LoginCodeAuthenticator`, `InvitationAuthenticator`, `ConfirmPasswordAuthenticator` and Symfony's `remember_me`, all in `Identity\Infrastructure\Security` except the last | `web` |

The token firewall is stateless. Its authenticator takes every request, so a broken or unknown header is refused
like a wrong token: an empty 401 with `WWW-Authenticate: Bearer`, and the reason only in the log. It looks the
token up by the SHA-256 hash of the secret on every request, so a revocation or a deactivation counts at once.
Before that it reads the setting `apiAccess` through `GetApiTokenAccess`, one query: switched off, it refuses every
token (`tokens_disabled`), and `CreateApiToken` refuses a new one with the 409 problem `api_tokens_disabled`, while
listing and revoking keep working, since revoking is a step after a leak. `ApiToken::refusalAt()` decides why a known token lets nobody in (revoked, expired, member inactive); the member
comes from `MemberDirectory::identity()`, read afresh, so a changed role counts on the next request.

Under `/api/` a request needs a logged-in member, except `/api/v1/health`, `/api/v1/docs.json`, `/api/v1/config`,
`/api/v1/manifest.webmanifest`, and everything under `/api/v1/auth/` and `/api/v1/media/brand/`. Without one, `SessionEntryPoint` answers with the 401 problem `unauthorized` and logs the path.

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

**Login code.** `POST /api/v1/auth/login-code` (`RequestLoginCode`) takes `{email}` and always answers 202 with a
`challengeId`, which the browser keeps. An active member gets a six-digit code by mail and loses every earlier open
challenge; any other address gets a challenge that no code opens, so neither answer nor attempts tell whether the
address belongs to a member. `LoginCodeRequestThrottle` allows 3 requests per address and 10 per IP address in an
hour, then 429 with `Retry-After`. `POST /api/v1/auth/login-code/verify` takes `{challengeId, code}`; spaces and
hyphens in the code do not count. `LoginCodeCheck` stores the attempt before it answers: a wrong code is the 401
problem `login_code_wrong` with `attemptsLeft`, a challenge that is unknown, used, older than 60 minutes, past 5
attempts or of a member deactivated since is `login_code_ended`. Only an HMAC of challenge id and code is stored.

**Invitation.** `POST /api/v1/auth/invitation` takes `{secret}`, which the frontend reads from behind the `#` of the
link in the mail, so it reaches no access log. `InvitationCheck` accepts an invitation once, within 48 hours, for an
active member; anything else is the 401 problem `invitation_ended`. `IssueInvitation` sends one and ends the earlier
ones; only an admin inviting a member who never logged in gets the secret back.

**Every browser login** answers 204, records `account.logged_in` or `account.login_failed` with the method
(`password`, `code`, `invitation`), and counts in Symfony's login throttling. `"rememberMe": true` in the body sets
the cookie `bp_remember` for 90 days, signed with `MemberUser::$credentialsVersion`, the moment of the member's last
new password. A new password ends every remembered device and every session, because `MemberUser` is the same user
only while that version is; the session that set it stays (`CurrentSession`), and so does its cookie. A login without
an e-mail address is the 422 problem `invalid_input` with a field error. A login stamps the token with `authenticated_at`, which makes
it recent for 15 minutes (`RecentAuthentication`); a session from the cookie and an API token never are.
`POST /api/v1/auth/confirm` takes `{password}` in an existing session and makes it recent; a refusal leaves the
session as it was. `BrowserTokenChannel` puts the channel `web` on every token of the browser firewall. A request
without a token, such as a request for a code, also runs on `web`, without an actor.

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
allow an action. `TokenManagementGuard` refuses every token request under `/api/v1/me/tokens` with the 403 problem
`browser_only`, whatever its scopes: a leaked token can neither list, create nor revoke tokens.

**Own account.** Everything under `/api/v1/me` is about the member logged in, and the controllers there pass no member
id, so one in the body counts for nothing. `GET /me` (`GetOwnProfile`) shows the whole profile. `PATCH /me`
(`ChangeOwnContactDetails`) takes phone number, address, consent and notifications about open rounds; null removes a
phone number or address, empty text is refused. Consent and notifications go through `ChangeContactConsent` and
`ChangeOpenRoundNotifications`, so they are the same audit actions wherever they change. Name, display name and
e-mail address stay with the admin, so a hijacked session cannot move the address a login code goes to.
`PUT /me/password` (`SetPassword`) and `POST /me/tokens` (`CreateApiToken`) need a recent login, otherwise the 403
problem `reauthentication_required`; a login with a code counts, which is how a member replaces a forgotten password.
A new token expires within 365 days and its secret is only in the answer to `POST`; `GET /me/tokens`
(`ListApiTokens`) lists the tokens not revoked, and `DELETE /me/tokens/{id}` (`RevokeApiToken`) revokes one.
`GET /me/activity` (`ListOwnActivity`) gives the member's own audit entries, 50 a page; of an entry about another
member, such as a role an admin gave, it keeps the action but not who it was about or what changed.
`GET /me/export` (`ExportOwnData`) gathers profile, rounds, reports and comments (`ExportOwnPatrolData`), tokens and
every activity entry through the use cases of each module, and of another member it holds a buddy's display name
only.

**Naming.** Every JSON key, in a body, an answer or a problem, and every query parameter is camelCase
(`walkerCount`, `existingId`, `allEntries`). Codes and enum values are snake_case (`round_is_full`, `seeking_buddy`,
`to_report`): they are values a client compares, not keys. `OpenApiNamingTest` checks every property and query
parameter of the OpenAPI description, and `OutputKeyNamingTest` the keys of the array shapes in the `…Output`
docblocks, which Nelmio leaves out of the description.

**Changes.** Every `PATCH` is a JSON Merge Patch (RFC 7396): a field left out stays, null clears a field that may be
empty, a value replaces it. Null on a field that may not be empty, such as a name or the start of a round, is 422, and
so is empty text for a phone number or address. `?string` cannot tell a field left out from null, so each field of
the input is typed as, say, `string|null|Unchanged` with `Shared\Domain\Unchanged::Field` as its default: the
serializer leaves a field it does not find at that default. The handler applies only the fields that are not
`Unchanged`, and the audit entry names only what changed. A constraint such as `Length` cannot read `Unchanged`, so
each field's constraints sit in a validation group named after the field, and the input is a
`GroupSequenceProviderInterface` that validates only the groups of the fields sent (`Unchanged::fieldsSetIn()`).
`MergePatchFieldsProcessor` describes such a field in the OpenAPI description by what a client sends, without
`Unchanged`. Latitude and longitude of a meeting point go as a pair: both left out, both null or both a number,
otherwise the 422 problem `invalid_coordinates`.

**Rounds.** `GET /rounds?from=&to=&status=` (`ListRounds`) lists the rounds that start in a period of at most 93 days,
otherwise the 422 problem `invalid_period`; a `+` in an offset reads as a space in a query string, so a client sends
`%2B` or `Z`. `GET /rounds/{id}` (`GetRound`) shows one round with its walkers, report and comments; `GET
/staffing-gaps` (`FindStaffingGaps`) the rounds that have not started and seek a buddy; `GET
/me/rounds?view=&from=&to=&order=&limit=` (`ListMyRounds`) the rounds of the member logged in, through
`RoundRepository::forWalker()` with a `WalkerRoundQuery`: `upcoming` (the default) the ones that have not started,
earliest first; `to_report` the walked ones without a report, latest first, where walked is what `RoundStatus` derives
(started, staffed, not cancelled); `all` every round, `order=asc` (the default) or `desc`. A period (`from` and `to`
together, at most 93 days by `ListingPeriod`) narrows any view and `all` needs one, otherwise the 422 problem
`invalid_period`; `limit` is 50 by default and at most 200. Every round in these answers has `status`, `walkerCount`, `minimumWalkers`, `maximumWalkers`,
`openSpots` and `daypart`. `POST /rounds` (`CreateRound`) plans a round and answers 201 with its id; `PATCH
/rounds/{id}` (`ChangeRound`) changes start, meeting point and start note; `DELETE
/rounds/{id}` (`DeleteRound`) deletes a round that has not started; `POST /rounds/{id}/cancellation` (`CancelRound`)
records that a started round was not walked, with an optional reason. A round id comes from the path only: the
controllers fill the one in the input with `default_constructor_arguments` and replace it, so one in the body
counts for nothing. `PUT` and `DELETE /rounds/{id}/walkers/me` sign the member up and withdraw them, and
`/walkers/{memberId}` does so on behalf of another member, which only a coordinator may; both answer 204 however
often they are repeated, until the last walker leaves and the round goes, after which the round is not found.

**After the round.** `POST /rounds/{id}/reports` (`ReportRoundResult`) takes the one report of a walked round, a
second gets the 409 problem `round_already_reported`; `DELETE /reports/{id}` (`DeleteRoundReport`) removes it. `POST
/rounds/{id}/comments` (`AddRoundComment`) and `DELETE /comments/{id}` (`DeleteRoundComment`) do the same for
comments. The note of a report and the comments may be about a suspect, so `ReportReadingPolicy::maySeeNotes()` keeps
them for the walkers of the round, analysts, coordinators and the system: `GET /rounds/{id}` gives anyone else the
outcome with a null note and no comments. `GET /reports?from=&to=&outcome=` (`ListRoundReports`, voter attribute
`report.list`) lists the reports of the rounds that start in a period of at most 93 days (`ListingPeriod`, shared with
`ListRounds`), with notes, for analysts and coordinators. `GET /rounds/{id}/history` (`GetRoundHistory`, Audit,
`audit.view_round_history`) gives a coordinator the audit entries about the round, oldest first, through
`AuditEntryRepository::bySubject()`; each names its actor as `member`, `removed_member` (erased, without a name) or
`system`, which the frontend words. It reads the log only, so a deleted round keeps its history; a round the log does
not know is not found.

**Members.** `GET /members` (`GetMemberSummaries`) lists the active members by display name for every member; a
coordinator or admin sees the full name as well. Phone number and address show only for a member who consents to share
them, to everyone alike, and the e-mail address never does. The shape stays the same for every reader: `name`,
`phone` and `address` are always there, null for what the reader may not see or what the member left empty, and the
walkers in `GET /rounds/{id}` always carry `name` and `phone` the same way. The OpenAPI description names these fields
with `#[OA\Property]` on the output, because Nelmio does not read an array shape from a docblock. Everything else about a member is under `/admin/members`,
for the admin (voter attribute `member.view` and the other `member.*` attributes): `GET` lists every member that is
not erased, deactivated ones included, and `GET /admin/members/{id}` (`GetMember`) shows one, both with e-mail
address, contact details, roles and choices (`ListMembers`); `POST` (`AddMember`) adds a member, `PATCH
/admin/members/{id}` (`ChangeMember`) changes name, display name, e-mail address, contact details and certificate,
`PUT /admin/members/{id}/roles` (`ChangeMemberRoles`) sets the roles, `POST` and `DELETE
/admin/members/{id}/deactivation` (`DeactivateMember`, `ReactivateMember`) deactivate and reactivate, `DELETE
/admin/members/{id}` (`EraseMember`) erases. Changing roles and erasing need a recent login, otherwise the 403 problem
`reauthentication_required`. `POST /admin/members/{id}/invitation` (`InviteMember`) sends an invitation, also for a
coordinator; the answer holds the secret only for an admin inviting a member who never logged in, and the frontend
makes the link of it.

**Catalogues.** `GET /meeting-points` (`ListMeetingPoints`) and `GET /outcomes` (`ListOutcomes`) give every member the
items to choose from, by name, without the archived ones (voter attribute `catalogue.list`); under
`/admin/meeting-points` and `/admin/outcomes` the same use cases list the archived ones too, for the admin
(`catalogue.manage`). There the admin adds an item with `POST` (`CreateMeetingPoint`, `CreateOutcome`), changes it
with `PATCH /{id}` (`ChangeMeetingPoint`: name and coordinates; `ChangeOutcome`: the name only), archives it with
`POST /{id}/archive` and restores it with `DELETE /{id}/archive`. A name another item holds, archived or not, is the
409 problem `meeting_point_name_taken` or `outcome_name_taken` with `existingId` and `existingArchived`, so the admin
can restore that item instead of adding a look-alike. A domain error adds such members by implementing
`Shared\Domain\RuleViolationDetails`, which `ProblemResponder` reads.

**Audit log.** `GET /admin/audit` (`ListAuditEntries`, voter attribute `audit.view`, for the admin) gives the whole
log newest first, 50 entries a page with `nextPage`, narrowed by `member` (what the member did and what was done to
the member), `action`, `from` and `to` (`AuditFilter`, through `AuditEntryRepository::matching()`). Each entry names
its actor as `member`, `removed_member` or `system`. The Accept header picks the format: `application/json`, `*/*`
or none gives the page as JSON, `text/csv` every matching entry at once as a file, and anything else the 406 problem
`not_acceptable`; the query string cannot ask for a format (`allEntries` is `#[Ignore]`d). The file is streamed, read
in batches of 500, with a fixed column order, the changes as JSON and every cell that starts with `=`, `+`, `-`, `@`,
a tab or a carriage return prefixed with an apostrophe, so a spreadsheet never runs it as a formula. The file ends
before the second of the request (`to` is the start of that second, or stays when it is earlier): the batches are read
by offset, newest first, so an entry recorded while the file streams would otherwise shift them and appear twice, and
the log stores whole seconds. It holds at most
10,000 entries: more is the 422 problem `too_many_entries` with their `count` (`AuditEntryRepository::countMatching()`),
never a file cut short that would look complete. IP addresses
are shortened when the entry is recorded, so the log never holds a full one.

**Settings.** `GET` and `PATCH /admin/settings` (`GetSettings`, `ChangeSettings`) read and change every setting but
the images, `PUT` and `DELETE /admin/settings/images/{logo|icon}` (`ChangeBrandImage`, `RemoveBrandImage`) the
images, all for the admin (`settings.view`, `settings.change`). `BrandImages` stores every size of an upload before
the setting names its version, and removes the previous files through `Transaction::afterCommit()`, so a reader
always finds the files the setting names. `GET /media/brand/{file}` (`GetBrandImage`) is public: `BrandImageFile`
reads kind, size and version from the name, and anything outside that pattern, or a version no setting names, is
not found before a path is made; while no icon is uploaded, the version `BrandImageKind::defaultVersion()` names the
neutral icon `DefaultBrandIcon` draws. The answer keeps `Cache-Control: public` for a logged-in member too, through
the header Symfony reads to leave the cache headers of a session alone.

`GET /config` (`GetPublicConfig`) gives the frontend name, colours, logo and icons, texts in the language of the
association and the staffing defaults, to anyone; `contact` and `group` keep their keys with null unless a member is
logged in, by session or token: the contact is personal data, and the group invitation lets anyone in to see the phone
numbers of its members. `GET /manifest.webmanifest`
(`GetWebManifest`) is the web app manifest, in the member names of its standard. Both give every link as a path from
`AppUrl::pathTo()`, so the start of the app and every image follow the path of the base URL.

**Mail.** Every type of message has a `MailComposer` in `<Module>\Infrastructure\Mail` of the module that sends it,
as only that module can read what the ids in the message point at: `LoginCodeMail` and `InvitationMail` in Identity,
`ReminderMail`, `BuddyNeededMail`, `WalkerWithdrawnMail` and `RoundCancelledMail` in Patrol. `MailComposers` finds it
by type; a type without one is a `LogicException`, and `MailNotifierTest` holds every type in `src/` against the
composers. `MailRenderer` renders `templates/mail/<name>.html.twig` and `.txt.twig` on one layout, with styles inline
and the texts of `translations/mail+intl-icu.<locale>.yaml` in the language of the association; `LocalDateText` writes
a moment out on the local clock. `MailNotifier` sends from `MAILER_FROM` under the sender name, replies go to the
contact address, and the logo travels inside the mail (`cid:logo`); without it the layout shows the name. A mail about
a round names date, time, daypart, meeting point and how many walk, never who, nor a phone number, address or note
(`RoundFacts`); a link leads to the round in the app. A deleted round is gone when its mail is written, so
`round.cancelled` carries the start.

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

The first admin of every set is `beheer@example.org` with the password in `SamplePassword`, so one login fits any
set; in `demo` one member per role and three walkers in different situations have it too. Each sets it through
`SetPassword`, as a member would. The others have none: every hash costs the real hasher half a second.

In `demo` the walker who uses the API gets a token that reads and writes for 90 days, through `CreateApiToken`. A set
returns what the developer needs to know afterwards from `FixtureSet::load()`, and the command shows it: here the
secret of that token, which the app keeps only as a hash, for the curl scenario of
[getting-started.md](getting-started.md#sign-up-with-curl).

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
  `HttpKernel\Attribute`, `Routing\Attribute` and `Routing\Requirement` (such as `Requirement::UUID` for an id in
  the path), and on `OpenApi\Attributes` and `Nelmio\ApiDocBundle\Attribute` to document the endpoint; the rest of
  the OpenAPI library stays out. The one HTTP exception it may throw is `NotAcceptableHttpException`, for a client
  whose Accept header allows no format the endpoint gives: that choice is HTTP alone, and `ProblemResponder` turns it
  into the 406 problem `not_acceptable`.
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

The spec on `/api/v1/docs.json` comes from NelmioApiDocBundle: paths, path parameters, request bodies
(`#[MapRequestPayload]`) and query parameters (`#[MapQueryString]`) from the controllers. The routes the firewall
answers (`/auth/login`, `/auth/login-code/verify`, `/auth/invitation`, `/auth/confirm`, `/auth/logout`) and the
`Problem` schema are written out in `config/packages/nelmio_api_doc.yaml`. Every controller names its successful answer
with `#[OA\Response]`: 200, 201 or 202 with the `…Output` class as a Nelmio `Model`, or 204 without a body.
`Shared\Infrastructure\Http\ProblemResponsesProcessor` gives every operation its problem answers: 401 and 403 off the
public paths, 404 with an id in the path, 409 for a change, 422 with a body or query parameters. It is a swagger-php
processor rather than a describer, because Nelmio adds bodies and query parameters in processors of its own, after
every describer. An answer a controller documents itself, such as the 404 of a repeated withdrawal, stays.

**Why.** `OpenApiCoverageTest` holds the spec to the router and to the Postman collection, so neither drifts from the
API.
