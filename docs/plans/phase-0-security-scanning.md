# Fase 0, aanvulling: beveiligingsscan en cooldown

Status: ter goedkeuring.

Bron: `docs/plan-v4.md` (Fase 0, de twee open punten; Beveiligingsupdates en zelf bijwerken, Kant
ontwikkelaar; Risico's), ADR 0002 en 0003, en `AGENTS.md`.

## Doel en klaar-criterium

Een gekaapte release van een afhankelijkheid komt niet meteen binnen, en CI scant de eigen code op
gevaarlijke patronen met een open-source scanner en een eigen regelset.

Klaar als:

- Dependabot een nieuwe Composer-versie pas voorstelt na 3 dagen (patch), 7 (minor) of 14 (major) en
  een nieuwe action na 7 dagen, zonder uitzonderingen, en een test dat bewaakt;
- de CI-job "Security scan" groen is op `develop`: Opengrep, gekozen binnen de huidige major met
  cooldown, gecontroleerd met Cosign, draait de regeltests en scant de code;
- `composer update` en de wekelijkse workflow een nieuwe Opengrep-major melden zodra die 14 dagen oud
  is, en `composer update` daarbij nooit faalt;
- ADR 0004 de keuzes vastlegt en `docs/` en `CHANGELOG.md` bijgewerkt zijn;
- de pull request naar `develop` groen en gemergd is, en "Security scan" een verplichte check is.

## Vastgesteld

1. **Dependabot kent voor `github-actions` alleen `default-days`**, geen dagen per soort update
   (cooldown-tabel in de bron van de GitHub-documentatie). Composer ondersteunt beide.
2. **Opengrep draait regeltests met `opengrep test <map>`** (of `opengrep scan --test`), in het
   Semgrep-formaat met `ruleid:`- en `ok:`-annotaties. Paden sluit hij uit via `.semgrepignore` en
   `.gitignore`; een `.opengrepignore` bestaat niet.
3. **`cosign verify-blob --cert` leest het `.cert`-bestand van Opengrep rechtstreeks:** het eigen
   `install.sh` van Opengrep doet precies dat. Dat script accepteert elke workflow in de
   Opengrep-organisatie (`--certificate-identity-regexp`) en slaat de controle over zonder Cosign;
   CI hier eist de exacte identiteit van de release-workflow en installeert Cosign altijd.

## Voorgestelde beslissingen

1. **Cooldown zonder uitzonderingen**, dus zonder `include` of `exclude`:
   - Composer: `semver-patch-days: 3`, `semver-minor-days: 7`, `semver-major-days: 14`, en
     `default-days: 7` voor updates zonder semver-informatie;
   - GitHub Actions: `default-days: 7`, omdat Dependabot daar geen dagen per soort update kent.
   Beveiligingsupdates vallen er volgens GitHub niet onder.
2. **Versiekeuze en majorcontrole in één PHP-klasse**, met PHPUnit in memory te testen:
   - kiezen: de nieuwste release binnen de major die niet als prerelease gemarkeerd is, waarbij een
     patch (`vX.Y.Z` met Z > 0) minstens 3 dagen oud is en een minor (`vX.Y.0`) minstens 7;
   - melden: een release in een hogere major die minstens 14 dagen oud is.
3. **Die klasse staat in `tools/`** (namespace `App\Tools\`, alleen in `autoload-dev`), zodat hij niet
   in de release-zip komt. PHPStan, Mago en Rector krijgen `tools/` erbij. Eén script,
   `tools/opengrep-release.php`, met twee commando's: `select` geeft de versie voor CI, `check-major`
   meldt een nieuwe major.
4. **De huidige major staat in één bestand**, `tools/opengrep-major` (inhoud `1`). Overstappen naar
   een nieuwe major is een pull request die dat bestand verandert.
5. **`composer update` waarschuwt via `post-update-cmd`.** Het script vraagt GitHub alleen om de lijst
   met releases, met een time-out van 5 seconden. Geen netwerk, een fout of een time-out geeft geen
   melding en exit 0. In CI krijgt `check-major` de optie `--fail`, zodat de wekelijkse job een issue
   opent.
6. **CI-job "Security scan"** in `ci.yml`, naast "Checks (PHP 8.4)":
   - `permissions: contents: read`, `persist-credentials: false`, geen geheimen behalve het
     standaard-`GITHUB_TOKEN` voor de GitHub-API;
   - versie kiezen, dan `opengrep_manylinux_x86` met `.sig` en `.cert` downloaden uit de release van
     `opengrep/opengrep`;
   - Cosign via `sigstore/cosign-installer` (vastgepind op SHA), dan `cosign verify-blob` met
     `--certificate-identity https://github.com/opengrep/opengrep/.github/workflows/rolling-release.yml@refs/heads/release-v<versie>`
     en `--certificate-oidc-issuer https://token.actions.githubusercontent.com`;
   - versie en SHA-256 in de log;
   - eerst de regeltests, dan de scan, die faalt bij elke bevinding.
   Geen SARIF-upload: die loopt via `codeql-action`, en ADR 0003 sluit CodeQL uit.
7. **De regels staan in `.opengrep/rules/`**, per regel een YAML-bestand met een testbestand ernaast.
   Gescand worden `src/`, `config/`, `templates/`, `public/`, `bin/` en `tools/`; `vendor/` en `var/`
   vallen buiten de scan; `tests/` ook, want daar draait geen code van de app.
8. **De eerste regels**, voor wat in fase 1 en 2 komt:
   - `unserialize` op een waarde die geen letterlijke string is;
   - `eval`, `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen` en backticks;
   - SQL opgebouwd met concatenatie of interpolatie in `executeQuery`, `executeStatement`, `query` of
     `prepare` van Doctrine DBAL;
   - het filter `|raw` in Twig-templates;
   - een waarde uit `Request` als pad in `file_get_contents`, `fopen`, `unlink`, `file_put_contents`
     of `include`.
9. **Alleen in CI, niet lokaal.** `composer check` draait de scan niet; `AGENTS.md` en
   `docs/getting-started.md` zeggen dat. Lokaal scannen zou van elke ontwikkelaar een
   Cosign-installatie vragen.

## Nieuwe afhankelijkheden (goedgekeurd)

- `sigstore/cosign-installer` (Apache-2.0), als GitHub Action, vastgepind op SHA en onder de cooldown.
- De Opengrep-binary (LGPL-2.1), alleen in CI, niet via Composer.

Geen nieuwe Composer-pakketten: `symfony/yaml` (voor de Dependabot-test) is er al.

## Taken

Elke taak is één of enkele kleine commits; tests komen vóór de code.

### T1. Dependabot-cooldown (test eerst)

Test in `tests/Dependencies/`: elk blok in `.github/dependabot.yml` heeft precies de cooldown uit
beslissing 1 en geen `include` of `exclude`. Daarna de configuratie.

### T2. Versiekeuze en majorcontrole (test eerst)

Unit-tests met vaste releaselijsten en een vaste klok:

- kiest de nieuwste patch van minstens 3 dagen, en slaat een jongere over;
- een minor telt pas na 7 dagen;
- negeert prereleases en andere majors;
- geeft een fout als er binnen de major geen release in aanmerking komt;
- meldt een hogere major van minstens 14 dagen, en zwijgt bij een jongere.

Daarna de klasse en `tools/opengrep-release.php`, plus een test dat `check-major` zonder netwerk
exit 0 geeft.

### T3. Regelset (test eerst)

Per regel eerst het testbestand met gevallen die moeten afgaan en gevallen die moeten passeren, dan
de regel.

### T4. CI-job "Security scan"

Volgens beslissing 6. Controle: één run met een opzettelijke bevinding op een tijdelijke branch die
faalt, daarna groen zonder.

### T5. Wekelijkse melding en `composer update`

- `weekly.yml`: job "Opengrep major" met `check-major --fail` en `report-issue.sh`.
- `composer.json`: `post-update-cmd` met `check-major`.

### T6. ADR 0004 en documentatie

- `docs/adr/0004-supply-chain-protection.md`: cooldown, Opengrep met eigen regels en versiekeuze,
  Cosign-controle, waarom geen CodeQL en geen Semgrep-regels in de repo.
- `docs/operations.md`: de nieuwe job in de CI-tabel, cooldown bij Dependabot, de wekelijkse melding.
- `docs/testing.md`: wat de scan doet, en hoe je een regel met regeltest toevoegt.
- `docs/getting-started.md` en `AGENTS.md`: `composer check` dekt alles behalve de scan.
- `CHANGELOG.md`: regels onder Unreleased.
- `docs/plan-v4.md`: de twee open punten van Fase 0 afvinken.

### T7. Verifiëren en opleveren

- Lokaal `composer check`; uitvoer in het verslag.
- Push, pull request naar `develop`, CI groen (beide jobs).
- Na de merge "Security scan" als verplichte check in de branch protection (`gh api`), na akkoord.

## Afspraken

1. Claude commit in deze privérepo; push, pull request, merge en instellingen op GitHub na akkoord.
2. De Opengrep-binary en Cosign komen alleen in CI; niets ervan staat in de repo.
