# Configuratie

Technische instellingen staan in omgevingsvariabelen. Instellingen die een vereniging zelf aanpast
(naam, logo, teksten) komen in de database en het beheerscherm; die volgen in fase 3.

## Bestanden

| Bestand | In Git | Inhoud |
| --- | --- | --- |
| `.env` | ja | Standaardwaarden zonder geheimen |
| `.env.dev`, `.env.test` | ja | Standaardwaarden per omgeving, alleen voor ontwikkelen en testen |
| `.env.local` | nee | Instellingen en geheimen van deze machine of server |
| `shared/.env.local` | nee | Instellingen en geheimen van een installatie op een server (fase 6) |

Symfony leest ze in deze volgorde; een latere waarde gaat voor. Echte geheimen staan alleen in
`.env.local` of `shared/.env.local`, nooit in een bestand in Git.

## Variabelen

| Variabele | Waarvoor | Standaard |
| --- | --- | --- |
| `APP_ENV` | Omgeving: `dev`, `test` of `prod` | `dev` |
| `APP_SECRET` | Sleutel voor ondertekende waarden zoals CSRF-tokens. Per installatie uniek en geheim | verplicht in productie, in `shared/.env.local` |
| `DEFAULT_URI` | Basis-URL van de app voor links buiten een webverzoek, zoals in e-mails en vanuit de console. Een subdomein (`https://buurtpreventie.example.org`) of een pad (`https://example.org/buurtpreventie`), zonder `/public` | `http://localhost` (in `config/services.yaml`) |
