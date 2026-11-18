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
- **Font:** the system font ([ADR 0009](../../../adr/0009-system-font.md)). Screens are reviewed, approved and
  exported on a Mac, so the images show San Francisco. On Android and Windows a line may break differently; sizes,
  colours and order may not.
- **Height:** the table gives each screen's height in CSS pixels; the image is twice as high. A scrolling screen is
  exported at its full height, so its image is taller than 1688 px.
- **Approved only:** a screen lands here after approval, in its approved state. A screen whose design changes goes back
  to "· concept" in the canvas and to "draft" here until it is approved again, with a new export and a new date. A
  change of font alone keeps the status and date and only replaces the image.
- **Status in the canvas title:** "· concept" (not approved, not built), "· goedgekeurd <date>" (built to this image),
  "· later (fase …)" (belongs to a later phase). The Status column below mirrors it as "draft", "approved <date>" or "later".

## Screens

Canvas titles are quoted from the canvas, without the status after the "·".

### Sign-in

| Image | Canvas title | Status | Height (CSS px) |
| --- | --- | --- | --- |
| `login.png` | Inloggen | approved 2026-11-18 | 844 |
| `session-expired.png` | Opnieuw inloggen | approved 2026-11-18 | 844 |
| `login-code.png` | Code invullen | draft | 844 |
| `login-code-error.png` | Code invullen — fout | draft | 844 |
| `welcome.png` | Welkom (uitnodiging) | draft | 844 |
| `set-password.png` | Wachtwoord instellen (mag) | draft | 844 |
| `browser-unsupported.png` | Browser te oud | draft | 844 |

### Rounds

| Image | Canvas title | Status | Height (CSS px) |
| --- | --- | --- | --- |
| `start.png` | Start | draft | 844 |
| `start-empty.png` | Start — geen loopmaatje nodig | draft | 844 |
| `start-offline.png` | Start — offline | draft | 844 |
| `no-connection.png` | Geen verbinding | draft | 844 |
| `update-available.png` | Nieuwe versie beschikbaar | draft | 844 |
| `error.png` | Er ging iets mis | draft | 844 |
| `calendar.png` | Kalender | draft | 844 |
| `calendar-list.png` | Kalender — lijst | draft | 1040 (scrolls) |
| `calendar-past.png` | Kalender — vorige week | draft | 960 (scrolls) |
| `round-past.png` | Afgelopen ronde | draft | 844 |
| `plan-round.png` | Ronde plannen | draft | 844 |
| `form-error.png` | Ronde plannen — fout | draft | 844 |
| `round-buddy-needed.png` | Ronde — loopmaatje nodig | draft | 844 |
| `round-open-spots.png` | Ronde — genoeg lopers, je kunt mee | draft | 844 |
| `round-just-full.png` | Ronde — net vol | draft | 844 |
| `round-with-others.png` | Ronde — jij en andere loper(s) | draft | 844 |
| `round-alone.png` | Ronde — alleen jij | draft | 844 |
| `change-round.png` | Ronde wijzigen | draft | 844 |
| `round-full.png` | Ronde — vol | draft | 844 |
| `not-found.png` | Ronde niet gevonden | draft | 844 |
| `no-access.png` | Geen toegang | draft | 844 |
| `add-comment.png` | Opmerking toevoegen | draft | 844 |
| `confirm-withdrawal.png` | Afmelden bevestigen | draft | 844 |
| `confirm-withdrawal-last.png` | Afmelden als enige loper | draft | 844 |
| `report-result.png` | Resultaat melden | draft | 844 |
| `round-not-walked.png` | Ronde ging niet door | draft | 844 |
| `my-rounds.png` | Mijn rondes | draft | 844 |
| `my-rounds-empty.png` | Mijn rondes — leeg | draft | 844 |

### More

| Image | Canvas title | Status | Height (CSS px) |
| --- | --- | --- | --- |
| `more.png` | Meer | draft | 844 |
| `my-details.png` | Mijn gegevens | draft | 920 (scrolls) |
| `download-data.png` | Mijn gegevens downloaden | draft | 844 |
| `confirm-identity.png` | Bevestig dat jij het bent | draft | 844 |
| `my-activity.png` | Mijn activiteit | draft | 844 |
| `integrations.png` | Koppelingen | draft | 844 |
| `integration-created.png` | Koppeling gemaakt | draft | 844 |
| `notifications.png` | Meldingen | draft | 844 |
| `walkers.png` | Lopers | draft | 844 |
| `help.png` | Hulp en spoed | draft | 844 |
| `privacy.png` | Privacyverklaring | later | 1240 (scrolls) |
| `handbook.png` | Handleiding voor lopers | later | 844 |
| `under-construction.png` | In aanbouw | draft | 844 |

## Adding an approved screen

1. Set the canvas title to "· goedgekeurd <date>".
2. Export the screen from the canvas as PNG at 2x, at its full height.
3. Put the image in this folder under the name from the table, check it is 780 px wide and twice the height in the
   table, and set the status in the table to "approved <date>".

The prompts for the canvas and for Claude Code are B and D in [the phase 5 plan](../../../plans/phase-5.md) (T2).
