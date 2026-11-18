# ADR 0009: the system font instead of Inter

- Status: accepted
- Date: 2026-11-18

## Context

The screens were designed in Inter, loaded from Google Fonts. The content security policy of the frontend allows its
own origin only (`default-src 'self'` and `style-src 'self'`, so fonts as well). The app shell, Lit included, stays
under 100 KB compressed, and the PWA has to work offline from the first visit. Walkers use their own phones: mostly iPhones and
Android phones, often older ones, often with the text size turned up.

| Option | For | Against |
| --- | --- | --- |
| Inter from Google Fonts | The same look everywhere | Every visit connects to Google and hands it the walker's IP address; without consent that breaches the GDPR (LG München I, 20 January 2022). An outside dependency the CSP would have to allow. |
| Inter served by the app | The same look everywhere, no third party | Tens to hundreds of KB depending on the weights, against the 100 KB budget of the shell; it must be cached before the first offline use; a swap of font on a slow connection. |
| **The system font** | No download, no third party, works offline at once, familiar on every device | The look differs per platform; lines may break differently. |

## Decision

The frontend uses the font of the device, in every screen:

```css
font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
```

Sizes, weights, line heights and colours stay as designed. The canvas uses the same font stack, without Google
Fonts. Screens are reviewed, approved and exported on a Mac, so every design image shows San Francisco, the font an
iPhone shows.

| Platform | Font | Difference from Inter |
| --- | --- | --- |
| iPhone, iPad, Mac | San Francisco | Small: Inter follows the shapes and widths of San Francisco. |
| Android | Roboto, or the manufacturer's font | Largest: Roboto is narrower, and Samsung and other makers ship their own font or let the user pick one. |
| Windows | Segoe UI | Moderate: slightly narrower. |

Android carries the most risk, because the font is least fixed there. The larger risk on every device is enlarged
text: Chrome on Android takes the text size from the device settings, and older and less experienced walkers often
turn it up.

## Consequences

- **Design images** in `docs/design/` are in San Francisco. On Android and Windows a line may break differently from
  the image; sizes, colours and order may not.
- **Room around text**: no fixed widths around text, buttons that grow in height, labels that may wrap to two lines.
  No text has to fit exactly on one line; where a design suggests it, such as the calendar blocks, the text stays
  short and may wrap.
- **Checks** (phase 5 plan, decisions 29 and 30): the browser tests run in Chromium and WebKit at 320, 360 and 390 px,
  fail on horizontal scrolling at 320 px and with text at 200 % (WCAG 1.4.4 and 1.4.10), and nothing may be cut off.
  The fonts in CI are not those of a phone: the default sans-serif on the Ubuntu runners is DejaVu Sans, wider than
  every system font above, which makes an overflow test there stricter than on a phone. CI therefore installs no
  fonts.
- **Real devices** before an association switches: an Android phone with the text size at its maximum, preferably a
  Samsung, and an iPhone with Safari text at 200 % (the aA menu). CI does not replace that check.
