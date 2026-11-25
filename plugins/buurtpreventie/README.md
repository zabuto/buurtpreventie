# Buurtpreventie plugin

Plan neighbourhood watch rounds from Claude Code: find rounds that need a buddy, sign up or withdraw, plan a round,
report back after a round, and give counts for coordinators and analysts. The plugin connects to the MCP server of
your own Buurtpreventie app and adds a skill on when and how to use its tools.

## Before you install

- Your association runs the Buurtpreventie app and an admin has switched its MCP server on (`mcpAccess`).
- You have an API token from the app, under your account. Claude Code asks for it and for the address of the app at
  install, and keeps the token in the keychain of your computer.

```text
/plugin install buurtpreventie --marketplace zabuto/buurtpreventie
```

The full steps, also for Cursor and GitHub Copilot, are in the
[user guide](https://github.com/zabuto/buurtpreventie/blob/main/docs/user-guide/mcp.md).

## Check before you confirm

An AI client can get things wrong. What it signs you up for, withdraws or reports is done in your name, so read what
it proposes before you say yes. What it reads goes to the company behind the AI client, under that company's terms.

## Privacy

The plugin holds no data and sends nothing itself: Claude Code talks to the app of your association only. That
association's privacy statement applies, and the
[Privacy](https://github.com/zabuto/buurtpreventie/blob/main/docs/user-guide/mcp.md#privacy) part of the user guide lists
what the AI client gets.

## Licence

PolyForm Noncommercial 1.0.0, see [LICENSE](https://github.com/zabuto/buurtpreventie/blob/main/LICENSE): free for
non-commercial use. The plugin and the app come as is, without warranty or support.

## In het Nederlands

Met deze plugin plan je buurtpreventierondes vanuit Claude Code, via de MCP-server van de app van je eigen
vereniging. Je hebt een API-token uit de app nodig, en de beheerder moet MCP aanzetten. Een AI-app kan fouten maken:
wat hij voor je aanmeldt, afmeldt of terugmeldt, gebeurt op jouw naam. Lees dus eerst wat hij voorstelt en zeg dan
pas ja. Wat de AI-app leest, gaat naar het bedrijf achter die app. De plugin is gratis voor niet-commercieel gebruik
en wordt geleverd zoals hij is, zonder garantie.
