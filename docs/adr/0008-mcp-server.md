# ADR 0008: an MCP server on personal API tokens, off unless an association switches it on

- Status: accepted
- Date: 2026-11-17

## Context

Members want to ask an AI client such as Claude Code about rounds, buddies and statistics, and to sign up from it. The
Model Context Protocol (MCP) is how such a client calls an app. The app runs on shared hosting: one PHP process per
request, no process that stays, no Redis.

The MCP SDK for PHP (`mcp/sdk`) and its Symfony bundle (`symfony/mcp-bundle`) are both below 1.0 and marked
experimental. The SDK serves two protocol eras: revision `2026-07-28`, where every request stands on its own, and the
handshake era before it, which needs a session. The SDK is a resource server only: it checks tokens, it issues none.

An AI client passes what a server gives it to a language model, outside the association. Members share their phone
number and address with each other by consent, not with that model. Notes and comments are typed by members and may
hold anything, including text that reads as an instruction to the model.

## Decision

- **One server at `/mcp`, through the bundle, over Streamable HTTP**, with JSON answers and no stream, plus
  `mcp:server` over STDIO. Revision `2026-07-28` runs without a session; the handshake era keeps a session in files
  that holds only the negotiated revision. The bundle offers no handshake without a session.
- **Personal API tokens only**, the ones members make for scripts ([ADR 0006](0006-authentication.md)), on a firewall
  of its own. A browser session gives no access to `/mcp`. OAuth for clients that only speak OAuth is a later
  extension (U10).
- **Off by default, per association.** The setting `mcpAccess` makes `/mcp` answer 404 to every request, before a
  token is looked up, and keeps `mcp:server` from starting. Switching API tokens off switches MCP off too.
- **A tool is the `…Tool` adapter of a use case**: it builds the input, validates it with the constraints of the REST
  layer and calls the same handler, so the voter, the audit entry and every rule are the ones REST has. A tool acts
  only for the member of the token, never on behalf of another.
- **Scope per tool from its annotations**: `read` for a tool that only reads, `write` for the rest. Every MCP request
  is a POST, so the scope per HTTP method of the API cannot tell them apart.
- **No contact details over MCP**, consent or not: a walker shows by id and display name only, and the resource leaves
  out the contact of the association.
- **No notes or comments over MCP.** A round gives the outcome of its report, without the note, and no comments.
  Members type them freely, often about a suspect; filtering them for phone numbers and names cannot be relied on.
  Writing them through a tool stays possible: the client has seen that text already. The start note and the reason
  for a cancellation stay, as short practical text, in their own field, which the instructions of the server call
  data.
- **No tool deletes**, and none manages members, settings or tokens: those stay in the app, where a person confirms.
- **A limit of 60 requests a minute per token**, counted in files, and the DNS rebinding protection of the bundle with
  the host of the app as the only host.
- **The bundle and the SDK are pinned to their minor.** A new minor comes in a pull request of its own.

## Consequences

- An association decides itself whether its members' rounds reach an AI client; a fresh installation sends nothing
  anywhere.
- The Claude Code plugin with its skill guides a model; it protects nothing. A rule that has to hold belongs in the
  server, in a voter, a scope, the output of a handler or a limit, never in the skill alone, since a client may ignore
  the skill or run without it.
- A client of either era works, Claude Code and the MCP Inspector included; the handshake era writes small session
  files that an update may clear, after which the client starts over.
- A member can only do over MCP what the app already lets that member do, with the same audit trail on the channel
  `mcp`.
- Analysts and coordinators get statistics and a summary of the reports over REST too (`/stats`,
  `/reports/summary`), since a tool calls a handler that REST calls as well.
- Notes and comments are read in the app only; an association that wants them over MCP needs a setting of its own,
  off by default, which does not exist yet.
- A start note or cancellation reason reaches the client as typed: the server filters no free text.
- STDIO checks its token once, at start: after revoking a token, a running `mcp:server` stops only when its process
  ends.
- An update of the SDK or the bundle can break the server; the tests through the client of the SDK, per tool and per
  era, show where.
