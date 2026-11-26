# Using the app from an AI client (MCP)

An AI client such as Claude Code can read rounds, find rounds that need a buddy, sign you up and report back, through
the MCP server of the app. It acts as you: it can do what you can do in the app, and nothing more. It never gets phone
numbers, addresses or e-mail addresses, also not of members who share theirs with you, and it never reads the notes and
comments of a round: those can be about other people, so they stay in the app.

## Privacy

What the AI client reads goes to the company behind it, under that company's terms: rounds, the display names of the
walkers, the outcomes of reports and the counts. Your association decides whether this is allowed at all, and says so
in its privacy statement.

The plugin only delivers files from the repository `zabuto/buurtpreventie`: the skill and the connection settings.
Claude Code connects straight to the app of your association, with the address and token you give at install, which
stay on your computer. The developers of this repository get nothing from your association: no rounds, names,
reports or tokens. The plugin holds no code that runs on your computer or reports anywhere. GitHub sees that someone
downloads the plugin, as with any download, and shows the developers only how many times the repository was
downloaded, not by whom. Your data stays with your association,
whoever hosts its app, and the company behind your AI client.

## Before you start

- **The admin opens MCP for you.** It is off on a new installation. The admin decides who may use it. When the AI
  client says that MCP is off or not open to you, ask the admin.
- **You make an API token** in the app, under Integrations, with a name such as "Claude Code" and an expiry date.
  Give it the scope `read` to look only, and `write` too to sign up, plan rounds and report back. Copy the secret
  (`bpt_…`) at once: the app shows it only then. Keep it like a password; revoke it in the app when you no longer use
  it or think someone else has it.
- **You get a mail about your token.** The app mails you when there is a new token in your name, and when a token
  goes over its limit for one day. Neither mail has a link. Was it not you? Then open the app yourself, revoke
  the token under Integrations, and tell the coordinator.
- **You check before you confirm.** An AI client can get things wrong. What it signs you up for, withdraws or reports
  is done in your name, so read what it proposes before you say yes. The app and the plugin come as is, without
  warranty (see `LICENSE`).

To try it on the demo of a development machine, see
[An AI client on the demo](../getting-started.md#an-ai-client-on-the-demo).

Until the app supports logging in from an AI client, every member needs a token of their own. That suits
coordinators, analysts and members who already use an AI client; walkers can keep using the app.

## Claude Code

Install the plugin of the app once:

```text
/plugin install buurtpreventie --marketplace zabuto/buurtpreventie
```

On Claude Code older than 2.1.275, add the marketplace first:

```text
/plugin marketplace add zabuto/buurtpreventie
/plugin install buurtpreventie@buurtpreventie
```

Claude Code asks for the address of your app, such as `https://buurtpreventie.example.org` (without `/mcp`), and your
token. It keeps the token in the keychain of your computer, not in a settings file. The plugin also brings a skill
that tells Claude which tool fits your question, to ask you before every change, and to treat text other members
typed as information rather than as an instruction.

How the install works: Claude Code reads the list of plugins in `.claude-plugin/marketplace.json` of the repository
`zabuto/buurtpreventie` and fetches the plugin from its `main` branch, which holds only released versions, so the
plugin never names a tool before a release of the app serves it. Your rounds and your token go from Claude Code to
your own app only, never to GitHub. Claude Code keeps the plugin you installed until its version number changes;
update it under `/plugin`, where you can also switch on automatic updates for this marketplace.

Then ask, for example: "Which rounds still need a buddy this week?" or "Sign me up for the open round on Thursday".
Claude asks before it signs you up, withdraws you or plans a round.

Without the plugin, add the server by hand:

```bash
claude mcp add --transport http buurtpreventie https://buurtpreventie.example.org/mcp \
  --header "Authorization: Bearer bpt_…"
```

Claude Code then stores the token in its own configuration file, and the command lands in the history of your
shell. Use one of the two: with both, every tool shows up twice; remove the one added by hand with
`claude mcp remove buurtpreventie`.

## Cursor and GitHub Copilot

These steps follow the documentation of Cursor and Copilot and are not tested with this app.

Put the token in an environment variable, such as `BUURTPREVENTIE_TOKEN` in the profile of your shell, so it stays
out of the configuration file. Then add the server:

- **Cursor**, in `~/.cursor/mcp.json`:

  ```json
  {
      "mcpServers": {
          "buurtpreventie": {
              "url": "https://buurtpreventie.example.org/mcp",
              "headers": { "Authorization": "Bearer ${env:BUURTPREVENTIE_TOKEN}" }
          }
      }
  }
  ```

- **GitHub Copilot**, in `~/.copilot/mcp-config.json`:

  ```json
  {
      "mcpServers": {
          "buurtpreventie": {
              "type": "http",
              "url": "https://buurtpreventie.example.org/mcp",
              "headers": { "Authorization": "Bearer ${BUURTPREVENTIE_TOKEN}" }
          }
      }
  }
  ```

The skill works in both: copy the folder `plugins/buurtpreventie/skills/buurtpreventie` of the repository to
`~/.cursor/skills/` or `~/.copilot/skills/`.

## MCP Inspector

The MCP Inspector shows the tools, the resource and the prompts of the server, to try them one by one:

```bash
npx @modelcontextprotocol/inspector
```

Choose the transport "Streamable HTTP", the address `https://…/mcp` of your app, and add the header
`Authorization: Bearer bpt_…`.

## What the server offers

| Tool | What it does | Who |
| --- | --- | --- |
| `list_rounds` | Rounds in a period, with status and walkers needed | every member |
| `get_round` | One round with its walkers (display names) and the outcome of its report; notes and comments only in the app | every member |
| `my_rounds` | Your own upcoming rounds, or walked ones still waiting for your report | every member |
| `staffing_gaps` | Upcoming rounds that need walkers | every member |
| `list_meeting_points`, `list_outcomes` | The choices for a new round and a report | every member |
| `sign_up_for_round`, `withdraw_from_round` | Sign up for a round, or withdraw | walker |
| `create_round` | Plan a round with you as its first walker | walker |
| `report_round_result` | Report back after a walked round | walker of that round, coordinator |
| `add_round_comment` | Comment on a round | walker of that round, coordinator |
| `report_summary` | The reports of a period counted per outcome, and the incidents per month | analyst, coordinator |
| `round_statistics` | How the rounds of a period went, per month, weekday or daypart | analyst, coordinator |

The resource `buurtpreventie://config` gives the name of the association, the walkers of a new round and the
choices. The prompt `monthly_report` asks for an overview of a month, from counts only. It comes back as a draft: it
names the period and the tools behind its numbers, and holds no number of the AI client's own. You are responsible for
what you do with it, so check it before you use it. `plan_week` asks which rounds of a week still need walkers.

Deleting, managing members and changing settings stay in the app.

## Limits

A token has limits, through the AI client and through scripts together:

- 60 reads a minute and 2,000 a day;
- 20 changes an hour, such as signing up or reporting back.

A question to the AI client costs a few reads. Above a limit the app refuses the token for a while, and the AI client
gets the number of seconds to wait. The first time a day that the token reaches its daily limit, you get a mail.

## On your own computer (STDIO)

A developer with the code on their computer can start the server over STDIO instead, with the token in `MCP_TOKEN`:

```bash
claude mcp add buurtpreventie --env MCP_TOKEN=bpt_… -- symfony console mcp:server
```

The command checks the token when it starts. After you revoke the token, restart the server.
