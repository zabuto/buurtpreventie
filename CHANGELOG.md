# Changelog

All notable changes per version. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Symfony 7.4 LTS base on PHP 8.4.27 or later, with the modules `Patrol`, `Membership`, `Identity`,
  `Audit`, `Settings` and `Maintenance`.
- Health check `GET /api/v1/health`.
- Generator `symfony console app:make:use-case <Module> <Name>`.
- Architecture rules (PHPat) and a folder layout test, explained in `docs/architecture.md`.
- Checks through `composer check`: PHPStan level max, Mago (format, lint, analyze), Rector, PHPUnit, `composer audit`
  and deprecations.
- GitHub Actions for CI, a weekly audit and a weekly look-ahead on Symfony 8 and PHP 8.5;
  Dependabot with automatic merging of patch and minor updates once CI is green (minor updates of
  packages below 1.0 excepted). Dependabot skips major Symfony updates, so the app stays on 7.4 LTS.
- Development database: MariaDB 10.11 in `compose.yaml` on a free port, handed to the app by the
  Symfony CLI.
- Test suite in unit, integration and functional groups; `composer test:unit` runs the unit tests.
- `SECURITY.md` and documentation in `docs/`, including the code style and writing guidelines.
- Cooldowns against hijacked releases: Dependabot waits 7 days for a patch or minor and 14 for a
  major (actions 7), Composer withholds releases younger than 24 hours. Security updates skip the
  Dependabot cooldown.
- Security scan in CI: Opengrep, verified with Cosign, with rules and rule tests in
  `.opengrep/rules/`. A new Opengrep major is reported weekly and after `composer update`.
- ADRs on Symfony 7.4 LTS and the PHP minimum, the licence, and supply-chain protection.
- Doctrine ORM and Migrations on MariaDB and MySQL; the PHP extensions `intl` and `mbstring` are
  required.
- Shared building blocks for handlers: `Transaction`, `ExecutionContext`, `AuditTrail`,
  `Authorization` and `Notifier`, the value objects `EmailAddress` and `LookupKey`, the Doctrine
  type `utc_datetime_immutable`, and authorization through Symfony's voters.
- Architecture rules: the domain may use Symfony Uid; input and handlers may not use Doctrine.
- Database tests in the group `database`, on a test database of the run's own, built from the
  migrations and dropped when the run ends, so two runs at once never interfere; they skip without a
  database, unless `REQUIRE_DATABASE=1`.
- Patrol domain: rounds with a staffing rule fixed at creation, derived status, open spots and
  daypart; signing up (idempotent, refused when full or started), withdrawing, round reports, outcomes,
  meeting points with coordinates, comments, reminders once per walker per day, and who may change a
  round or delete a report or comment.
- Migrations and Doctrine repositories for every module, each query with an integration test on MariaDB and MySQL;
  no foreign key between modules, and an index on every member reference.
- Signing up and withdrawing lock the round and count its walkers with a locking read: two walkers on the last spot
  leave one signed up and the other refused, and a sign-up never vanishes with a deleted round. A test races two
  real connections.
- A clash on a unique e-mail address, display name or catalogue name between two requests gives the same error as
  the check before it.
- Membership: members with one e-mail address for mail and login, a unique display name and an
  erasure that keeps the member; adding, changing, roles, consent, (de)activating, erasing and a
  member list that shows full names only to coordinators and admins.
- Deactivating or erasing a member withdraws it from every round that has not started and revokes its
  tokens, in one transaction. A round left without walkers is deleted; walkers of a round that drops
  below its minimum get a notification, which the log records.
- Audit log: every write action stores time, actor with their display name at that moment or the
  system, channel and changed fields, never a field holding a password, token or other secret.
  Erasing a member wipes its name from the log and keeps its actions.
- Who acts comes from Symfony's token storage, for handlers and voters alike: `RunAs` sets a member or
  the system identity (`ROLE_SYSTEM`) with the channel it acts through, for entry points without HTTP authentication. A
  failed transaction resets the entity manager, so the next use case in the same process works.
- `MemberDirectory` lets other modules ask whether a member is active, who wants to hear about open
  rounds, and a member's names and phone number (only with consent).
- The last active admin keeps the admin role and cannot be deactivated or erased.
- Patrol use cases: create (also on behalf of an active member), change and delete rounds; sign up and
  withdraw, also on behalf; report a walked round and delete the report; comment and delete a comment;
  manage meeting points and outcomes; list rounds, show a round and find staffing gaps; send reminders
  and buddy calls as the system. A round starts after it is planned and cannot change once started.
- A started round that was not walked, such as after a no-show, can be cancelled with a reason by a
  walker of the round or a coordinator; its status is `cancelled` and it takes no report.
- Rights: a voter per subject on the policy of its module (rounds, participations, reports, comments,
  catalogues, members, API tokens, accounts, audit log), an authorization matrix over every attribute,
  and a guard test that holds every handler to its voter, matrix rows and audit test.
- A meeting point where an upcoming round starts cannot be archived; archived meeting points and outcomes
  are not offered for a new choice.
- Identity: accounts with a password that accepts the hashes of the legacy app, and API tokens stored as a hash with
  scopes, expiry and revocation; a token secret is always generated, and any other value is refused. Settings: the default minimum and maximum walkers of a new round.
- Responses do not name PHP or its version: the app and `public/.htaccess` remove `X-Powered-By`.
- API responses carry a Content-Security-Policy that allows nothing, `nosniff`, `Referrer-Policy: no-referrer`
  and `Cross-Origin-Opener-Policy: same-origin`, and over HTTPS `Strict-Transport-Security` once an installation
  opts in with `HSTS_MAX_AGE`.
- `AddFirstAdmin`: the system adds the first admin, only while no active admin exists.
- Login in the browser: `POST /api/v1/auth/login` with e-mail address and password gives a session, `POST
  /api/v1/auth/logout` ends it. A failed attempt gets the same answer whatever went wrong; repeated failures are
  throttled with 429. An outdated password hash is renewed at login. A deactivated member is logged out on the next
  request, a changed role counts at once. Logins and failed attempts on a known member are in the audit log with a
  shortened IP address. Changing requests from the browser must come from the same origin.
- Mail through Symfony Mailer (`MAILER_DSN`, sender `MAILER_FROM`); in development Mailpit in `compose.yaml` catches
  every mail. Login codes and invitations go out as plain Dutch text; the log never holds message parameters.
- Login with a code by mail: `POST /api/v1/auth/login-code` answers the same for any address and mails an active
  member a six-digit code for 60 minutes, at most 3 requests per address and 10 per IP address in an hour;
  `POST /api/v1/auth/login-code/verify` logs in with the code and the id of the request, at most 5 attempts. Without
  `APP_SECRET` the app refuses to hash a login code.
- Invitations: a link that logs a member in once within 48 hours (`POST /api/v1/auth/invitation`), sent by a
  coordinator or admin (`InviteMember`).
- A new password has at least 15 characters of any kind, and at most 4,096 bytes.
- Stay logged in for 90 days with `"rememberMe": true`. A new password ends every other session and every
  remembered device. A login without an e-mail address gets a field error instead of a failed login.
- `POST /api/v1/auth/confirm` with the password makes a session recent; a new password needs a login or confirmation
  of the last 15 minutes, otherwise 403 `reauthentication_required`. The audit entry of a login names its method.
- A member chooses to hear about rounds that seek a buddy (`ChangeOpenRoundNotifications`), only for themselves.
- A round list covers at most 93 days.
- Sample data: `app:fixtures:load demo|test|edge` in `dev` and `test`, built through the use cases with a fixed seed
  and dates from today. The first admin of every set, `beheer@example.org`, and in `demo` one member per role and
  three walkers log in with one made-up password, which the command shows.
- Messages leave only once the change they are about is committed, never for a change that is rolled back; a
  delivery that fails after the commit is logged and does not undo the change.
- Settings of the association: `GET` and `PATCH /api/v1/admin/settings` for the admin, with name, short name, four
  brand colours checked for contrast in light and dark mode, contact of the coordinator, a link to a group in
  WhatsApp or Signal, sender name, welcome and emergency texts in Dutch and English with standard texts, privacy and
  accessibility pages, walkers of new rounds, days ahead of the reminder, language, and access with API tokens.
- Logo and app icon: `PUT` and `DELETE /api/v1/admin/settings/images/logo` and `/icon` for the admin take a PNG or
  JPEG, checked by its content, and store every size the app uses as a fresh PNG without metadata in `UPLOAD_DIR`;
  `GET /api/v1/media/brand/{file}` serves them to anyone, cached for a year.
- `GET /api/v1/config` for the frontend, public: name, colours, logo and icons, texts in the language of the
  association and the staffing defaults; the contact of the coordinator and the group link only for a member logged in. `GET /api/v1/manifest.webmanifest` lets a phone install the app under the name, colour and icon of the
  association, started under the path of `DEFAULT_URI`. Without an uploaded icon the app draws a neutral one.
- Every message is a mail in HTML and text, in Dutch or English as the association sets it, with its name, colour
  and logo: login code, invitation, reminder, a round that seeks a buddy, a walker who withdrew and a cancelled
  round. A mail names no member, phone number, address or note; replies go to the contact address of the
  association. In dark mode a mail turns dark, with the dark primary colour of the association. Mails use only CSS
  that mail programs keep, spacing as padding on table cells and no rounded corners, so they look alike in Outlook,
  webmail and mail apps.
- The reminder goes out as many days ahead as the setting `reminderDaysAhead` says, counted on the local clock.
- With the setting `apiAccess` off, every API token is refused from its next request and no new token can be made
  (409 `api_tokens_disabled`); members still list and revoke their tokens.
- A hash chain over the audit log (HMAC-SHA-256 with the new required key `AUDIT_SECRET`) makes a change in the
  database visible: each entry takes the next position as the last step of its transaction, under a lock on the head.
  `symfony console app:audit:verify` reports the chain intact through an entry, or the first entry that was changed,
  removed or inserted, and exits with 1 then. Erasing a member also removes the changed fields of every entry about
  that member; the chain holds. Entries from before the chain get a position and stay unchecked.
- The `demo` sample set is the association "Buurtpreventie Voorbeeldwijk", with colours, a drawn logo and icon, and
  the coordinator as contact, all set through the settings and uploads; `edge` has a name at the limit of 100
  characters and a short name of two letters. CI checks the audit chain after loading each set.
- Opengrep rules `client-file-metadata`, `twig-autoescape-disabled` and `mailer-outside-notifier`, each with a rule
  test.
- ADR 0007 on the hash chain of the audit log, with what it does not cover; `docs/operations.md` on checking the chain
  and keeping `AUDIT_SECRET`.
- Every link in a mail starts at `DEFAULT_URI`, also when the app runs under a path such as
  `https://example.org/buurtpreventie`, with or without a trailing slash.
- Dependabot also updates the Mailpit image in `compose.yaml`, after the same 7-day cooldown.
- Hurl files in `hurl/`, one per subject, that check a running app from the outside; each logs in by itself, so they
  run in parallel.
- Test coverage per class in the summary of the CI job "Checks" (`composer test:coverage`), as information only.
- CI jobs on MariaDB 10.11 and MySQL 8.4: migrations from an empty database, schema validation, every `down()`,
  the database tests and every sample data set.
- Opengrep rules against queries outside a repository, query builder parts built from strings, predictable random
  functions and broken hashes, and byte-wise case changes.
- ADR 0005 on the database approach; fixed limits in `docs/configuration.md`.
- OpenAPI description of the API at `GET /api/v1/docs.json` (NelmioApiDocBundle), public, in JSON only and as OpenAPI 3.1.
- API errors as `application/problem+json` with a title in Dutch or English (`Accept-Language`), field errors for
  invalid input, and no internal detail.
- Personal API tokens in `Authorization: Bearer`: looked up by hash on every request, refused at once when revoked,
  expired or when the member is deactivated or erased, held to their scopes (`read`, `write`, `admin`), and the
  last use recorded at most once a minute.
- Own account: `GET /api/v1/me` shows the profile; `PATCH /api/v1/me` changes phone number, address, consent and
  notifications about open rounds, never name or e-mail address; `PUT /api/v1/me/password` sets a new password in a
  recent session, also after a login with a code; `GET /api/v1/me/activity` lists the member's own actions a page at
  a time; `GET /api/v1/me/export` hands over all own data as a JSON file, of other members only display names.
- `GET`, `POST` and `DELETE /api/v1/me/tokens`: a new API token needs a recent session and an end date within 365
  days, and shows its secret only in the answer that created it; a request with an API token gets 403
  `browser_only` there. Every API token expires; tokens without an end date get one 365 days after they were made.
- Rounds: `GET /api/v1/rounds` for a period of at most 93 days, `GET /api/v1/rounds/{id}`, `POST /api/v1/rounds`,
  `PATCH` and `DELETE /api/v1/rounds/{id}`, `POST /api/v1/rounds/{id}/cancellation`, `GET /api/v1/staffing-gaps` and
  `GET /api/v1/me/rounds` with a view (`upcoming`, `to_report` or `all`), an optional period of at most 93 days that
  `all` needs, an order for `all` and a limit of at most 200; every round carries its status, staffing, open spots and daypart. Signing up and
  withdrawing with `PUT` and `DELETE /api/v1/rounds/{id}/walkers/me`, or `/walkers/{memberId}` for a coordinator,
  answer the same when repeated.
- Review focus 2 through the API: a round planned at 22:00 on the evenings around both clock changes, with the offset
  of the day, the other offset or `Z`, comes back at 22:00 with the right offset and in the evening, in one round, in
  the list and in the own rounds, in memory and once through the database.
- After the round: `POST /api/v1/rounds/{id}/reports` and `DELETE /api/v1/reports/{id}`, `POST
  /api/v1/rounds/{id}/comments` and `DELETE /api/v1/comments/{id}`; `GET /api/v1/reports` lists the reports of a
  period of at most 93 days for analysts and coordinators, and `GET /api/v1/rounds/{id}/history` the history of a
  round for coordinators, with an erased member as a removed member. The note of a report and the comments of a
  round are for its walkers, analysts and coordinators only, also in `GET /api/v1/rounds/{id}`.
- Members: `GET /api/v1/members` lists the active members, with phone number and address only for a member who
  consents and never an e-mail address. Name, phone number and address are always in the answer, null for what the
  reader may not see, and so are name and phone number of the walkers in `GET /api/v1/rounds/{id}`. Under `/api/v1/admin/members` the admin lists, shows, adds and changes
  members, sets roles, deactivates, reactivates and erases them, and has an invitation sent; changing roles and
  erasing need a recent login. The secret of an invitation is in the answer only for an admin inviting a member who
  never logged in.
- Catalogues: `GET /api/v1/meeting-points` and `GET /api/v1/outcomes` list what a member can choose; under
  `/api/v1/admin/meeting-points` and `/api/v1/admin/outcomes` the admin lists them with the archived ones, adds,
  changes, archives and restores them. A name that is taken answers 409 with the id of the item that holds it and
  whether that one is archived.
- Audit log for the admin: `GET /api/v1/admin/audit` narrowed by member, action and period, 50 entries a page as
  JSON, or with `Accept: text/csv` every matching entry as a streamed CSV file, with a fixed column order and cells
  that a spreadsheet would run as a formula neutralised. A file ends before the second of the request, so an entry
  recorded while it streams never appears twice. A file holds at most 10,000 entries; more answers 422
  `too_many_entries` with their count. An Accept header that allows neither format answers 406.
- JSON keys and query parameters are camelCase, codes and enum values snake_case; tests hold the OpenAPI description
  and the outputs to it.
- Every `PATCH` is a JSON Merge Patch: a field left out stays, null clears a field that may be empty, a value
  replaces it, and null on a required field is refused. `PATCH /api/v1/me` refuses empty text for a phone number or
  address; null removes it. A meeting point takes latitude and longitude as a pair.
- The OpenAPI description covers every route, the login routes of the firewall included, and names the successful
  answer, with the schema of its body, and the problem answers of every operation; a test holds it to the router and to the Hurl files. A controller may carry
  OpenAPI attributes to document its endpoint.
- An HTTP matrix test runs every route against every role, next to the matrix of the voters, and a test signs a
  member up with a personal API token alone, as curl would. In the `demo` sample set the walker who uses the API has
  a token that reads and writes, whose secret the fixture command shows; `docs/getting-started.md` walks through
  signing up with curl.
- ADR 0006 on authentication: two firewalls, own authenticators, a hash for every secret, recent logins and scopes per
  request.
- Opengrep rules `request-value-in-query`, `secret-compared-with-equals`, `security-disabled` and
  `insecure-session-cookie`, each with a rule test; `sql-built-from-strings` leaves the `set()` of response headers
  alone.
- MCP server at `/mcp` (Streamable HTTP, both protocol eras) and `symfony console mcp:server` (STDIO, with an API
  token in `MCP_TOKEN`), through `symfony/mcp-bundle` and `mcp/sdk`, both pinned to their minor. Off until an admin
  sets `mcpAccess`; off, `/mcp` answers 404 to every request. Only API tokens give access, on the channel `mcp` in the
  audit log; a token may make 60 requests a minute, and the server answers only for the host of `DEFAULT_URI`.
- Thirteen tools, each the `…Tool` adapter of a use case with a parity test against REST: `list_rounds`, `get_round`,
  `my_rounds`, `staffing_gaps`, `list_meeting_points`, `list_outcomes`, `sign_up_for_round`, `withdraw_from_round`,
  `create_round`, `report_round_result`, `add_round_comment`, `report_summary` and `round_statistics`. A tool that
  only reads needs the scope `read`, any other `write`; a failed call is a tool result with the problem REST would
  answer. No tool, resource or prompt gives a phone number, address or e-mail address, nor the note of a report or
  a comment.
- The resource `buurtpreventie://config` and the prompts `monthly_report` and `plan_week`.
- `GET /api/v1/stats?from=&to=&groupBy=` (`RoundStatistics`): how the rounds of a period of at most a year went per
  month, weekday or daypart, next to the period before, for analysts and coordinators.
- `GET /api/v1/reports/summary?from=&to=` (`SummariseRoundReports`): the reports of a period counted per outcome, and
  the incidents per month, without notes or authors.
- `GET /api/v1/staffing-gaps?days=` limits the gaps to the next 1 to 31 days.
- ADR 0008 on the MCP server, and `docs/user-guide/mcp.md` on connecting Claude Code and the MCP Inspector.
- `GET /api/v1/rounds`, `GET /api/v1/staffing-gaps` and the tools `list_rounds` and `staffing_gaps` say per round
  whether the member asking walks it (`signedUp`), so a client tells a round of their own that seeks a buddy from one
  to join.
- A Claude Code plugin in `plugins/buurtpreventie`, installed from `main` through a marketplace in this repository: it
  asks for the address of the app and an API token at install, keeps the token in the keychain of the system, and adds
  a skill on when and how to use the tools: one confirmation per write, text typed by members read as data, results
  passed nowhere else unasked. The skill also works in Cursor and GitHub Copilot. Its README and the user guide say
  what reaches the provider of an AI client, that a member checks before confirming, and that it comes without
  warranty.
- The instructions of the MCP server name every field with text typed by members and ask for a confirmation before
  every write.
- The `edge` sample data hold a start note and a display name that try to instruct an AI client.
