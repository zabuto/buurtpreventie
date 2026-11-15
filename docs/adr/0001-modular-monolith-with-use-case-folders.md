# ADR 0001: modular monolith with a folder per use case

- Status: accepted
- Date: 2026-11-15

## Context

Buurtpreventie has three entry points: a REST API for the frontend, an MCP server for AI clients
and a scheduler for reminders. A rule such as "at least two walkers" or "only delete your own round
report" must hold for all three in exactly the same way. The project has one maintainer, runs on
shared hosting without workers, and must be transferable to another developer who finds their way
within an hour.

The legacy app mixes controllers, forms, Twig pages and one service with domain rules, with
permissions spread over controllers and templates.

## Decision

- **One Symfony app, split into modules** directly under `src/`: `Patrol`, `Membership`, `Identity`,
  `Audit`, `Settings`, `Maintenance`. Every module has `Domain/`, `UseCase/` and `Infrastructure/`.
  `Shared/` holds building blocks without use cases.
- **One folder per use case** with fixed files: input, handler, output, and a thin adapter per entry
  point (`…Controller` for REST, `…Tool` for MCP). The handler is the only place for permissions,
  rules and the audit log.
- **Modules use each other only through use cases** or through interfaces in `Shared\Domain`.
- **The boundaries are guarded** by PHPat rules in PHPStan, by a test proving each rule fires, and by a
  test for the folder layout. Every error message links to [architecture.md](../architecture.md).
- **A generator** (`bin/console app:make:use-case`) creates a new use case, so new code follows the
  pattern by default.

## Consequences

- REST and MCP allow exactly the same: they share handler and input, and the rules forbid adapters
  their own logic.
- Domain rules are testable without the framework.
- A successor learns one pattern and finds every action in a predictable place.
- More files per action than one controller holding all logic. The generator and the fixed names keep
  that manageable.
- Use cases that run only from cron have no controller or tool; those files are therefore optional.
- Microservices, a message bus or CQRS with separate read models are ruled out: they need workers or
  infrastructure that shared hosting does not offer, and add nothing for a few dozen users.
