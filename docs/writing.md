# Writing guidelines

Rules for every text in this repository: comments, docblocks, test names, messages, identifiers,
documentation and the texts users see.

## The reader decides the language

| Reader | Texts | Language |
| --- | --- | --- |
| Developer | Comments, docblocks, test names, assertion and log messages, developer exceptions, command output, identifiers, documentation | UK English |
| User | Screen texts, labels, notifications, validation and error messages, e-mails, the installer | Dutch and English through translation files, at the levels below |
| Unclear | Such as an exception that may reach the screen | Decide explicitly: a text that can reach a user goes through the translation files |

Which documents are Dutch and which English is listed in `AGENTS.md` (Language and naming).

## English for developers

- **UK spelling** for prose: behaviour, organise, licence (noun).
- **Code and framework vocabulary keeps its US spelling**, also inside UK prose, test names and
  messages: serializer, normalizer, denormalize, authorization, initialize.
- **A quotation keeps the spelling of its source.**
- **Identifiers are English.** A Dutch domain term uses its code name from
  [glossary.md](glossary.md): a loopmaatje is a buddy, a terugmelding a `RoundReport`. Existing names
  are never translated.

## Every text

- **Concise.** The fewest words that keep all information and the durable why. Wordy, repeated or
  code-restating text gets sharpened.
- **Limits for code texts** (comments, docblocks, test names, assertion, log and exception messages):
  at most 4 sentences per text and 50 words per sentence. A lead-in with its list counts as one
  sentence; each item stays within 50 words.
- **People without gender.** A text about a person, sample people included, uses no gendered pronoun:
  in English "they" and "their" or the name or role, in Dutch "die", "de eigen" or the role ("de
  loper", "het lid"), never "hij", "zij", "hem", "haar" or "zijn" for a person. A pronoun for a thing
  follows grammar, such as "hij" for "de link".

## Comments and documentation

State the end result and the durable why, never the process that produced it.

- **No narrative.** Comments, docblocks, test names, messages, command output and repository Markdown
  describe what holds today and what breaks if it stops holding. Words that point at an earlier state
  ("again", "back", "still", "no longer", "now"; in Dutch "weer", "nog", "niet meer", "nu") belong in
  the commit message or pull request, not in the code.
- **Existing narrative is no licence.** Matching the surrounding code covers style and idiom, not
  narrative. Fix it in a block you rewrite; leave other lines alone.
- **Keep the durable why:** platform behaviour that forces a non-obvious shape, why a simpler version
  silently fails, why a silence is deliberate.
- **Omit the obvious.** Comment only what the code does not say.
- **The rule itself, not where it came from.** A comment or docblock never names an issue, a plan, a
  phase or a decision number; it states the rule.
- **No Markdown fluff.** Do not document what tooling implies, such as how to run the test runner or
  standard framework commands. A precondition of one test goes in that test's docblock and skip
  message, not in `README.md` or `AGENTS.md`.

## Dutch for users

Walkers differ widely in digital skills; texts are written for the least experienced one. The level
follows what a text does, not where it sits.

- **Explanations at B1** (help texts, instructions, labels, option and status names, notifications,
  error and validation messages): at most 20 words and one point per sentence, common words, active
  voice, and the imperative for an action ("Meld je aan", "Stuur de terugmelding"). No technical words
  such as "sessie". "Token" is the exception, in texts about connecting an AI app through MCP: the
  member makes one in the app and pastes it into the AI app, which calls it that too, and a plain word
  such as "koppeling" would read as the connection itself. Buttons say what they do: "Ik loop mee",
  "Ik kan toch niet". Write out an abbreviation at its first use on a screen or in a message
  ("Algemene verordening gegevensbescherming (AVG)"). The domain terms from the glossary stay as they
  are.
- **Need to know.** A text an end user reads (screen, mail, error message, `docs/user-guide/`, the plugin
  README) names no setting, endpoint, status code or other detail the reader has no access to: "Vraag het de
  beheerder." or "Ask the admin." is enough. The admin finds the detail in `docs/configuration.md`. A detail the
  reader needs to act, such as the address of the app in an AI client, stays.
- **Mails never look like phishing.** A mail that warns, such as about a new token or a reached limit, holds
  no link or button: it says where to go in the app ("Open dan zelf de app. Trek het token in onder
  Koppelingen."). A warning with a link is what a phishing mail looks like, and members learn from ours that a
  real one never asks them to click. No mail asks for a password, login code or token. Other links go only to
  the address of the app, with a link text that says where it leads.
- **Rule texts unambiguous, at B2** (privacy statement, retention periods, who may see what, consent):
  "and" and "or" are explicit ("als aan alle voorwaarden is voldaan", "een van deze situaties"), every
  party is named, and a long condition becomes a lead-in with a list. B1 limits do not apply here:
  simplifying a rule text changes the rule.
- **Modality stays.** A power stays a power and a duty stays a duty: "kan" never becomes an imperative,
  and "moet" never becomes "kan".
- **Same reach as the source.** A rule text is never broader or narrower than the rule it restates,
  such as a retention period in the privacy statement. Compare it only with a source that the
  repository or the plan quotes; never restate law from memory.

English user texts follow the same levels in plain English.
