# Beheer buiten de code

Wat er naast de code bestaat en wie wat bijhoudt. Hosting, cron, releases en back-ups volgen in fase 6.

## GitHub-repository

- Repository: `zabuto/buurtpreventie`. Eigenaar is de oorspronkelijke ontwikkelaar; overdracht gaat via
  een fork (zie Overdraagbaarheid in [plan-v4.md](plan-v4.md)).
- Branches: `develop` is de standaardbranch tot v4.0.0; daar komt het werk binnen via pull requests
  vanaf `feature/<korte-naam>`. `main` bevat alleen uitgebrachte versies met een tag `vX.Y.Z`.
  Spoedfixes gaan via `hotfix/<korte-naam>` vanaf `main`, terug naar `main` en `develop`.
- De voorganger staat in `zabuto/buurtpreventie-v3` (MIT, alleen-lezen).

### Instellingen die met de hand gezet worden

Deze staan niet in bestanden en moeten bij een fork opnieuw:

| Instelling | Waar | Waarom |
| --- | --- | --- |
| Branch protection op `develop`: pull request verplicht, check "Checks (PHP 8.4)" verplicht | Settings → Branches | Automatisch samenvoegen wacht anders niet op groene CI |
| Allow auto-merge | Settings → General | Nodig voor het samenvoegen van patch-updates van Dependabot |
| Private vulnerability reporting | Settings → Code security | Meldingen volgens `SECURITY.md` |
| Dependabot alerts en security updates | Settings → Code security | Beveiligingsupdates voor Composer en Actions |
| Automatically delete head branches | Settings → General | Een branch verdwijnt van GitHub zodra zijn pull request is gemerged |

## CI

| Workflow | Wanneer | Wat |
| --- | --- | --- |
| `ci.yml` | Elke pull request, elke push naar `develop` en `main` | `composer check` op PHP 8.4; zie [testing.md](testing.md) |
| `weekly.yml`, job audit | Maandag 05:00 UTC en met de hand | `composer audit` op `develop`; bij een advies een issue |
| `weekly.yml`, job look-ahead | Idem | Nieuwste Symfony 8 op PHP 8.5, alle controles; bij een fout een issue. Blokkeert niets |
| `dependabot-automerge.yml` | Pull requests van Dependabot | Patch-updates samenvoegen zodra CI groen is |

De weekjobs openen per soort één issue en voegen bij een volgende fout een reactie toe aan dezelfde
issue. Actions staan vast op een commit-SHA; Dependabot werkt ze bij.

## Dependabot

`.github/dependabot.yml`: wekelijks Composer en GitHub Actions, gericht op `develop`. Patch-updates
van Composer komen samen in één pull request en worden automatisch samengevoegd als CI groen is;
minor- en major-updates beoordeelt de ontwikkelaar. Beveiligingsupdates gaan naar de standaardbranch.

Major-updates van Symfony-pakketten (`symfony/*`) slaat Dependabot over. v4 blijft op 7.4 LTS tot de
geplande overstap naar 8.4 LTS; een losse Symfony 8-versie zoals 8.1 krijgt maar een paar maanden
onderhoud, en één pakket op 8 naast de rest op 7.4 ondersteunt Symfony niet. Wat er op Symfony 8
breekt, laat de look-ahead-job zien.

## Geheimen

Geheimen staan nooit in Git. Overzicht:

| Geheim | Waar | Sinds |
| --- | --- | --- |
| `APP_SECRET` | `.env.local` lokaal, `shared/.env.local` op de server | fase 0 |

Bij elke nieuwe sleutel (database, mail, tick, ondertekening van releases) komt hier een regel bij.

## PHP-versie

Minimaal PHP 8.4.27. PHP 8.4.0 tot en met 8.4.26 hebben PHP2Shell (CVE-2026-109618 en
CVE-2026-109619): remote code execution zonder inloggen, misbruikt in het wild. `composer.json` eist
daarom `>=8.4.27`, en Composer's runtime-controle laat de app op een oudere versie niet starten. Bij een
nieuwe kritieke PHP-kwetsbaarheid gaat dit minimum omhoog in `require.php` en `config.platform.php`.

## Symfony 8.4 LTS

v4 draait op Symfony 7.4 LTS (bugfixes tot november 2028, security tot november 2029). De overstap
naar 8.4 LTS staat gepland voor begin 2028, samen met PHP 8.5; de stappen staan in
[plan-v4.md](plan-v4.md#overstap-naar-symfony-84-lts). De look-ahead-job laat elke week zien wat er
op Symfony 8 breekt.
