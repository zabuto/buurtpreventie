# Configuration

Technical settings are environment variables. Settings an association changes itself (name, colours,
contact, texts) live in the database; the admin reads them with `GET /api/v1/admin/settings` and changes them with
`PATCH /api/v1/admin/settings`, a JSON Merge Patch where null restores the default.

## Files

| File | In Git | Contents |
| --- | --- | --- |
| `.env` | yes | Defaults without secrets |
| `.env.dev`, `.env.test` | yes | Defaults per environment, for development and tests only |
| `.env.local` | no | Settings and secrets of this machine or server |
| `shared/.env.local` | no | Settings and secrets of an installation on a server (phase 6) |

Symfony reads them in this order; a later value wins. Real secrets live only in `.env.local` or
`shared/.env.local`, never in a file in Git.

`compose.yaml` takes `MARIADB_VERSION`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD` and
`MYSQL_ROOT_PASSWORD` from the shell; the defaults (`10.11`, `buurtpreventie`, `app`, `app`,
`root`) are for development only.

## Variables

| Variable | Purpose | Default |
| --- | --- | --- |
| `APP_ENV` | Environment: `dev`, `test` or `prod` | `dev` |
| `APP_SECRET` | Key for signed values such as CSRF tokens. Unique and secret per installation, and always generated, never typed: the installer writes it; locally `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` makes one for `.env.local` | required in production, in `shared/.env.local` |
| `DATABASE_URL` | Database connection. Locally the Symfony CLI sets it from the `database` container in `compose.yaml`; on a server it goes in `shared/.env.local`, such as `mysql://db_user:db_password@localhost:3306/db_name?charset=utf8mb4` for MariaDB and MySQL alike. URL-encode special characters in the password (`@` becomes `%40`, `:` `%3A`, `/` `%2F`, `#` `%23`, `%` `%25`), or the connection fails with a misleading error. Leave out `serverVersion`: hosts upgrade without notice, and a stale version makes Doctrine generate SQL for the wrong platform; without it, DBAL reads the version when it connects. Tests add `_test` to the database name | none; in `test`, an unreachable address in `.env.test`, so the kernel boots and the database tests skip |
| `MAILER_DSN` | How mail leaves the app, such as `smtp://user:password@smtp.example.org:587` on a server. Locally the Symfony CLI sets it from the `mailer` container (Mailpit) in `compose.yaml` | `null://null`, which sends nothing; always in `test` |
| `MAILER_FROM` | Sender of every mail, an address the mail server may send from | `noreply@localhost` (in `config/services.yaml`) |
| `HSTS_MAX_AGE` | Seconds a browser keeps to HTTPS for this host after an API answer over HTTPS (`Strict-Transport-Security`), such as `31536000` for a year. Opt-in per installation: set it only once HTTPS works there, because a browser that has it refuses the site over HTTP until the time runs out, also when the certificate is lost. Covers this host only, not other subdomains of the domain | `0`, which sends no HSTS (in `config/services.yaml`) |
| `UPLOAD_DIR` | Absolute path of the folder for uploaded files, outside the web folder: the logo and icon go in `brand/` under it. The PHP user writes there, and a backup includes it | `var/uploads` in the project (in `config/services.yaml`) |
| `DEFAULT_URI` | Base URL of the app for links outside a web request, such as in e-mails and from the console. A subdomain (`https://buurtpreventie.example.org`) or a path (`https://example.org/buurtpreventie`), without `/public`; a trailing slash makes no difference. Anything but an http or https address with a host and at most a path is an error as soon as the app sends a message | `http://localhost` (in `config/services.yaml`) |

## Settings of the association

Every setting, by its name in the API. The catalogue is `Settings\Domain\SettingName`; a value is stored once the
admin sets it, also when it equals the default, and null removes it again.

| Setting | Takes | Default |
| --- | --- | --- |
| `associationName` | Text, at most 100 characters | Buurtpreventie |
| `associationShortName` | Text under the icon on a home screen, at most 30 characters | Buurtpreventie |
| `primaryColor`, `accentColor` | `#rrggbb`; at least 4.5:1 and 3:1 against white | `#1f5f8b`, `#c2410c` |
| `primaryDarkColor`, `accentDarkColor` | `#rrggbb`; at least 4.5:1 and 3:1 against `#121212`, the dark background | `#8cc4e8`, `#fb923c` |
| `contactName`, `contactEmail`, `contactPhone` | The coordinator members can reach: at most 100 characters, an e-mail address, a phone number | none |
| `groupUrl` | An invitation to a group in WhatsApp (`https://chat.whatsapp.com/…`) or Signal (`https://signal.group/#…`) | none |
| `mailSenderName` | The name mails come from, at most 100 characters; the address stays `MAILER_FROM` | the name of the association |
| `welcomeTextNl`, `welcomeTextEn`, `emergencyTextNl`, `emergencyTextEn` | Plain text, at most 2,000 characters; an empty line starts a paragraph | the standard text in `translations/settings.<locale>.yaml` |
| `privacyUrl`, `accessibilityUrl` | An https address of a page of the association | none |
| `minimumWalkers`, `maximumWalkers` | 1 to 20 and 1 to 50, the maximum at least the minimum; for new rounds only | 2 and 7 |
| `reminderDaysAhead` | 1 to 7 | 1 |
| `language` | `nl` or `en` | `nl` |
| `apiAccess` | `true` or `false`: whether API tokens give access | `true` |

A colour is checked against the other colours and a minimum against the maximum as one set, so a refused value
leaves every value of the patch unstored. The audit log names each setting that changed with its old and new value,
except contact details, the group link and texts, which it names without a value.

### Logo and icon

The admin uploads them with `PUT /api/v1/admin/settings/images/logo` and `/icon`, the file itself as the body, and
puts the default back with `DELETE`. What a file is follows from its content alone: name, extension and
`Content-Type` count for nothing. A refused file is the 422 problem `unsupported_image` with the `condition` it breaks.

| Condition | Logo | Icon |
| --- | --- | --- |
| `format` | PNG or JPEG | PNG or JPEG |
| `file_size` | at most 2 MB | at most 2 MB |
| `too_large`, `not_square`, `too_small` | at most 2048 × 2048 pixels, any proportion | square, from 512 × 512 to 2048 × 2048 pixels |
| Stored as | PNG within 512 × 512 (screen) and within 512 × 128 (mail) | PNG of 512, 192, 180 (Apple) and 32 (favicon) pixels |

Every size is drawn afresh with GD, so only the pixels stay: no metadata such as the place a photo was taken, and
nothing hidden after the image. A JPEG keeps the orientation of its pixels; GD ignores the rotation a phone notes
in its metadata. The files are named `<kind>-<size>-<version>.png`, the version from the uploaded content, and
`GET /api/v1/media/brand/<file>` serves, to anyone, only a file of the version a setting names, cached for a year.
The files of the previous image go once the change is committed.

## Fixed limits

Values in the code that no setting changes. A new limit gets a row here.

### Rounds and messages

| Limit | Value | Where |
| --- | --- | --- |
| Local clock | `Europe/Amsterdam` for dayparts, calendar days, reminders and buddy calls; stored instants are UTC | `LocalTime::ZONE` |
| Default walkers of a new round | Minimum 2, maximum 7, until an admin changes the settings `minimumWalkers` and `maximumWalkers` | `StaffingDefaults` |
| Walkers of one round | Minimum at least 1, maximum at least the minimum; fixed when the round is created | `StaffingRule` |
| Dayparts | `morning` before 12:00, `afternoon` before 18:00, `evening` from 18:00 | `Daypart` |
| Start of a round | After the moment it is created or moved; no change once started | `RoundStart`, `Round::change()` |
| Round and report lists | A period that ends after its start and lasts at most 93 days: a quarter in local time, the hour the clocks go back included | `ListingPeriod` |
| Own rounds | 50 by default, at most 200 per answer (`limit`) | `ListMyRounds` |
| Buddy call | Once per round, at the first of 5, 3 or 1 days before the start (local clock) that falls after the round was created; at once for a round created less than a day ahead | `BuddyCall::LEAD_DAYS` |
| Reminder | One message per active walker per local calendar day, for all their rounds of that day | `Reminders` |

### Field lengths

The input of a use case refuses a longer value; the column holds at least as much.

| Field | Maximum (characters) |
| --- | --- |
| E-mail address | 180 |
| Full name, display name | 100 |
| Phone number | 30, with at least 6 digits; digits, spaces, brackets, hyphens and a leading `+` only |
| Address | 255 |
| Search term for members | 100 |
| Name of a meeting point or outcome | 100 |
| Start note of a round, reason for a cancellation | 500 |
| Comment | 2,000 |
| Note of a round report | 5,000 |
| Name of an API token | 100 |
| Password | at least 15 characters (NIST SP 800-63B for a password as the only factor), at most 4,096 bytes; any kind of character |

### Other values

| Limit | Value | Where |
| --- | --- | --- |
| Coordinates | Latitude −90 to 90, longitude −180 to 180, six decimals (about 10 cm) | `Coordinates`, `DECIMAL(9,6)` |
| API token secret | `bpt_` and 64 hexadecimal characters from 32 random bytes, stored as SHA-256; any other value is refused | `ApiToken`, `RandomSecretGenerator` |
| API token scopes | At least one of `read`, `write` and `admin` | `CreateApiToken`, `TokenScope` |
| API token expiry | Always, after the moment of creation and at most 365 days later | `ApiToken::MAXIMUM_LIFETIME_IN_DAYS` |
| Session | Cookie `bp_session`: `Secure`, `HttpOnly`, `SameSite=Lax`; files in `var/sessions/<env>/`; a new id at login | `config/packages/framework.yaml` |
| Failed logins | 5 per address (or login challenge, or invitation) and IP address in 15 minutes, 30 per IP address in an hour, then 429; a successful login clears the first count | `config/packages/rate_limiter.yaml` |
| Login code | Six digits, 60 minutes, once, at most 5 attempts; a new request ends the earlier ones | `LoginChallenge` |
| Login code requests | 3 per address and 10 per IP address in an hour, then 429 | `config/packages/rate_limiter.yaml` |
| Invitation | `bpi_` and 64 hexadecimal characters, stored as SHA-256; 48 hours, once; a new invitation ends the earlier ones | `Invitation` |
| Stay logged in | Cookie `bp_remember` for 90 days: `Secure`, `HttpOnly`, `SameSite=Lax`; ends with a new password | `config/packages/security.yaml` |
| Recent login | 15 minutes after a login or confirmation; needed for a new password, a new API token, changing roles and erasing a member. A console command run as a member counts as recent | `TokenRecentAuthentication`, `RunAs` |
| Own activity | 50 entries per page, newest first | `ListOwnActivityHandler::PAGE_SIZE` |
| Audit log of the admin | 50 entries per page, newest first; as CSV every matching entry, at most 10,000, read 500 at a time | `ListAuditEntriesHandler` |
| Audit fields | A field name containing `password`, `secret`, `token` or `hash` is refused | `AuditEntry` |
