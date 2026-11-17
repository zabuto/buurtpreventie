# Using the app from an AI client (MCP)

An AI client such as Claude Code can read rounds, find rounds that need a buddy, sign you up and report back, through
the MCP server of the app. It acts as you, with your roles: it can do what you can do in the app, and nothing more.
It never gets phone numbers, addresses or e-mail addresses, also not of members who share theirs with you, and it
never reads the notes and comments of a round: those can be about other people, so they stay in the app.

## Before you start

- **The association switches MCP on.** It is off on a new installation. An admin sets `mcpAccess` to `true` in the
  settings (`PATCH /api/v1/admin/settings`); until then `/mcp` answers 404.
- **You make an API token** in the app, under your account, with a name such as "Claude Code" and an expiry date.
  Give it the scope `read` to look only, and `write` too to sign up, plan rounds and report back. Copy the secret
  (`bpt_…`) at once: the app shows it only then. Keep it like a password; revoke it in the app when you no longer use
  it or think someone else has it.

## Claude Code

Add the server once, with the address of your app and your token:

```bash
claude mcp add --transport http buurtpreventie https://buurtpreventie.example.org/mcp \
  --header "Authorization: Bearer bpt_…"
```

Then ask, for example: "Which rounds still need a buddy this week?" or "Sign me up for the open round on Thursday".
Claude asks before it signs you up, withdraws you or plans a round.

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
choices. The prompt `monthly_report` asks for an overview of a month for the police and the municipality, from counts
only; `plan_week` asks which rounds of a week still need walkers.

Deleting, managing members and changing settings stay in the app. A token may make 60 requests a minute.

## On your own computer (STDIO)

A developer with the code on their computer can start the server over STDIO instead, with the token in `MCP_TOKEN`:

```bash
claude mcp add buurtpreventie --env MCP_TOKEN=bpt_… -- symfony console mcp:server
```

The command checks the token when it starts. After you revoke the token, restart the server.
