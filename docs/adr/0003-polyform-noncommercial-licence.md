# ADR 0003: PolyForm Noncommercial 1.0.0

- Status: accepted
- Date: 2026-11-15

## Context

Buurtpreventie is made for neighbourhood and residents' associations, which run it without a profit
motive. Commercial resale of the software, for example as a paid hosted service, is not intended.
The predecessor (v1 to v3) is MIT-licensed, with the same author as copyright holder.

## Decision

- **v4 is released under PolyForm Noncommercial 1.0.0**, with the `Required Notice:` line at the top
  of `LICENSE`, from the first commit of the repository.
- **The predecessor stays MIT.** Its last commit carries the tag `v3-mit-final` and a README pointing
  to v4; MIT versions keep their licence.
- **The README speaks of "free to use for non-commercial purposes"**, says the software is meant for
  neighbourhood and residents' associations, and calls it source-available, not open source in the
  OSI sense. The software is provided as is, without warranty or support.

## Consequences

- Anyone may use, change and share the software for a non-commercial purpose, including charities,
  education and government; commercial use is not allowed. Whoever passes it on includes the licence
  and the `Required Notice:` line.
- Dependencies under MIT or Apache 2.0, such as Symfony and Doctrine, combine with this licence as
  long as their licence texts ship in `vendor/`.
- Tools whose licence only covers code under an OSI-approved licence cannot be used on this
  repository. GitHub CodeQL is one of them.
- Rule sets or other material whose licence forbids distribution never go into this repository, such
  as the Semgrep rules; they may be fetched by CI at run time where their licence allows that use.
- A successor takes over through a fork; the licence and the `Required Notice:` line go with it.
- This is no legal advice.
