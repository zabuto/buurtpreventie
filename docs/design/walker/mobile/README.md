# Design: walker, mobile

Approved screens for the **walker** role on a **phone** (390 × 844 CSS pixels). The canvas "Buurtpreventie v4 —
schermen" (page Mobile) is the workshop; this folder is the recorded version, so the frontend can be checked without
access to the canvas. Only a screen with status "approved" is built.

## Conventions

- **Folder:** `docs/design/<role>/<device>/<screen>.png`. Role: `walker`, `coordinator`, `analyst`, `admin`. Device:
  `mobile`, `tablet`, `desktop`.
- **Names:** each screen has two names. The canvas file is English (`login.dc.html`) and gives the image its name
  here (`login.png`). The canvas title is Dutch ("Inloggen", as the walker reads it) and is what the export names
  the PNG ("Inloggen · goedgekeurd 18 nov@2x.png"), so every export is renamed to the Image column below.
- **Format:** PNG, exported from the canvas at **2x**: a screen 390 px wide is 780 px in the image. A size taken from
  the image divided by two is the size in CSS pixels (a button 104 px high in the image is 52 px in the code).
- **Font:** Inter in the canvas and so in every image; the app uses the system font
  ([ADR 0009](../../../adr/0009-system-font.md)). In the app a line may break differently, least on Apple devices; sizes,
  colours and order may not.
- **Height:** the table gives the height in CSS pixels of an approved screen only; the image is twice as high. A
  scrolling screen is exported at its full height, so its image is taller than 1688 px, and its height above 844
  carries "(scrolls)" (`920 (scrolls)`). A draft has no height: it can change in the canvas until approval.
- **Approved only:** a screen lands here after approval, in its approved state. A screen whose design changes goes back
  to "· concept" in the canvas and to "draft" here, without a height, until it is approved again, with a new export
  and a new date. A change of font alone keeps the status and date and only replaces the image.
- **Status in the canvas title:** "· concept" (not approved, not built), "· goedgekeurd <date>" (built to this image),
  "· later (fase …)" (belongs to a later phase). The Status column below mirrors it as "draft", "approved <date>" or "later".

## Screens

This is the only list of walker screens on a phone; the phase plan points here. A screen or state added to the canvas
gets a row here with status "draft" and no height in the same change. Canvas titles are quoted from the canvas,
without the status after the "·".

### Sign-in

| Image | Canvas title | Status | Height (CSS px) |
| --- | --- | --- | --- |
| `login.png` | Inloggen | approved 2026-11-19 | 844 |
| `login-error.png` | Inloggen — fout | approved 2026-11-19 | 844 |
| `session-expired.png` | Opnieuw inloggen | approved 2026-11-19 | 844 |
| `login-code.png` | Code invullen | approved 2026-11-19 | 844 |
| `login-code-error.png` | Code invullen — fout | approved 2026-11-19 | 844 |
| `welcome.png` | Welkom (uitnodiging) | approved 2026-11-19 | 844 |
| `set-password.png` | Wachtwoord instellen (mag) | approved 2026-11-28 | 844 |
| `set-password-error.png` | Wachtwoord instellen — fout | approved 2026-11-28 | 900 (scrolls) |
| `browser-unsupported.png` | Browser te oud | approved 2026-11-19 | 844 |

### Rounds

| Image | Canvas title | Status | Height (CSS px) |
| --- | --- | --- | --- |
| `start.png` | Start | draft | |
| `start-empty.png` | Start — geen loopmaatje nodig | draft | |
| `start-offline.png` | Start — offline | draft | |
| `calendar.png` | Kalender | draft | |
| `calendar-list.png` | Kalender — lijst | draft | |
| `calendar-past.png` | Kalender — vorige week | draft | |
| `round-past.png` | Afgelopen ronde | draft | |
| `plan-round.png` | Ronde plannen | draft | |
| `form-error.png` | Ronde plannen — fout | draft | |
| `round-buddy-needed.png` | Ronde — loopmaatje nodig | draft | |
| `round-open-spots.png` | Ronde — genoeg lopers, je kunt mee | draft | |
| `round-just-full.png` | Ronde — net vol | draft | |
| `round-with-others.png` | Ronde — jij en andere loper(s) | draft | |
| `round-alone.png` | Ronde — alleen jij | draft | |
| `change-round.png` | Ronde wijzigen | draft | |
| `round-full.png` | Ronde — vol | draft | |
| `add-comment.png` | Opmerking toevoegen | draft | |
| `confirm-withdrawal.png` | Afmelden bevestigen | draft | |
| `confirm-withdrawal-last.png` | Afmelden als enige loper | draft | |
| `report-result.png` | Resultaat melden | draft | |
| `round-not-walked.png` | Ronde ging niet door | draft | |
| `my-rounds.png` | Mijn rondes | draft | |
| `my-rounds-empty.png` | Mijn rondes — leeg | draft | |

### Account

| Image | Canvas title | Status | Height (CSS px) |
| --- | --- | --- | --- |
| `more.png` | Mijn account | draft | |
| `my-details.png` | Mijn gegevens | draft | |
| `download-data.png` | Mijn gegevens downloaden | draft | |
| `confirm-identity.png` | Bevestig dat jij het bent | draft | |
| `my-activity.png` | Mijn activiteit | draft | |
| `integrations.png` | Koppelingen | draft | |
| `integration-created.png` | Koppeling gemaakt | draft | |
| `notifications.png` | Meldingen | draft | |

### General

| Image | Canvas title | Status | Height (CSS px) |
| --- | --- | --- | --- |
| `no-connection.png` | Geen verbinding | draft | |
| `update-available.png` | Nieuwe versie beschikbaar | draft | |
| `error.png` | Er ging iets mis | draft | |
| `error-offline.png` | Er ging iets mis — geen bereik | draft | |
| `page-not-found.png` | Pagina niet gevonden | draft | |
| `not-found.png` | Ronde niet gevonden | draft | |
| `no-access.png` | Geen toegang | draft | |
| `under-construction.png` | In aanbouw | draft | |
| `privacy.png` | Privacyverklaring | later | |
| `help.png` | Hulp en spoed | draft | |
| `handbook.png` | Handleiding voor lopers | later | |

"Privacyverklaring" and "Handleiding voor lopers" are designs for later; in the app, the links to them open
"In aanbouw".

"Geen verbinding" and "Nieuwe versie beschikbaar" appear as a notice that slides down below the top bar; with reduced
motion on, it appears without sliding.

## Adding an approved screen

1. Set the canvas title to "· goedgekeurd <date>".
2. Export the screen from the canvas as PNG at 2x, at its full height.
3. Put the image in this folder under the name from the table, check it is 780 px wide and twice the screen's full
   height in the canvas, and set the status to "approved <date>" and the height in the table, with "(scrolls)" after a
   height above 844.

The prompts for the canvas and for Claude Code are B and D in [the phase 5 plan](../../../plans/phase-5.md) (T2).
