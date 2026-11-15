# Configuration

Technical settings are environment variables. Settings an association changes itself (name, logo,
texts) live in the database and the admin screens; those arrive in phase 3.

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
| `APP_SECRET` | Key for signed values such as CSRF tokens. Unique and secret per installation | required in production, in `shared/.env.local` |
| `DATABASE_URL` | Database connection, from phase 1 on. Locally the Symfony CLI sets it from the `database` container in `compose.yaml`; on a server it goes in `shared/.env.local` | none |
| `DEFAULT_URI` | Base URL of the app for links outside a web request, such as in e-mails and from the console. A subdomain (`https://buurtpreventie.example.org`) or a path (`https://example.org/buurtpreventie`), without `/public` | `http://localhost` (in `config/services.yaml`) |
