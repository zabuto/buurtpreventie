# ADR 0001: modulaire monoliet met een map per use case

- Status: aangenomen
- Datum: 2026-11-15

## Context

Buurtpreventie v4 krijgt drie ingangen: een REST-API voor de frontend, een MCP-server voor
AI-clients en een scheduler voor herinneringen. Een regel zoals "minimaal twee lopers" of "alleen je
eigen terugmelding verwijderen" moet voor alle drie precies hetzelfde gelden. Het project heeft één
onderhouder, draait op shared hosting zonder workers en moet over te dragen zijn aan een andere
ontwikkelaar die er binnen een uur zijn weg in vindt.

De voorganger (v3) heeft controllers, formulieren, Twig-pagina's en een service met domeinregels door elkaar,
met rechten verspreid over controllers en templates.

## Besluit

- **Eén Symfony-app, ingedeeld in modules** direct onder `src/`: `Patrol`, `Membership`, `Identity`,
  `Audit`, `Settings`, `Maintenance`. Elke module heeft `Domain/`, `UseCase/` en `Infrastructure/`.
  `Shared/` bevat bouwstenen zonder use cases.
- **Eén map per use case** met vaste bestanden: invoer, handler, uitvoer en per ingang een dunne
  adapter (`…Controller` voor REST, `…Tool` voor MCP). De handler is de enige plek voor rechten,
  regels en auditlog.
- **Modules gebruiken elkaar alleen via use cases** of via interfaces in `Shared\Domain`.
- **De grenzen worden bewaakt** met PHPat-regels in PHPStan, met een test die bewijst dat elke regel
  afgaat, en met een test voor de mapindeling. Elke foutmelding verwijst naar
  [architecture.md](../architecture.md).
- **Een generator** (`bin/console app:make:use-case`) maakt een nieuwe use case, zodat nieuwe code het
  patroon vanzelf volgt.

## Gevolgen

- REST en MCP staan precies hetzelfde toe: ze delen handler en invoer, en de regels verbieden
  adapters eigen logica.
- Domeinregels zijn zonder framework te testen.
- Een opvolger leert één patroon en vindt elke handeling op een voorspelbare plek.
- Meer bestanden per handeling dan bij één controller met alle logica. De generator en de vaste namen
  houden dat beheersbaar.
- Use cases die alleen via cron lopen, hebben geen controller of tool; die bestanden zijn daarom
  optioneel.
- Microservices, een message bus of CQRS met aparte leesmodellen vallen af: ze vragen workers of
  infrastructuur die shared hosting niet biedt, en voegen voor tientallen gebruikers niets toe.
