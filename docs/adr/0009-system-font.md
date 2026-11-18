# ADR 0009: the system font in the app, Inter in the canvas

- Status: accepted
- Date: 2026-11-18

## Context

The screens are designed in a canvas in Inter, loaded from Google Fonts. The content security policy of the frontend
allows its own origin only (`default-src 'self'` and `style-src 'self'`, so fonts as well). The app shell, Lit
included, stays under 100 KB compressed, and the PWA works offline from the first visit (from iOS 16.4; on iOS 15 it
runs online only). Walkers use their own phones: mostly iPhones and Android phones, often older ones, often with the
text size turned up. The app runs on iPhone, Android, Windows and the Mac.

| Option for the app | For | Against |
| --- | --- | --- |
| Inter from Google Fonts | The same look everywhere | Every visit connects to Google and hands it the walker's IP address; without consent that breaches the GDPR (LG München I, 20 January 2022). An outside dependency the CSP would have to allow. |
| Inter served by the app | The same look everywhere, no third party | Tens to hundreds of KB depending on the weights, against the 100 KB budget of the shell; it must be cached before the first offline use; a swap of font on a slow connection. |
| **The system font** | No download, no third party, works offline at once, familiar on every device | The look differs per platform; lines may break differently. |

## Decision

**The app** uses the font of the device, in every screen:

```css
font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
```

It loads no font from elsewhere. Sizes, weights, line heights and colours stay as designed.

**The canvas** uses one neutral font, Inter from Google Fonts, with the same system fonts as fallback:

```css
font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
```

- **Verifiable:** the canvas shows the same font on every computer, so an approved screen and its export look the same
  wherever they are reviewed or exported. With the system font the image depends on the machine that exported it.
- **Close to the main target:** Inter follows the shapes and proportions of San Francisco, the font of the iPhone.
- **Google Fonts in the canvas only:** it reaches whoever works on the design, never a walker.

| Platform | Font in the app | Difference from the design in Inter |
| --- | --- | --- |
| iPhone, iPad, Mac | San Francisco | Small: similar shapes and widths. |
| Android | Roboto, or the manufacturer's font | Largest: Roboto is narrower, and Samsung and other makers ship their own font or let the user pick one. |
| Windows | Segoe UI | Moderate: slightly narrower. |

Android carries the most risk of a different look, because the font is least fixed there. The larger risk of text
running over an edge is enlarged text, on every device: Chrome on Android takes the text size from the device
settings, and older and less experienced walkers often turn it up.

## Consequences

- **Design images** in `docs/design/` are in Inter. In the app a line may break differently from the image, least on
  Apple devices; sizes, colours and order may not differ.
- **Room around text:** no fixed widths around text, buttons that grow in height, labels that may wrap to two lines.
  No text has to fit exactly on one line; where a design suggests it, such as the calendar blocks, the text stays
  short and may wrap.
- **Checks** (phase 5 plan, decision 29): the browser tests run in Chromium, WebKit and Firefox at 320, 360 and
  390 px, fail on horizontal scrolling at 320 px (WCAG 1.4.10) and on text cut off at 200 % (WCAG 1.4.4). There are
  no screenshot comparisons against the design images: the fonts differ by design, so such a comparison fails on
  every line break.
- **Fonts in CI:** none are installed. The default sans-serif on the Ubuntu runners is DejaVu Sans, wider than every
  font above, which makes an overflow test there stricter than on a phone. A stand-in such as Liberation Sans for
  Android and Windows, or Inter for Apple, was rejected: it needs a font download or a fontconfig alias, and still
  does not show what a phone shows.
- **Real devices**, checked by the association before it switches, not by the developer: an Android phone with the
  text size at its maximum, preferably a Samsung, and an iPhone with Safari text at 200 % (the aA menu). CI does not
  replace that check.
