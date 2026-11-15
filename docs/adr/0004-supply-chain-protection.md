# ADR 0004: cooldowns and a code scan against supply-chain attacks

- Status: accepted
- Date: 2026-11-15

## Context

A hijacked maintainer account or a stolen publish token lets an attacker release a malicious version
of a trusted package. Most such releases are found and withdrawn within hours or days. The project
merges Dependabot patch and minor updates automatically, and one maintainer reviews the rest.

The project also wants its own code scanned for dangerous patterns, with tools that are open source,
actively maintained and an industry standard, and whose licence allows use on a PolyForm
Noncommercial code base (see [ADR 0003](0003-polyform-noncommercial-licence.md)).

## Decision

- **Dependabot cooldown, without exemptions.** Composer updates wait 7 days for a patch or minor and 14
  days for a major; actions wait 7 days, since Dependabot has no per-update-type cooldown for them.
  Security updates bypass this cooldown.
- **Composer cooldown of 24 hours** (`config.policy.cooldown`, Composer 2.11 or later). It withholds new
  releases during every `composer update` and `composer require`, also on a developer machine. It stays
  short because it also delays a security fix that Dependabot proposes at once.
- **Opengrep (LGPL-2.1) scans the code in CI** with a rule set of its own in `.opengrep/rules/`, each
  rule with a rule test. A finding fails the job.
- **CI picks the Opengrep version** within the major in `tools/opengrep-major`, with the same 7-day
  cooldown. Opengrep has no Composer package or container image, so Dependabot cannot update it.
- **Only a verified binary runs:** `cosign verify-blob` checks the signature against the exact identity
  of the Opengrep release workflow for that version. Never `install.sh` or PyPI.
- **A new Opengrep major never arrives automatically.** Once it is 14 days old, the weekly workflow
  opens an issue and `composer update` prints a warning; moving to it takes a pull request.

## Consequences

- A compromised release that is withdrawn within a week never reaches `develop` through Dependabot, and
  one withdrawn within a day never reaches a developer machine.
- A security fix reaches `develop` after at most 24 hours instead of at once.
- Opengrep releases and CI runs can change the scan result without a commit; the version and SHA-256
  of every run are in the log.
- The scan runs only in CI; local runs would need Cosign on every machine.
- GitHub CodeQL is ruled out: its licence only covers code under an OSI-approved licence. The Semgrep
  rule set never goes into the repository: its licence forbids distribution.
- Mend's hosted Renovate app is ruled out: it would get write access to the repository.
- A vulnerability in PHP itself is outside all of this; the maintainer raises the minimum PHP version
  (see [ADR 0002](0002-symfony-lts-and-php-minimum.md)).
