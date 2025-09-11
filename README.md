# Actieve buurtpreventie

Met deze applicatie kunnen geregistreerde leden van de actieve buurtpreventie het lopen door de buurt inroosteren.
Ook is het mogelijk om achteraf het resultaat en eventuele bijzonderheden te registreren.

## Lokale installatie

Installeer eerst de Symfony CLI tool: https://symfony.com/download

### Configuratie

Alle instellingen voor de applicatie staan in configuratiebestanden. Voor meer informatie
zie: https://symfony.com/doc/current/best_practices.html#configuration

De configuratie dient opgegeven te worden in een `.env.local` bestand.

    APP_SECRET="$ecretf0rD3v3l0pm3nt"
    DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/db_name?serverVersion=8.0.37"
    MAILER_DSN="smtp://user:pass@smtp.example.com:port"
    APPLICATION_NAME="Buurtpreventie"
    APPLICATION_EMAIL="noreply@example.com"
    APPLICATION_WALKER_MINIMUM=2

### Setup

Draai onderstaande console commands:

```bash
symfony composer install
symfony console doctrine:database:create
symfony console doctrine:schema:create
symfony console doctrine:fixtures:load -q
```

### Webserver

De lokale webserver kan via de CLI tool gestart worden (argument -d voor daemonized):

```bash
symfony server:start -d
 ```

## Database bijwerken

Het bijwerken van een bestaande database kan via beschikbare migrations:

```bash
symfony console doctrine:migrations:migrate
 ```

Het forceren van een nieuwe structuur is als volgt mogelijk:

```bash
symfony console doctrine:schema:update --force
```

## Configuratie voor productie

Het onderstaande commando kan worden gebruikt voor het compileren van .env files voor productie:

```bash
symfony composer dump-env prod
```

## Development

De codebase kan via [PHPStan](https://phpstan.org) gescant worden om te zoeken naar zowel voor de hand liggende als
lastige bugs.

```bash
vendor/bin/phpstan analyse src tests
```

[Rector](https://getrector.com) is een tool die gebruikt kan worden om direct een upgrade of geautomatiseerde
refactoring uit te voeren.

```bash
vendor/bin/rector
```
