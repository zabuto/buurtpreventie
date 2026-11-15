# Aan de slag

Van een lege machine naar een draaiende lokale omgeving en een eerste wijziging.

## Vereisten

- PHP 8.4.27 of hoger, of PHP 8.5. Oudere 8.4-versies hebben een kritieke kwetsbaarheid
  (PHP2Shell); Composer weigert de app daarop te starten. Zie [gotchas.md](gotchas.md).
- Composer 2.
- Git.
- Optioneel: de [Symfony CLI](https://symfony.com/download) als lokale webserver.

Een database is in deze fase niet nodig.

## Installeren

```bash
git clone git@github.com:zabuto/buurtpreventie.git
cd buurtpreventie
git switch develop
composer install
```

Lokale instellingen en geheimen horen in `.env.local`, dat niet in Git komt. Zie
[configuration.md](configuration.md).

## Starten

Met de Symfony CLI:

```bash
symfony server:start
```

Zonder Symfony CLI:

```bash
php -S localhost:8000 -t public
```

Controleer daarna `http://localhost:8000/api/v1/health` (de Symfony CLI toont zelf het adres). Het
antwoord is `{"status":"ok"}`.

## Testen en controleren

| Commando | Wat het doet |
| --- | --- |
| `composer test` | Alle tests |
| `composer check` | Alles wat CI controleert: Composer, beveiligingsadviezen, container, deprecations, codestijl, Rector, PHPStan met de architectuurregels, tests |
| `composer fix` | Past Rector en PHP-CS-Fixer toe |

Wat elke controle doet: [testing.md](testing.md).

## Je eerste wijziging

1. Lees [architecture.md](architecture.md): de mapindeling, het uitgewerkte voorbeeld en de regels.
2. Begrippen zoals ronde, loopmaatje en terugmelding staan met hun codenaam in
   [glossary.md](glossary.md).
3. Volg het recept [Nieuwe functie toevoegen](architecture.md#nieuwe-functie-toevoegen).
4. Werk op een branch `feature/<korte-naam>` vanaf `develop` en lever via een pull request op.
   `composer check` moet groen zijn.
