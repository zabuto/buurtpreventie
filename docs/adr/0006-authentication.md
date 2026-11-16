# ADR 0006: two firewalls, own authenticators, a hash for every secret, recent logins and scopes per request

- Status: accepted
- Date: 2026-11-16

## Context

The API serves two kinds of client. The frontend runs in the browser on the same domain and needs a session that
survives a closed tab, protection against requests forged from other sites, and a way in for members who never set
a password or forgot it. Scripts and, from phase 4, MCP clients need a personal key without a session.

Members range from a confident admin to a walker who has never typed a password into an app. Most read their mail
on a phone. The app runs on shared hosting with no mail it can trust to arrive at once, and a database a copy of
which may one day leak.

Every write records who acted and through which channel, and the voters decide on roles alone
([architecture.md](../architecture.md#authentication)). Whoever is logged in must reach the handlers in one shape:
the token storage, with the member and the channel.

## Decision

### Two firewalls

- **`api_token` for every request with an `Authorization` header**, stateless, without a session or CSRF. Any
  header goes there, `Basic` included, so a wrong header is refused and logged rather than treated as a request
  without a session.
- **`browser` for everything else under `/api/`**, with a session, CSRF and stay-logged-in. A request without a
  session gets 401 from the entry point. `/api/v1/health`, `/api/v1/docs.json` and `/api/v1/auth/` are open.
- The channel is `web` on the browser firewall, `api` on the token firewall, and `mcp` on the firewall of phase 4.

### Own authenticators

- **The token authenticator is a class of our own**, not Symfony's `access_token`, which skips a request without a
  header so that a missing token would never reach our log. It looks the token up by the SHA-256 of its secret, and
  one `match` names every reason to refuse: unknown, revoked, expired, member deactivated or erased. A refusal is a
  401 without a body, with `WWW-Authenticate: Bearer`; the reason goes to the log only. `markUsed()` writes at most
  once a minute.
- **Login with a password, a code, an invitation and the confirmation of a password are authenticators** on the
  browser firewall, not `json_login` and not use cases. The member user carries no hash, the checks are steps of the
  passport, so Symfony's login throttling decides before anything is hashed, and an unknown address costs the same
  hash as a known one. A failed login answers the same whatever went wrong.
- **Identity finds a member through `Shared\Domain\MemberDirectory`**, not through a use case: a use case asks a
  voter, and nobody is logged in yet. The user provider reads roles and status afresh on every request, so a
  deactivated or erased member drops out of a running session, and a changed role counts on the next request.
- **No JWT.** A JWT cannot be revoked at once without a lookup on every request anyway, its content is readable, and
  one leaked signing key signs any token.

### A hash for every secret

- An API token (`bpt_`) and an invitation (`bpi_`) are 32 random bytes, stored as SHA-256; nobody types or chooses
  one, and any other format is refused.
- A login code has six digits, so plain SHA-256 is reversed in a second from a copy of the database. It is stored as
  HMAC-SHA-256 with `APP_SECRET` over the id of the request and the code, and compared with `hash_equals()`.
- A login code works only with the request it belongs to, which the browser keeps: a forwarded mail, a link a virus
  scanner opens, or someone who sees only the mail logs in nobody. It is valid for 60 minutes, once, for at most 5
  attempts, and a new request ends the earlier ones. There is no separate password reset: a forgotten password is a
  login with a code, after which the recent session sets a new one.
- An invitation is a link, valid 48 hours and once, because a new member has no screen to ask for a code in. The
  secret sits after a `#`, which the browser never sends to a server, and the page posts it.

### Browser protection

- **CSRF with `SameOriginCsrfTokenManager`** on every changing request of the browser firewall, the login included:
  `Sec-Fetch-Site` and `Origin`, and for a browser that sends neither (Safari on iOS 15) a cookie and a header the
  frontend sets. A check on `Origin` alone would either lock those devices out or leave them unprotected.
- **Session cookie `bp_session`**: `Secure`, `HttpOnly`, `SameSite=Lax`, a new id at login.
- **Stay logged in for 90 days** with Symfony's `remember_me`, signed over the member and the moment the password
  last changed: a new password ends every remembered device and every other session.
- **Rate limits** with `symfony/rate-limiter`: 5 failed logins per address and IP address in 15 minutes, 30 per IP
  address in an hour, 3 code requests per address and 10 per IP address in an hour; then 429 with `Retry-After`.
- **Login entries** in the audit log, with the IP address shortened.

### Recent login

- **`Shared\Domain\RecentAuthentication`**: a session that logged in or confirmed with a password, code or
  invitation in the last 15 minutes is recent; a remembered session and an API token never are. `POST /auth/confirm`
  with the password makes a session recent. A new password, a new API token, changing roles and erasing a member
  need it, otherwise the 403 problem `reauthentication_required`. An API token is never recent, so those actions run
  in the browser only. Symfony 8.4's sudo mode takes this over.
- A new password needs a recent session and never the old password: a check of the old password in the use case
  would be a way to guess passwords without throttling.

### Tokens and scopes

- **Scopes are checked per request, not in the voters.** `read` for `GET` and `HEAD`, `write` for every other method,
  `admin` for `/api/v1/admin/`; a missing scope is the 403 problem `insufficient_scope`. The voters decide on roles as
  for any request, so scope and role must both allow an action, and one test covers every scope.
- **Tokens are managed in the browser only.** Every token request under `/api/v1/me/tokens` gets 403
  `browser_only`, so a leaked token can neither make another nor revoke the one its owner needs.
- **Every token expires**, at most 365 days after it was made.

## Consequences

- A member logs in without a password, and a forgotten password needs no reset flow of its own.
- A copy of the database yields no usable secret: no token, invitation or code can be read back from it.
- Revoking a token or deactivating a member works on the next request, at the cost of a lookup per request.
- Five authenticators and their checks are code of our own to keep up with Symfony; the tests per authenticator and
  the HTTP matrix guard them.
- Scripts and MCP cannot change roles, erase a member or manage tokens, by design.
- Members on iOS 15 depend on the CSRF cookie the frontend sets; a frontend that forgets it locks them out of every
  change.
