# Buurtpreventie v4 — Onderzoek & implementatieplan

Nov 15, 2026 · @Anke Heijnen

## Samenvatting

Buurtpreventie v4 wordt een Symfony 7.4 LTS-backend met een echte REST-API (`/api/v1`) en een MCP-server (`/mcp`) bovenop dezelfde use cases, plus een losse, buildless frontend die alleen die API gebruikt. Het domeinmodel en de structuur worden verbeterd; de data van bestaande v3-installaties gaat mee via een eenmalige conversie. Voor ontwikkeling, tests en demo's zijn er verzonnen voorbeelddata die met de tijd meegaan.

**Uitgangspunten** (gelden voor elke fase):

- PHP ≥ 8.4, Symfony 7.4 LTS, MySQL/MariaDB; geen Redis, geen workers, geen long-running processen.
- Draait op shared hosting, altijd in een eigen map, op een eigen subdomein (voorkeur) of in een pad, los van andere software zoals WordPress; ook zonder SSH en cron.
- Frontend praat uitsluitend met `/api/v1`; geen domeindata in Twig. Bruikbaar voor lopers van elk digitaal niveau, ook op oudere telefoons (iOS 15 en nieuwer).
- REST en MCP delen dezelfde use cases en Voters; MCP bevat geen eigen logica.
- Geen vereniging-specifieke naam, logo of tekst in de code: alles via instellingen.
- Licentie PolyForm Noncommercial 1.0.0; README in het Engels met een korte Nederlandse samenvatting; docs in het Engels, behalve de woordenlijst en de plannen; code, API, AGENTS.md, CLAUDE.md en alle map- en bestandsnamen in het Engels.
- Weinig afhankelijkheden, geen deprecations, tests vóór code, en overdraagbaar aan een andere ontwikkelaar (zie Overdraagbaarheid). Een beter domeinmodel gaat voor compatibiliteit met v3; data gaat over via conversie.

**Fasen en stand van de MVP** (bijgewerkt op 16 november 2026; details in Implementatieplan):

| Fase | Inhoud | Stand |
| --- | --- | --- |
| 0 | Basis: skelet, CI, docs, codescan | klaar, in `develop` |
| 1 | Domein, use cases, Voters, auditlog, database, voorbeelddata | klaar, in `develop`; T13 naar fase 5b |
| 2 | REST-API v1 met inloggen en API-tokens | klaar, in `develop` |
| 3 | Branding en configuratie | taakplan goedgekeurd (`docs/plans/phase-3.md`), in uitvoering |
| 4 | MCP-server | open |
| 5 | Frontend (Lit, PWA, WCAG 2.2 AA) | open |
| 5b | Conversie v3 → v4 | open |
| 6a | Installeren en releasen | open |
| 6b | Bijwerken, onderhoud en overdracht; daarmee is de MVP klaar en begint de uitrol | open |

**Uitbreidingen na de MVP** (extra functies, elk los aan of uit; volgorde en afweging in Uitbreidingen na de MVP):

| Uitbreiding | Inhoud | Stand |
| --- | --- | --- |
| U1 | Vast loopmaatje uitnodigen | open |
| U2 | iCal-feed met eigen rondes | open |
| U3 | Reageren op de terugmelding | open |
| U4 | Pushmeldingen | open |
| U5 | Statistieken voor analist | open |
| U6 | Offline terugmelden | open |
| U7 | Terugmelden met spraak | later |
| U8 | Vaste export voor politie en gemeente | later, nog uit te werken |
| U9 | Route en incidentkaart | open, opt-in per vereniging, na DPIA |
| U10 | OAuth voor MCP-clients | later |
| U11 | No-shows bijhouden | open, opt-in per vereniging |

**Klaar-afspraak voor elke taak** (naast het "Klaar als" per fase):

- Tests eerst geschreven en groen; PHPStan, PHPat en Mago schoon; geen nieuwe deprecations.
- Schrijvende acties laten een auditregel achter; rechten zitten in een Voter en in de matrixtest.
- Nieuwe teksten in `nl` en `en`; schermen getest met toetsenbord en op een smal scherm.
- Voorbeelddata en woordenlijst bijgewerkt waar het model veranderde.
- `docs/` bijgewerkt waar gedrag of beheer veranderde, plus een regel in `CHANGELOG.md`.
- Werkt met de verzonnen voorbeelddata; echte gegevens horen niet in de ontwikkeling.

## Huidige code

De huidige app is een nette maar monolithische Symfony 7.3-app (PHP 8.4, \~5.100 regels in `src/`) waarin Twig-pagina's, formulieren en een kleine JSON-"API" door elkaar lopen. Laatste commit: 28 mei 2026, MIT-licentie ([repo](https://github.com/zabuto/buurtpreventie-legacy)).

| Onderdeel | Wat er nu is | Knelpunt voor de vernieuwing |
| --- | --- | --- |
| Stack | Symfony 7.3, Doctrine ORM 3, Twig, AssetMapper, Bootstrap, eigen Zabuto Calendar (jQuery-plugin) | Frontend zit in Twig + jQuery; geen losse client |
| Entiteiten | `User`, `Round`, `RoundWalker`, `RoundResult`, `Result`, `MeetingPoint`, `Comment`; basisklasse met blame, timestamps en soft delete | Goed bruikbaar als basis; `Result` is eigenlijk een uitkomst-type (catalogus) |
| Rollen | `ROLE_MEMBER`, `ROLE_WALK`, `ROLE_COORDINATE`, `ROLE_ANALYST`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` | Rechten zitten verspreid in controllers en templates (\~80 vermeldingen) |
| "API" | 8 routes onder `/api/*`, sessie-cookie | Niet REST, geen schema, geen tokens |
| Overige routes | \~40 Twig-routes: kalender, rondes, gelopen rondes, resultaten, leden, admin (gebruikers, startpunten, uitkomsten), account, wachtwoord-reset via token | Al deze schermen worden API + nieuwe frontend |
| Domeinlogica | `WalkService` (aanmelden, afmelden, resultaat, opmerking, minimumaantal lopers), `CalendarService`, `MailService` | Logica is herbruikbaar; mag naar een applicatielaag |
| Achtergrond | `app:reminder --days=1` (e-mailherinnering), Messenger; mail staat sinds de laatste commit op `sync` | Past bij shared hosting: cron + synchroon |
| Privacy | `User.permitted`: lid geeft wel/geen toestemming om contactgegevens te tonen; `User.credited` = certificaat | Moet in API-serialisatie per veld afgedwongen worden |
| Branding | Naam en afzender via `.env` (`APPLICATION_NAME`, `APPLICATION_EMAIL`); kleuren hardcoded in CSS/Twig, afbeeldingen in `public/images` | Logo, kleuren, teksten nog niet instelbaar |
| Hosting | Root-`.htaccess` herschrijft alles naar `public/` — al gebouwd voor shared hosting | Behouden |
| Kwaliteit | PHPStan, Rector en PHPUnit geconfigureerd, maar `tests/` bevat alleen `bootstrap.php` | Geen vangnet bij herbouw: tests eerst |

Conclusie: de domeinregels uit WalkService zijn de moeite waard om mee te nemen; model en structuur worden verbeterd (zie Domeinmodel en Codestructuur). De herbouw zit vooral in een echte API-laag, een losse frontend, tokens voor niet-browser-clients en een testbasis.

## Uitgangssituatie: hoe een loopgroep werkt

**Twee vormen van buurtpreventie.** Actieve buurtpreventie is de verzamelnaam voor bewoners die samen met gemeente en politie werken aan een veilige en leefbare wijk. Er zijn twee vormen. In een buurtpreventieteam, in dit plan loopgroep, lopen bewoners regelmatig door de wijk, meestal met z'n tweeën, 's avonds en herkenbaar aan een reflecterend hesje; ze letten op verdachte situaties, geven gebreken door zoals kapotte straatverlichting, en zijn ogen en oren voor de politie. Gemeente (wijkregisseur) en wijkagent zijn er vanaf de start bij betrokken. In WhatsApp-buurtpreventie (WABP) waarschuwen buren elkaar via een groep, vaak volgens de SAAR-methode. Deze app is alleen voor buurtpreventieteams; buurtsignalen blijven in de WhatsApp-groep (zie Spoedmeldingen hieronder).

Een buurtpreventie-loopgroep bestaat uit tientallen vrijwillige lopers met een of enkele coördinatoren, vaak in samenwerking met politie en gemeente. Lopers roosteren zichzelf in via de app. Een loper loopt nooit alleen; een ronde gaat pas door als er een loopmaatje bij is. Route en startpunt spreken de lopers onderling af.

**Wat de praktijk met v3 laat zien.** Het vinden van een loopmaatje is het knelpunt: een flink deel van de geplande rondes gaat niet door omdat er geen tweede loper is. Daarom krijgen open rondes in de app de meeste aandacht, en komen er later statistieken voor de analist in de app (uitbreiding U5).

Wat dat vraagt van de software:

- **Kleine schaal, weinig beheer.** Tientallen gebruikers, één of twee beheerders. Simpel installeren en bijwerken weegt zwaarder dan schaalbaarheid. Lopers verschillen sterk in digitale vaardigheid en gebruiken niet altijd een nieuw toestel; de app moet werken voor de minst ervaren loper.
- **Zelf inroosteren, altijd met loopmaatje.** Elke loper kan een ronde aanmaken. Die staat op "zoekt loopmaatje" tot een tweede loper zich aanmeldt. Het minimum (standaard 2) is instelbaar, net als een maximum (standaard 7), zodat een groep niet te groot wordt. Een ronde zonder loopmaatje wordt niet gelopen en krijgt geen terugmelding.
- **Loopmaatjes vinden is de kern.** Rondes die een loopmaatje zoeken staan bovenaan. Lopers kunnen zich laten mailen bij een nieuwe open ronde (later ook pushmelding). Wie zich afmeldt waardoor een ander alleen komt te staan, krijgt een waarschuwing, en de achterblijver krijgt bericht. Meldt de laatste loper zich af, dan vervalt de ronde (zoals in v3). Later kan een loper een vast loopmaatje gericht uitnodigen, als die dat zelf heeft toegestaan (uitbreiding U1).
- **Route en startpunt vrij.** Die spreken lopers onderling af. De app biedt een optioneel startpunt en opmerkingen bij de ronde, maar legt in de basis geen routes vast. Route opnemen en een pin bij een incident kan later als uitbreiding U9, alleen met toestemming van de loper.
- **Terugkoppeling na de ronde.** Uitkomst kiezen (geen bijzonderheden / bijzonderheden / incident) plus vrije notitie; dit is wat de coördinator doorgeeft aan politie en gemeente.
- **Spoedmeldingen horen niet in de app.** Acuut: 112. Openbare ruimte: 14040 of BuitenBeter. Buurtsignalen: de WhatsApp-groep. De app moet dit tonen (instelbare teksten), niet vervangen.
- **Altijd los van andere sites.** De app staat altijd in een eigen map of op een eigen subdomein, los van een eventuele website van de vereniging, zoals WordPress. Die mag op dezelfde hosting draaien, maar de twee delen geen map, database-tabellen of cookies. Voorkeur: een eigen subdomein (bijvoorbeeld `buurtpreventie.example.nl`); een pad zonder `/public` in de URL blijft ondersteund. Oude v3-URL's verwijzen door naar de nieuwe.
- **Privacy.** Contactgegevens van lopers alleen zichtbaar met toestemming (bestaat al) en een link naar het privacybeleid van de vereniging. Zie Privacy en AVG voor het volledige overzicht.

## Hosting: shared hosting

De app draait op gewone shared hosting. Die biedt moderne PHP, maar SSH en cron zitten niet in elk pakket, en soms alleen met beperkingen. De app moet dus ook zonder shell te installeren, bij te werken en te plannen zijn.

Gevolgen voor het ontwerp:

1. **Minimum PHP 8.4.27.** Gangbare shared hosting biedt 8.4, het krijgt security-updates tot eind 2028, en het houdt de latere stap naar Symfony 8.4 LTS klein. De oude app zit er al op. Eerdere 8.4-versies hebben PHP2Shell (CVE-2026-109618 en CVE-2026-109619), daarom 8.4.27 als ondergrens.
2. **Geen workers, geen long-running processen.** Messenger blijft `sync`; geen Mercure, geen websockets, geen SSE-streams die minuten openstaan.
3. **Cron is optioneel.** Herinneringen draaien via `bin/console app:scheduler:run` als er cron is, en anders via een beveiligde URL (`POST /api/v1/system/tick` met geheime sleutel) die een externe dienst als cron-job.org aanroept. Een vertraging van uren is acceptabel voor een herinnering "morgen loop je".
4. **Installeren zonder SSH.** Een GitHub Release levert een kant-en-klare zip (met `vendor/`, gebouwde frontend, `.htaccess`). Migraties en de eerste beheerder kunnen via een eenmalige, met een token beveiligde web-installer; met SSH gewoon via `bin/console`.
5. **Pad of subdomein.** De bestaande root-`.htaccess` die naar `public/` herschrijft, blijft; de updater laat hem naar de actieve map `releases/<versie>/public/` wijzen. Op een eigen subdomein (de voorkeur en de standaard in de handleiding) wijst de documentroot naar de app-map, en stuurt dezelfde root-`.htaccess` door naar de actieve release; cookies staan dan los van andere sites op het domein. De installer waarschuwt als de map binnen de map van een andere applicatie staat (bijvoorbeeld een WordPress-installatie), omdat diens `.htaccess`-regels dan kunnen doorwerken. In een pad werkt `example.nl/buurtpreventie/` zonder `/public` in de URL.
6. **`Authorization`-header.** Apache/LiteSpeed met FastCGI stript die header vaak; `.htaccess` moet hem doorgeven (`CGIPassAuth On` of een `RewriteRule` met `E=HTTP_AUTHORIZATION`). Zonder dit werken API-tokens en MCP niet.

**HTTPS en certificaat.** De app werkt alleen via HTTPS: veilige cookies, de service worker en de link in een uitnodiging vereisen het, en de installer weigert zonder. Een nieuw subdomein heeft dus eerst een eigen certificaat nodig. Veel hosters bieden gratis Let's Encrypt in het hostingpaneel, per subdomein aan te zetten en automatisch verlengd. Daarnaast stuurt de app `http://` door naar `https://`. De handleiding beschrijft de volgorde: subdomein aanmaken, DNS laten wijzen, certificaat aanvragen, en pas daarna installeren. Onderhoud waarschuwt als het certificaat binnen 14 dagen verloopt.

## Afhankelijkheden: tijdtrigger, e-mail en externe diensten

Alles wat op een vast moment moet gebeuren, zoals de herinnering de dag vooraf, heeft een tijdtrigger nodig. Alles wat een lid zelf in gang zet, zoals afmelden, werkt direct tijdens dat verzoek en heeft alleen e-mail nodig.

**Twee manieren om de tijdtrigger te geven.** De app gebruikt de beste die er is:

1. **Cron op de server** (`bin/console app:scheduler:run`, elke 15 minuten): stipt, ook 's nachts.
2. **Externe tick:** een dienst als cron-job.org roept elke 15 minuten `POST /api/v1/system/tick` aan, met een geheime sleutel.

**De cronregel.** Updates komen in een nieuwe releasemap, dus cron roept niet een releasemap aan maar een vast script in de app-map, `bin/scheduler.php`, dat zelf de actieve release kiest. Het scherm Onderhoud toont de precieze regel om over te nemen, inclusief het pad naar PHP 8.4 op die server; de laatste run laat daarna zien of het werkt. Je maakt de taak aan in het hostingpaneel; met `>/dev/null 2>&1` aan het eind komt er niet bij elke run een mail. Voorbeeld voor een subdomein, elke 15 minuten (`*/15 * * * *`):

```text
cd /home/<gebruiker>/domains/buurtpreventie.example.nl && <pad-naar-php-8.4> bin/scheduler.php >/dev/null 2>&1
```

Taken meeliften op gewone verzoeken van lopers gebeurt bewust niet: dat zou de app voor hen trager maken. Is er geen cron en geen externe tick, dan worden er **geen herinneringen** verstuurd en ruimt de app niet vanzelf op; de beheerder kan dan opruimen met de knop "Nu opruimen" in Onderhoud. Onderhoud toont welke trigger actief is en wanneer de laatste run was, en meldt het als er 24 uur geen run is geweest.

**Welke functie hangt waarvan af.**

| Functie | Tijdtrigger | E-mail (SMTP) | Andere externe dienst | Zonder die afhankelijkheid |
| --- | --- | --- | --- | --- |
| Herinnering de dag vooraf, met "nog geen loopmaatje" | ja | ja | — | Geen herinnering; de ronde staat wel in de app |
| Mail "nieuwe ronde zoekt loopmaatje" | nee, direct | ja | — | Alleen zichtbaar in de app |
| Mail "je loopmaatje heeft zich afgemeld" | nee, direct | ja | — | Alleen zichtbaar in de app |
| Uitnodiging, inlogcode | nee, direct | ja | — | De beheerder ziet de link van de uitnodiging in het beheerscherm, alleen voor een lid dat nog nooit inlogde, en geeft hem zelf door; zonder mail logt een lid alleen met een wachtwoord in |
| Opruimen volgens bewaartermijnen (auditlog, routes) | ja | — | — | Beheerder ruimt op met de knop Nu opruimen; anders blijven gegevens langer staan dan de privacyverklaring belooft |
| Dagelijkse controle op updates en beveiligingsadviezen | ja | ja (melding) | GitHub, Packagist | Geen melding; Onderhoud controleert wel als de beheerder het scherm opent |
| Update installeren | — | — | GitHub (download) | Handmatig via FTP volgens `docs/admin-guide/` |
| Pushmelding (uitbreiding U4) | ja | — | Pushdiensten van Apple, Google, Mozilla | Alleen e-mail |
| Kaart (uitbreiding U9) | — | — | PDOK of OpenStreetMap, vanuit de browser | Geen kaart; terugmelden werkt gewoon |

Aanmelden, afmelden, terugmelden, kalender, auditlog, API en MCP hangen van geen van deze af.

**E-mail.** De app verstuurt via de SMTP-server van de eigen hosting of een externe mailservice (instelling `MAILER_DSN`). Zonder SPF- en DKIM-records voor het afzenderdomein komen mails vaak in de spam; de handleiding beschrijft dat per host. Onderhoud heeft een knop "Testmail sturen" en toont de laatste verzonden mail en de laatste fout.

**Voorbeeld.** De app verstuurt via de mailserver van de eigen hosting, met een apart mailadres voor de app (bijvoorbeeld `buurtpreventie@example.nl`). Server, poort en beveiliging staan in de handleiding van de hoster; meestal poort 587 met STARTTLS, en de gebruikersnaam is het volledige mailadres. In `shared/.env.local` wordt dat:

```text
MAILER_DSN=smtp://buurtpreventie%40example.nl:WACHTWOORD@mail.example.nl:587
```

Het `@` in de gebruikersnaam wordt `%40`; bij poort 465 begint de regel met `smtps://`. Na installatie: testmail sturen vanuit Onderhoud, en controleren of SPF en DKIM voor het domein goed staan.

**Privacy per externe dienst.** Alles hieronder hoort in de privacyverklaring van de vereniging; de standaardtekst in de app noemt het.

| Dienst | Welke gegevens gaan erheen | Risico | Maatregel |
| --- | --- | --- | --- |
| Externe tick (bv. cron-job.org) | Adres van de app en de geheime sleutel; geen persoonsgegevens | Gelekte sleutel: iemand start de tick vaker | Sleutel alleen voor de tick, rate limit, in Onderhoud te vervangen; kies een dienst in de EU |
| E-mail (SMTP) | E-mailadressen, namen, datum en tijd van rondes in de mailtekst | Persoonsgegevens bij een derde partij | Bij voorkeur de mailserver van de eigen hosting (valt onder de afspraken met de hoster); een externe maildienst alleen met verwerkersovereenkomst en servers in de EU; mails zonder telefoonnummers en zonder notities van terugmeldingen |
| GitHub (releases) | IP-adres van de server en de versie van de app | Verwaarloosbaar; geen gegevens van leden | Geen |
| Packagist (beveiligingsadviezen) | IP-adres van de server en de lijst met geïnstalleerde pakketten | Verwaarloosbaar; geen gegevens van leden | Geen |
| Pushdiensten van Apple, Google, Mozilla (uitbreiding U4) | Apparaatadres en tijdstip; de inhoud is versleuteld | De dienst ziet dát iemand een melding krijgt, en wanneer | Alleen als het lid het aanzet; korte tekst zonder namen ("Je loopt morgen om 20:00") |
| Kaartondergrond PDOK of OpenStreetMap (uitbreiding U9) | IP-adres van de browser en welk kaartgebied wordt bekeken | Laat zien welk gebied iemand bekijkt | PDOK (Nederlandse overheid) als standaard; nooit lopergegevens in de kaartverzoeken |
| AI-client via MCP | Wat de tools teruggeven: rondes, namen van lopers, notities | Gegevens bij de AI-aanbieder die het lid zelf kiest | MCP standaard uit, dataminimalisatie (zie MCP-server), token per lid in te trekken |
| Spraakherkenning in de browser (uitbreiding U7) | De gesproken tekst van de terugmelding | De browsermaker (bij Chrome Google, bij Safari Apple) verwerkt de spraak | Opt-in per vereniging en per lid, melding vooraf, geen opnames op de server; dicteren via het toetsenbord als alternatief |

## Licentie

Gekozen: de nieuwe versie verschijnt onder **PolyForm Noncommercial 1.0.0**, net als [apps-script-event-listings](https://github.com/zabuto/apps-script-event-listings), met dezelfde `Required Notice:`-regel en dezelfde README-tekst over "as is, geen support".

- **Wat de licentie doet.** Gebruiken, wijzigen en delen mag voor elk niet-commercieel doel, ook door goede doelen, onderwijs, overheid en vergelijkbare organisaties; commercieel gebruik niet. Wie het doorgeeft, geeft de voorwaarden en de `Required Notice:`-regel mee ([licentietekst](https://polyformproject.org/licenses/noncommercial/1.0.0)).
- **Overgang van MIT.** De huidige repo is MIT, met dezelfde auteur als rechthebbende. Die mag nieuwe versies onder een andere licentie uitbrengen; bestaande MIT-versies blijven MIT. Leg dit vast met een tag (`v3-mit-final`) en een regel in de README.
- **Afhankelijkheden.** Symfony en Doctrine zijn MIT, de MCP SDK MIT/Apache 2.0; dat combineert met PolyForm NC voor de eigen code, mits hun licentieteksten in `vendor/` meekomen.
- **Let op de term "open source".** PolyForm NC is source-available, geen OSI-licentie. Spreek in de README van "free to use for non-commercial purposes", in de Nederlandse samenvatting "vrij te gebruiken voor niet-commerciële doelen".
- **Bewonersverenigingen.** Een vereniging zonder winstoogmerk die het voor haar leden inzet, valt naar de geest onder niet-commercieel gebruik. Een expliciete zin in de README ("intended for neighbourhood and residents' associations", in de samenvatting "bedoeld voor buurt- en bewonersverenigingen") voorkomt twijfel. Dit is geen juridisch advies.

## API en MCP in Symfony

Keuze: **Symfony 7.4 LTS op PHP 8.4, gewone controllers met invoerklassen, en de officiële MCP SDK via `symfony/mcp-bundle` in stateless HTTP-modus.**

**Symfony-versie.** 7.4 LTS krijgt bugfixes tot november 2028 en security-fixes tot november 2029 ([Symfony](https://symfony.com/blog/symfony-8-0-reaches-its-end-of-maintenance)). Voor een vrijwilligersproject betekent dat één grote upgrade in drie jaar in plaats van één per half jaar. Het project wordt gebouwd zonder deprecations, zodat de overstap naar 8.4 LTS (verwacht november 2027) klein blijft; die staat gepland in 2028, ruim vóór het einde van de 7.4-bugfixes. De oude app draait op 7.3.

**API-laag.** Gewone Symfony-controllers in de use-case-mappen, met invoerklassen via `#[MapRequestPayload]`, de Validator, en OpenAPI via NelmioApiDocBundle. De kern van dit domein zijn acties zoals aanmelden en terugmelden, geen CRUD; één controller per use case past daar precies bij.

**MCP SDK.** De officiële PHP SDK (`mcp/sdk`) is gebouwd door de PHP Foundation en Symfony; laatste versie 0.9.0 van 18 oktober 2026, nog "experimental" en op weg naar 1.0 ([Packagist](https://packagist.org/packages/mcp/sdk), [repo](https://github.com/modelcontextprotocol/php-sdk)). Nieuwe bijdragen vallen onder Apache 2.0, oudere code onder MIT. `symfony/mcp-bundle` (MIT) koppelt hem aan Symfony: tools, prompts, resources en resource templates, over HTTP of STDIO ([bundle](https://github.com/symfony/mcp-bundle)).

**Waarom dit op shared hosting werkt.** Protocolrevisie `2026-07-28` schrapt de handshake en sessies: elk verzoek bevat alles wat de server nodig heeft. Daardoor kan elk PHP-proces elk verzoek afhandelen, zonder gedeelde staat ([protocolversies](https://github.com/modelcontextprotocol/php-sdk/blob/main/docs/protocol-versions.md)). Oudere clients (handshake-tijdperk) kunnen ook zonder sessies, of met de `FileSessionStore` op schijf; geen Redis nodig.

**Autorisatie.** De SDK is alleen *resource server*: hij valideert bearer-tokens, publiceert Protected Resource Metadata (RFC 9728) en dwingt scopes af. Tokens uitgeven (OAuth-autorisatieserver, client-registratie) doet hij bewust niet ([autorisatie](https://github.com/modelcontextprotocol/php-sdk/blob/main/docs/run/authorization.md)). Gevolg voor dit project:

- **Vanaf fase 2:** persoonlijke API-tokens die een lid of beheerder zelf aanmaakt, gecontroleerd door de Symfony-firewall vóór de MCP-route. Werkt met clients die een eigen `Authorization`-header toestaan (bv. Claude Code, Claude Desktop-config, de MCP Inspector).
- **Later, optioneel (uitbreiding U10):** een eigen OAuth 2.1-autorisatieserver (authorization code + PKCE) voor clients die alleen OAuth doen. Dat is extra code en extra beveiligingsrisico; komt als latere uitbreiding, per vereniging aan of uit te zetten.

## Architectuur

Eén Symfony-app met drie ingangen (REST, MCP, scheduler) die allemaal via dezelfde applicatielaag gaan; de frontend is een losse map met statische bestanden die alleen de API aanroept.

De applicatielaag is het hart: een regel als "minimaal twee lopers" of "alleen eigen melding verwijderen" staat daar één keer, en geldt dus voor browser, AI-client en cron tegelijk.

**Waarom buildless.** Geen Node-toolchain bij installeren of bijwerken, net als in je andere projecten. Lit draait als één gevendord bestand (lit-core.min.js) via relatieve imports, zonder importmap; de release-workflow kopieert `frontend/` naar `public/app/`.

**Lit, met een evaluatiemoment.** Lit is de keuze voor v4, ook al heeft de ontwikkelaar er nog weinig ervaring mee. Fase 5 begint daarom met een proef van één scherm. Na fase 5 legt een korte ADR vast hoe het bevalt. Blijkt Lit lastig, dan kan een latere versie overstappen, bijvoorbeeld naar Preact met htm, zonder gevolgen voor API, MCP of backend: de frontend staat volledig los.

## Codestructuur

De backend is een modulaire monoliet: elke module staat direct onder `src/` en heeft dezelfde drie mappen (`Domain`, `UseCase`, `Infrastructure`). Elke use case heeft één eigen map met invoer, handler, REST-controller en MCP-tool. De frontend staat los in `frontend/` en praat alleen met de API.

**Modules.**

| Module | Gaat over |
| --- | --- |
| `Patrol` | Rondes, aanmelden, loopmaatjes, terugmelden (de kern) |
| `Membership` | Leden, rollen, toestemming, certificaat |
| `Identity` | Inloggen (wachtwoord, code per mail, uitnodiging), wachtwoorden, API-tokens |
| `Audit` | Auditlog vastleggen en tonen |
| `Settings` | Branding en teksten |
| `Maintenance` | Herinneringen, updates, installer, conversie uit v3 |

`Shared` is geen module: het bevat algemene bouwstenen, zoals klok, ID's en foutafhandeling (`problem+json`).

**Binnen elke module.**

- **`Domain/`**: entiteiten, value objects, regels (zoals `StaffingRule`) en repository-interfaces. Gewone PHP zonder HTTP of MCP, dus alle domeinregels zijn in milliseconden te testen met in-memory repositories.
- **`UseCase/<Naam>/`**: tot vijf bestanden met vaste namen; alleen de invoerklasse en de handler zijn verplicht, de andere drie komen erbij als de use case ze nodig heeft. De invoerklasse (`SignUpForRound`, met Validator-regels) is het contract. De `…Handler` regelt rechten, regels en auditlog. De `…Controller` (REST) en `…Tool` (MCP) zijn dunne adapters: invoer omzetten, handler aanroepen, uitvoer teruggeven. De uitvoer staat in `…Output`, zonder entiteiten. Een pariteitstest controleert per use case dat beide dezelfde handler met dezelfde invoer aanroepen.
- **`Infrastructure/`**: Doctrine-repositories, e-mail, Symfony-koppelingen zoals authenticators, console-commando's.
- **Tussen modules** alleen via elkaars use cases, bijvoorbeeld `Patrol` haalt een lopernaam op via `Membership/UseCase/GetMemberSummary`, nooit via diens entiteiten.

```text
buurtpreventie/
  src/                              backend (PHP, Symfony)
    Patrol/
      Domain/                       Round, Participation, RoundReport, Outcome,
                                    MeetingPoint, RoundComment, StaffingRule,
                                    RoundRepository (interface)
      UseCase/
        SignUpForRound/
          SignUpForRound.php        invoer + validatie (contract)
          SignUpForRoundHandler.php rechten, regels, auditlog
          SignUpForRoundController.php  REST: PUT /api/v1/rounds/{id}/walkers/me
          SignUpForRoundTool.php    MCP: sign_up_for_round
        WithdrawFromRound/
        CreateRound/
        ReportRoundResult/
        ListRounds/
        FindStaffingGaps/
      Infrastructure/               Doctrine-repositories, e-mail
    Membership/                     Domain/  UseCase/  Infrastructure/
    Identity/                       Domain/  UseCase/  Infrastructure/
    Audit/                          Domain/  UseCase/  Infrastructure/
    Settings/                       Domain/  UseCase/  Infrastructure/
    Maintenance/                    Domain/  UseCase/  Infrastructure/
    Shared/                         klok, ID's, problem+json (geen module)
    Kernel.php
  frontend/                         buildless app: index.html, Lit, CSS, i18n/
  templates/                        alleen e-mails en installer
  public/                           index.php; app/ = kopie van frontend/ bij release
  config/  migrations/  bin/
  tests/                            zelfde mappen als src/ en frontend/
  docs/                             architecture, adr/, glossary, admin-guide/, user-guide/
```

Een PHPat-regel bewaakt dit: elke map direct onder `src/` behalve `Shared` is een module met dezelfde drie submappen.

**Grenzen bewaakt in CI** met [PHPat](https://www.phpat.dev/getting-started/), een PHPStan-extensie, dus geen extra tool. Elke regel heeft een `because()`-tekst die naar `docs/architecture.md` verwijst:

1. `*\Domain` hangt alleen af van de eigen module, `Shared\Domain` en Doctrine-mapping-attributen.
2. Invoerklassen en handlers hangen niet af van Symfony HttpFoundation, het MCP SDK of `*\Infrastructure`.
3. `*Controller` en `*Tool` roepen alleen de handler uit hun eigen map aan; geen Doctrine, en geen entiteiten in de uitvoer.
4. Een module gebruikt een andere module alleen via diens `UseCase`-handlers, of via een interface in `Shared\Domain` die een module implementeert (zoals `AuditTrail`).
5. Twig alleen voor e-mails en de installer; de backend rendert geen schermen.
6. De frontend roept alleen endpoints aan die in de OpenAPI-spec staan (contracttest in CI).

## Overdraagbaarheid

Welke codestructuur het ook wordt: het project moet over te dragen zijn aan een andere ontwikkelaar. Doel: een PHP/Symfony-ontwikkelaar vindt binnen een uur zijn weg, en levert binnen een dag een eerste wijziging op.

**Code en kennis.**

1. **`docs/architecture.md`** legt de gekozen structuur uit met één volledig uitgewerkt voorbeeld, plus een recept "nieuwe functie toevoegen" in een paar stappen.
2. **Beslisnotities (ADR's)** in `docs/adr/` voor elke grote keuze: structuur, Symfony 7.4 LTS en de route naar 8.4 LTS, buildless frontend, licentie, inloggen, MCP. Een opvolger begrijpt waarom, en kan een keuze bewust herzien.
3. **Generators of sjablonen** voor terugkerende code, zodat nieuwe code vanzelf het patroon volgt (bij de huidige structuur: `symfony console app:make:use-case`).
4. **Bewaking die uitlegt:** architectuurregels en CI-controles zeggen in hun foutmelding waarom ze bestaan en waar de uitleg staat.
5. **Vaste namen en `docs/glossary.md`**, zodat Nederlandse begrippen en codenamen eenduidig gekoppeld zijn.
6. **`AGENTS.md` en `CLAUDE.md`** met dezelfde regels, ook voor opvolgers die met AI-tools werken.
7. **Eén commando om te starten en één om te testen:** lokale omgeving met voorbeelddata (fixtures), beschreven in `docs/getting-started.md`.

**Toegang en beheer.**

1. **`docs/operations.md`** beschrijft wat er buiten de code bestaat: GitHub-repo, releases en Dependabot, hostingaccounts, DNS/subdomein, cron of tick, back-ups.
2. **Eigendom van de repo en overdracht via een fork.** De oorspronkelijke ontwikkelaar blijft de enige eigenaar van de GitHub-repo en van de sleutel waarmee releases worden ondertekend. Neemt iemand anders het project over, dan gaat dat zo:
   - De opvolger maakt een fork; de licentie (PolyForm NC met de `Required Notice:`-regel) gaat mee.
   - De opvolger maakt een eigen ondertekeningssleutel en zet CI, Dependabot en releases in de fork aan.
   - De oorspronkelijke ontwikkelaar brengt één laatste, ondertekende **overdrachtsrelease** uit. Die zet in elke installatie de updatebron om naar de fork en voegt de publieke sleutel van de opvolger toe. Webbeheerders hoeven daarvoor alleen op Bijwerken te klikken.
   - De oorspronkelijke repo wordt gearchiveerd (alleen-lezen), met bovenaan de README een verwijzing naar de fork.

   Is de oorspronkelijke ontwikkelaar onbereikbaar, dan kan een beheerder in Onderhoud zelf een andere updatebron en sleutel instellen: alleen na opnieuw inloggen, met een duidelijke waarschuwing, en vastgelegd in het auditlog. `docs/operations.md` beschrijft beide routes.
3. **Geheimen nooit in de repo;** wel een lijst welke geheimen er zijn en waar ze staan.

**Toets:** vóór de overstap volgt iemand zonder voorkennis (een andere ontwikkelaar, of Claude Code in een lege map) `docs/getting-started.md` en voegt één kleine functie toe. Wat onduidelijk blijkt, wordt herschreven.

## Voorbeelddata (fixtures)

Het project krijgt verzonnen voorbeelddata die altijd actueel ogen: alle datums worden bij het laden berekend vanaf vandaag, zodat de kalender nooit leeg of verouderd is. Ze dienen voor lokale ontwikkeling, tests, schermafbeeldingen in de handleidingen, de gebruikerstest en proefinstallaties van andere verenigingen.

**Wat erin zit (set `demo`).**

- **Een verzonnen vereniging** "Buurtpreventie Voorbeeldwijk" met neutrale branding, een paar startpunten en de standaarduitkomsten.
- **Hooguit 20 leden** met elke rol (vooral lopers; hooguit drie leden met een andere rol: één coördinator die ook loopt, en één analist en één beheerder die niet per se lopen), wisselende toestemming voor contactgegevens, een gedeactiveerd lid, een gewist lid in de historie en een lid met een API-token. Alleen adressen op `example.org` en verzonnen telefoonnummers.
- **Rondes van drie maanden terug tot zes weken vooruit**, met het patroon uit de echte loopschema's: vooral avonden, soms ochtend of middag, ruim een kwart zonder loopmaatje, soms een volle ronde, af en toe een incident, terugmeldingen bij gelopen rondes en een paar zonder.
- **Auditlog-regels** die bij die rondes passen, zodat het scherm Auditlog meteen iets laat zien.

**Andere sets.**

| Set | Voor | Inhoud |
| --- | --- | --- |
| `demo` | Ontwikkelen, schermafbeeldingen, proefinstallatie | Zie hierboven |
| `test` | Automatische tests | Minimaal; elke test bouwt zelf wat hij nodig heeft |
| `edge` | Lastige gevallen | Ronde rond de zomer-/wintertijdwissel, laatste loper die zich afmeldt, verwijderd lid in de historie, lid zonder toestemming, ronde net onder het minimum, ronde op het maximum, twee leden met dezelfde naam en een eigen weergavenaam |
| `v3` | Test van de conversie | Een kleine, verzonnen v3-database, nooit echte data |

**Zo blijven ze bruikbaar.**

- **Gebouwd via dezelfde use cases** als de app (`CreateRound`, `SignUpForRound`, …), niet door rechtstreeks in de database te schrijven. Verandert een regel of het model, dan gaan de fixtures mee of falen ze meteen.
- **Vaste startwaarde voor het toeval** (seed), dus elke keer dezelfde mensen en hetzelfde patroon; alleen de datums schuiven mee met vandaag. Tests zetten de klok vast met Symfony Clock.
- **CI laadt elke set** en doet een korte rooktest; zo merkt niemand pas bij een demo dat de voorbeelddata stuk zijn.
- **Eén commando:** `symfony console app:fixtures:load demo`, ook onderdeel van het startcommando uit `docs/getting-started.md`.
- **Nooit in productie:** fixtures zitten niet in de release-zip. Alleen een proefinstallatie kan in de web-installer kiezen voor "voorbeelddata laden", met een knop "voorbeelddata wissen" in Onderhoud.
- **Gebruik `doctrine/data-fixtures`** (de bibliotheek onder de bundle uit v3) met een eigen commando en kleine factories per module, alleen als dev-afhankelijkheid; de bundle voegt alleen toe wat dit project niet gebruikt.

## Frontend: PWA, responsive en toegankelijk

De frontend ís de PWA: dezelfde app krijgt een manifest en een service worker, zodat lopers hem vanuit de browser op hun beginscherm zetten. Geen aparte app, geen app store, één codebase.

**Wat de PWA hier oplevert.**

| Onderdeel | Wat de loper merkt | Fase |
| --- | --- | --- |
| Installeerbaar | Eigen naam, icoon en kleur van de vereniging op het beginscherm, opent zonder browserbalk | 4 |
| App-shell in cache | Opent direct, ook bij slecht bereik | 4 |
| Offline lezen | Laatst opgehaalde rondes en eigen aanmeldingen zichtbaar, met duidelijke melding "offline" | 4 |
| Offline terugmelden | Terugmelding gaat in een wachtrij en wordt verstuurd zodra er bereik is | 11 |
| Pushmelding als herinnering | Naast de e-mail, alleen als het lid dat aanzet | 10 |

**Regels voor de service worker.**

- Cachet de app-shell en alleen de eigen rondegegevens; nooit ledenlijsten, contactgegevens, beheer of auth-verzoeken. Bij uitloggen wordt de cache gewist.
- Staat op het basispad van de app (bv. `/buurtpreventie/sw.js`) zodat zijn scope de hele app dekt; `start_url` en `scope` in het manifest komen uit de configuratie. Zo werkt het ook in een submap.
- Een nieuwe versie meldt zich met "Nieuwe versie beschikbaar, vernieuwen"; nooit stil halverwege een formulier.

**Responsive.**

- Mobiel eerst; bruikbaar vanaf 320 px breed en bij 400% zoom zonder horizontaal scrollen.
- De kalender wordt op een telefoon een lijst per dag; het maandraster alleen op brede schermen. Zeven kolommen op 360 px zijn niet te lezen of aan te tikken.
- Tikdoelen minstens 24 × 24 px (WCAG-minimum), knoppen voor de hoofdacties 44 × 44 px.
- Volgt systeeminstellingen: donkere modus en minder beweging.

**Kalender (loopschema).** De v3-kalender toont per dag drie dagdelen naast elkaar: ochtend, middag, avond. Een dagdeel is groen als een ronde daar genoeg lopers heeft, lichtrood als die nog een loopmaatje zoekt. Na afloop kleurt het dagnummer groen (geen incidenten), rood (incident) of grijs (geen terugmelding); geel betekent dat je zelf meeloopt. v4 houdt die informatie, maar maakt hem leesbaar zonder kleur:

- **Bovenaan "Zoekt een loopmaatje"**: de komende open rondes met datum, tijd en wie al loopt, plus de knop "Ik loop mee". Dit is het belangrijkste blok van de app.
- **Per ronde een regel in tekst**: "Avond 20:00 · 2 lopers" of "Avond 21:00 · zoekt loopmaatje", met een icoon; kleur komt erbij, niet in plaats van.
- **Na afloop**: "Gelopen, geen bijzonderheden", "Incident gemeld" of "Nog geen terugmelding"; "Niet gelopen (geen loopmaatje)" voor rondes die niet doorgingen.
- **Eigen rondes** krijgen "Je loopt mee" en vallen op zonder geel als enige signaal.
- **Telefoon**: lijst per dag met alleen dagen waarop iets gepland staat, en een knop "Ronde toevoegen" per dag. **Breed scherm**: maandraster met de dagdelen als gelabelde regels in elke dag.
- **Ronde toevoegen**: dag, tijd, en optioneel startpunt en opmerking ("start bij de school, route via het park"); een routekaart volgt eventueel in uitbreiding U9.

**Voor elke loper, ook met een oudere telefoon.** Lopers verschillen sterk in digitale vaardigheid, en niet iedereen heeft het nieuwste toestel. De app wordt ontworpen voor de minst ervaren loper.

| Toestel | Afspraak |
| --- | --- |
| iPhone / iPad | Safari op iOS/iPadOS 15 en nieuwer, dus ook iPhone 6s, 7 en SE (1e generatie), die niet verder komen dan iOS 15 |
| Android | Chrome of Samsung Internet van de laatste twee jaar, ook op goedkope toestellen |
| Computer | Actuele Chrome, Edge, Firefox en Safari |
| Te oud | Een duidelijke melding in gewone taal met wat te doen en wie te bellen, geen leeg scherm |

Technische keuzes die daarbij horen:

- **Lit als één gevendord bestand, zonder import map.** Lit importeert intern met "kale" pakketnamen, die de browser alleen via een import map of een gebundelde versie kan laden ([Lit-vereisten](https://lit.dev/docs/tools/requirements)). Import maps werken pas vanaf Safari 16.4; de gebundelde `lit-core.min.js` met relatieve imports werkt ook op iOS 15. Eigen code blijft binnen ES2021, net als Lit zelf.
- **Melding voor te oude browsers** via `<script nomodule>`, zodat niemand een wit scherm krijgt.
- **Klein en snel:** app-shell inclusief Lit onder 100 KB gecomprimeerd (gecontroleerd in CI), systeemlettertype, bruikbaar op traag mobiel internet.
- **Installeren als app is een extraatje;** alles werkt ook gewoon in de browser.

Eenvoud in gebruik:

- **Gewone taal (B1)**, geen vaktermen als "token" of "sessie". Knoppen zeggen wat ze doen: "Ik loop mee", "Ik kan toch niet".
- **Startscherm met drie dingen:** Zoekt een loopmaatje, Mijn rondes, Terugmelden.
- **Grote basistekst (18 px)** die meeschaalt met de lettergrootte van de telefoon.
- **Bevestiging in woorden na elke actie**, met ongedaan maken waar het kan ("Afgemeld. Ongedaan maken").
- **Inloggen zonder gedoe:** "ingelogd blijven" voor 90 dagen en inloggen met een code van zes cijfers per mail; "wachtwoord vergeten" is inloggen met een code. Een wachtwoord kan, maar is nooit verplicht. Een nieuw lid krijgt een uitnodiging: een link die één keer werkt. E-mails hebben een directe knop naar de ronde.
- **Hulp van de coördinator:** een nieuwe uitnodiging laten sturen, zonder de link zelf te zien, en een loper die het zelf niet lukt namens hem aan- of afmelden; dat staat als "namens" in het auditlog.
- **Handleiding voor lopers:** één A4 met schermafbeeldingen in `docs/user-guide/`, en dezelfde uitleg in de app onder "Hoe werkt het?".

Testen met echte lopers en toestellen:

- Op een iPhone met iOS 15 (bv. iPhone 7) en een goedkope Android-telefoon, bij elke release.
- Vóór livegang een gebruikerstest met drie lopers, onder wie de minst ervaren, met drie opdrachten: meld je aan bij een ronde die een loopmaatje zoekt, meld je weer af, geef door dat je niets bijzonders zag.

**WCAG 2.2 niveau AA.** Een vereniging is daar wettelijk niet altijd toe verplicht, maar lopers zijn vaak ouder of op straat met één hand bezig. De punten die in deze app het meest uitmaken:

| Punt | Hoe |
| --- | --- |
| Niet alleen kleur | De oude kalender toont bezetting alleen met rood/groen; nieuw: tekst "1 van 2 lopers" plus icoon |
| Toetsenbord | Alles bereikbaar met Tab; kalender met pijltjestoetsen; focus altijd zichtbaar en niet verstopt achter een vaste kop |
| Statusmeldingen | "Aangemeld voor ronde 21:00" via een `aria-live`-regio, zodat schermlezers het voorlezen |
| Formulieren | Zichtbare labels, foutmelding in tekst bij het veld, gekoppeld met `aria-describedby`; ingevulde gegevens niet opnieuw vragen |
| Inloggen | Wachtwoordmanager en plakken toegestaan, geen puzzels of captcha |
| Vaste hulp | Contact coördinator en "wat te doen bij spoed" op elke pagina op dezelfde plek |
| Contrast | Tekst 4,5 : 1, knoppen en randen 3 : 1; brandingkleuren worden bij opslaan gecontroleerd, ook in donkere modus |
| Taal | `lang="nl"` of `"en"` op de pagina, afhankelijk van de instelling |

**Valkuil bij Lit.** Lit gebruikt standaard shadow DOM; `aria-labelledby` en `<label for>` werken niet over die grens heen. Formuliercomponenten renderen daarom in de light DOM, of houden label en invoerveld in dezelfde component.

**Testen.**

- Automatisch: axe-core op elk scherm in CI (Playwright, alleen in CI, niet nodig op de server), met de tags `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa` en `wcag22aa`. De contrastregel blijft aan, omdat beheerders de kleuren zelf instellen.
- Handmatig per release: één ronde met alleen toetsenbord, met VoiceOver op een iPhone en met NVDA op Windows; checklist in `docs/testing.md`.
- In fase 5: twee echte lopers doen aanmelden en terugmelden op hun eigen telefoon, met voorbeelddata.
- Een korte toegankelijkheidsverklaring staat in de app (standaardtekst, aan te passen in de instellingen).

## Domeinmodel en API-ontwerp

Het domeinmodel wordt verbeterd waar dat de app helderder en eenvoudiger maakt; v3 is geen keurslijf. De data van bestaande v3-installaties gaat mee via een eenmalige conversie (zie Conversie v3 → v4 hieronder).

| Entiteit | Module | Uit v3 | Wat verandert |
| --- | --- | --- | --- |
| `Account` | Identity | deel van `User` | Alleen inloggen: wachtwoordhash (nooit verplicht), laatste login en het moment van het laatste nieuwe wachtwoord, met een verwijzing naar het lid. Inlogcodes en uitnodigingen staan in eigen entiteiten (`LoginChallenge`, `Invitation`). Los van het profiel, zodat de v3-`User` geen god object meer is |
| `Member` | Membership | deel van `User` | E-mailadres (voor mails én inloggen), naam, unieke weergavenaam (`displayName`, zoals "Jan H."), telefoon, adres, rollen, `consentContactShare` (was `permitted`), `certified` (was `credited`), `active`, `notifyOpenRounds` |
| `Round` | Patrol | `Round` | `startsAt`, aangemaakt door een loper, optioneel `meetingPoint` en vrije tekst `startNote`; `minimumWalkers` en `maximumWalkers` vastgelegd bij aanmaken; status, open plekken en dagdeel afgeleid |
| `Participation` | Patrol | `RoundWalker` | Aanmelding: lid, `joinedAt`, `remindedAt`, `onBehalfOf` (als de coördinator aanmeldde); ronde + lid is uniek |
| `RoundReport` | Patrol | `RoundResult` | Terugmelding per ronde: één uitkomst + notitie, door een loper van die ronde of de coördinator; alleen bij een gelopen ronde |
| `Outcome` | Patrol | `Result` | Eén veld `kind` (`nothing_to_report`, `remarks`, `incident`) in plaats van twee vinkjes die elkaar konden tegenspreken; `archived` in plaats van soft delete |
| `MeetingPoint` | Patrol | `MeetingPoint` | Optionele lijst met suggesties, coördinaat optioneel (breedte- en lengtegraad in WGS84); `archived` in plaats van soft delete |
| `RoundComment` | Patrol | `Comment` | Opmerking met auteur |
| `AuditEntry` | Audit | nieuw | Zie Auditlog |
| `ApiToken` | Identity | nieuw | Hash, naam, scopes, verloopdatum, laatst gebruikt |
| `Setting` | Settings | nieuw | Branding en teksten (zie Branding) |

Verbeteringen ten opzichte van v3:

- **Geen generieke soft delete en blame meer.** Het auditlog bewaart de geschiedenis. Leden worden gedeactiveerd of op verzoek gewist (AVG), catalogi gearchiveerd, rondes echt verwijderd. Geen globale filter meer die je in een query kunt vergeten; alleen velden met betekenis blijven, zoals de auteur van een opmerking.
- **Rollen als vaste lijst** op `Member`: `walker`, `coordinator`, `analyst`, `admin`. De v3-rollen `ROLE_MEMBER`, `ROLE_SUPER_ADMIN` en "switch user" vervallen; de coördinator handelt "namens" een loper, zichtbaar in het auditlog.
- **UUIDv7 als ID** in plaats van oplopende nummers: niet te raden in URL's en API. De conversie houdt een koppeltabel oud → nieuw bij.
- **Tijd zonder verrassingen:** `startsAt` in UTC opgeslagen, getoond in Europe/Amsterdam, met Symfony Clock zodat tests de tijd vastzetten.
- **Value objects** voor wat regels heeft: `EmailAddress`, `PhoneNumber`, `Daypart`, `RoundStatus`, `StaffingRule`.

**Status van een ronde** wordt afgeleid, niet opgeslagen: `seeking_buddy` (minder lopers dan het minimum), `staffed` (minimum gehaald), `walked` (voorbij en gehaald), `not_walked` (voorbij zonder loopmaatje) en `cancelled` (begonnen maar niet gelopen, zoals bij een no-show of overmacht). "Vol" is geen status maar een veld: `openSpots` is het maximum min het aantal lopers zolang de ronde niet begonnen is, en 0 vanaf de start; een volle ronde is `staffed`. Elke ronde in API en MCP bevat `status`, `walkerCount`, `minimumWalkers`, `maximumWalkers`, `openSpots` en `daypart`: `morning` vóór 12:00, `afternoon` tot 18:00, `evening` vanaf 18:00, zoals de v3-kalender ze al indeelt.

Regels die daaruit volgen:

- Terugmelden kan alleen bij `walked`.
- Een loper van de ronde of de coördinator annuleert een begonnen ronde, met een optionele reden; vóór de start meldt een loper zich af of wordt de ronde verwijderd. Een ronde met een terugmelding wordt niet geannuleerd.
- Een gearchiveerd startpunt of een gearchiveerde uitkomst wordt niet aangeboden voor een nieuwe keuze of een nieuwe ronde; een ronde houdt het startpunt dat ze al had. Een startpunt waar nog een ronde start die niet begonnen is, kan niet gearchiveerd worden.
- Minimum en maximum (maximum ≥ minimum) komen bij aanmaken uit de instellingen en gelden voor die ronde; een gewijzigde instelling geldt alleen voor nieuwe rondes.
- Aanmelden bij een volle ronde wordt geweigerd, ook namens; wie al aangemeld is, krijgt geen fout.
- Meldt de laatste loper zich af, dan vervalt de ronde (zoals in v3).
- Wordt een ronde door een afmelding weer `seeking_buddy`, dan ziet de afmelder vooraf een waarschuwing en krijgt de overgebleven loper een e-mail.
- Bij een nieuwe ronde die een loopmaatje zoekt, mailt de app de lopers met `notifyOpenRounds` aan; niet vaker dan één mail per ronde.
- De herinnering de dag ervoor zegt het erbij als de ronde nog geen loopmaatje heeft (zoals in v3).

**Conventies.** Alles onder `/api/v1`, JSON, datums als ISO 8601 met tijdzone `Europe/Amsterdam`, fouten als `application/problem+json` (RFC 9457) met veldfouten van de Validator. Muterende acties nooit via `GET`. Aan- en afmelden zijn idempotent (`PUT`/`DELETE`), zodat dubbelklikken en herhalingen geen dubbele aanmeldingen geven. OpenAPI-spec op `/api/v1/docs.json`.

**Endpoints.**

| Groep | Endpoints | Wie |
| --- | --- | --- |
| Publiek | `GET /config` (branding, teksten, minimum- en maximumaantal lopers) | iedereen |
| Sessie | `POST /auth/login`, `POST /auth/logout`, `POST /auth/confirm` (opnieuw bevestigen; geen aparte wachtwoord-reset, zie Fase 2) | iedereen |
| Eigen account | `GET/PATCH /me`, `PUT /me/password`, `GET/POST/DELETE /me/tokens` | lid |
| Rondes | `GET /rounds?from=&to=&status=`, `GET /rounds/{id}`, `POST /rounds`, `PATCH /rounds/{id}`, `DELETE /rounds/{id}`, `POST /rounds/{id}/cancellation`, `GET /staffing-gaps` | lid leest; loper maakt aan; coördinator wijzigt/verwijdert/annuleert |
| Aanmelden | `PUT /rounds/{id}/walkers/me`, `DELETE /rounds/{id}/walkers/me` | loper |
| Na de ronde | `GET /me/rounds?walked=1`, `POST /rounds/{id}/reports`, `DELETE /reports/{id}`, `POST /rounds/{id}/comments`, `DELETE /comments/{id}` | loper (eigen items), coördinator |
| Leden | `GET /members` (contactgegevens alleen bij toestemming) | lid |
| Keuzelijsten | `GET /meeting-points`, `GET /outcomes` (alleen niet gearchiveerd) | lid |
| Analyse | `GET /reports?from=&to=&outcome=`, `GET /stats?period=` | analist |
| Beheer | `/admin/members` (CRUD, rollen, (de)activeren, uitnodiging), `/admin/meeting-points`, `/admin/outcomes` (met archiveren en terugzetten), `/admin/settings` | beheerder; uitnodigen ook coördinator |
| Systeem | `POST /system/tick` (geheime sleutel; draait herinneringen) | cron |
| Inloggen per e-mail | `POST /auth/login-code`, `POST /auth/login-code/verify` (code van zes cijfers), `POST /auth/invitation` (uitnodiging) | iedereen |
| Namens een loper | `PUT /rounds/{id}/walkers/{memberId}`, `DELETE /rounds/{id}/walkers/{memberId}` | coördinator |
| Auditlog | `GET /me/activity`, `GET /rounds/{id}/history`, `GET /admin/audit` | lid, coördinator, beheerder (zie Auditlog) |
| Onderhoud | `GET /admin/maintenance`, `POST /admin/maintenance/update`, `POST /admin/maintenance/rollback`, `POST /admin/maintenance/backup` | beheerder |
| Media | `GET /media/brand/{bestand}` | iedereen |
| Privacy | `GET /me/export`, `DELETE /admin/members/{id}` (wissen) | lid (eigen gegevens), beheerder (wissen) |

**Rechten.** Eén Voter per onderwerp (`RoundVoter`, `ReportVoter`, `MemberVoter`, …) met de rollen walker, coordinator, analyst en admin. Rollen tellen op, en elke rol geeft alleen zijn eigen rechten. De beheerder (`admin`) beheert leden, catalogi, instellingen en onderhoud, en heeft geen rechten op de inhoud van rondes; wie dat nodig heeft, krijgt ook de rol coördinator. De analist leest alleen; loopt hij ook, dan krijgt hij de rol loper erbij. Controllers en MCP-tools roepen dezelfde Voters aan; een test controleert de volledige matrix rol × actie.

**Authenticatie.** Uitgewerkt en vastgelegd in fase 2 ([ADR 0006](../adr/0006-authentication.md)).

- **Browser:** sessiecookie (`SameSite=Lax`, `HttpOnly`, `Secure`) met eigen authenticators voor wachtwoord, code per mail, uitnodiging en opnieuw bevestigen, CSRF op muterende verzoeken en "ingelogd blijven" voor 90 dagen. Frontend en API staan op hetzelfde domein, dus geen CORS nodig.
- **Scripts en MCP:** `Authorization: Bearer <token>` via een eigen authenticator. Tokens worden alleen gehasht opgeslagen, eenmalig getoond, met scopes `read`, `write`, `admin` en een verloopdatum.

**Conversie v3 → v4.**

- `bin/console app:import-v3` leest de v3-database via een tweede, alleen-lezen databaseverbinding en vult de lege v4-database. Ook te starten vanuit de web-installer, voor hosts zonder SSH.
- Eerst `--dry-run`: rapport met aantallen per soort en waarschuwingen, zoals een lid zonder e-mail, dubbele e-mailadressen, een ronde zonder lopers, een ronde met meer lopers dan het maximum (die krijgt het aantal lopers als maximum) of een terugmelding bij een niet-gelopen ronde.
- Wachtwoorden gaan mee: v3 bewaart hashes via Symfony's `auto`-hasher, die v4 ook kan controleren. Lopers loggen in met hun bestaande wachtwoord; de hash wordt bij inloggen zo nodig vernieuwd.
- Verwijderde rondes en catalogusitems uit v3 gaan niet mee. Verwijderde leden die nog in de historie voorkomen, worden "verwijderd lid".
- De v3-blamevelden worden startregels in het auditlog, met kanaal `import`. Het model krijgt geen velden die alleen voor de conversie bestaan.
- **Standaard gaat alle historie mee.** Rondes en terugmeldingen ouder dan de bewaartermijn worden tijdens de conversie meteen geanonimiseerd: geen leden en geen notitietekst, wel datum, dagdeel, startpunt, aantal lopers, status en uitkomst. Zo kloppen de statistieken over de hele historie zonder oude persoonsgegevens. Auditregels uit v3 gaan alleen mee binnen de bewaartermijn van het auditlog. Met `--since=<datum>` neemt een vereniging minder mee.
- v3 wordt nooit gewijzigd. Tot de overstap definitief is, kan een vereniging dus altijd terug, en de conversie opnieuw draaien op een lege v4-database geeft hetzelfde resultaat.

## MCP-server

De MCP-server is een dunne laag op `/mcp` die dezelfde use cases en Voters aanroept als de REST-API; er zit geen eigen logica in, dus wat de API toestaat is precies wat MCP toestaat.

**Tools** (namen in het Engels; modellen kiezen ze daar het betrouwbaarst op):

| Tool | Doet | Scope | Rol |
| --- | --- | --- | --- |
| `list_rounds` | Rondes in een periode, met bezetting en of het minimum gehaald is | `read` | lid |
| `get_round` | Eén ronde: tijd, startpunt, lopers, meldingen | `read` | lid |
| `my_rounds` | Eigen komende en gelopen rondes, open terugmeldingen | `read` | lid |
| `staffing_gaps` | Rondes de komende N dagen die nog een loopmaatje zoeken | `read` | lid |
| `sign_up_for_round` / `withdraw_from_round` | Aan- en afmelden | `write` | loper |
| `create_round` | Nieuwe ronde op datum, tijd, startpunt | `write` | loper |
| `report_round_result` | Uitkomst + notitie na de ronde | `write` | loper |
| `add_round_comment` | Opmerking bij een ronde | `write` | loper van die ronde, coördinator |
| `report_summary` | Aantallen per uitkomst en incidenten in een periode | `read` | analist |
| `list_meeting_points`, `list_outcomes` | Keuzelijsten | `read` | lid |
| `round_statistics` | Aantallen gepland, gelopen, niet gelopen (geen loopmaatje) en incidenten, per maand, weekdag of dagdeel, met percentages en vergelijking met de vorige periode | `read` | analist, coördinator |

**Resources:** `buurtpreventie://config` (naam, minimum- en maximumaantal lopers, startpunten, uitkomsten). **Prompts:** `monthly_report` (maandoverzicht voor politie/gemeente uit `report_summary`) en `plan_week` (open plekken komende week).

**Voorbeeldvragen.** De tools leveren exacte aantallen uit de database; de AI-client maakt er een samenvatting van, zoals de analyse van de loopschema's in dit plan.

| Rol | Vraag | Tools |
| --- | --- | --- |
| Analist | "Hoeveel rondes gingen dit kwartaal niet door door een gebrek aan loopmaatjes, per maand?" | `round_statistics` |
| Analist | "Op welke weekdagen en dagdelen ontbreekt het vaakst een loopmaatje?" | `round_statistics` |
| Analist | "Vergelijk oktober met september" | `round_statistics` |
| Analist | "Hoeveel incidenten waren er dit jaar, en in welke maanden?" | `report_summary` |
| Analist | "Schrijf het maandoverzicht voor de wijkagent" | prompt `monthly_report` |
| Coördinator | "Welke rondes zoeken deze week nog een loopmaatje?" | `staffing_gaps` |
| Coördinator | "Op welke avonden volgende week staat niets gepland?" | prompt `plan_week` |
| Loper | "Wanneer loop ik de komende weken, en heb ik nog terugmeldingen open?" | `my_rounds` |
| Loper | "Meld me aan voor de open ronde op donderdag" | `staffing_gaps`, `sign_up_for_round` |

**Bewust niet te vragen:** ranglijsten of aantallen per loper ("wie loopt het minst?"), contactgegevens, en alles wat iets verwijdert. De tools geven die gegevens niet terug, dus de AI-client kan ze ook niet samenvatten.

**Bewust niet in v1:** verwijderen, ledenbeheer en instellingen. Die blijven in de beheerschermen, waar een mens bevestigt.

**Beveiliging.**

- Elk verzoek werkt als het lid van het token; scope én rol moeten kloppen.
- Uitvoer bevat alleen wat nodig is: geen telefoonnummers of adressen, ook niet bij toestemming.
- Notities en opmerkingen zijn door leden getypte tekst; tool-uitvoer zet ze in een apart veld, zodat clients ze als data behandelen en niet als instructie.
- Schrijftools krijgen MCP-annotaties (`readOnlyHint`, `destructiveHint: false`, `idempotentHint` bij aan-/afmelden).
- Rate limit per token met `symfony/rate-limiter` op de bestandscache.
- DNS-rebinding-bescherming van de bundle met de eigen hostnaam als enige toegestane host.
- Elke schrijfactie via MCP komt in het auditlog met kanaal `mcp`.

**Transport.** Streamable HTTP, JSON-antwoorden zonder SSE-stream, sessieloos; het `2026-07-28`-tijdperk wordt standaard bediend, het handshake-tijdperk zonder sessies. Daarnaast `symfony console mcp:server` over STDIO voor lokaal gebruik door de ontwikkelaar.

## Auditlog

Elke wijziging door een lid wordt vastgelegd als regel in een alleen-toevoegen-log: wie, wat, wanneer, via welk kanaal (web, API, MCP, cron) en wat er veranderde. Zo kan een coördinator zien wie zich wanneer afmeldde, en kan een lid zijn eigen geschiedenis inzien.

**Wat wordt vastgelegd.**

| Onderwerp | Acties |
| --- | --- |
| Rondes | aangemaakt, tijd of startpunt gewijzigd, verwijderd |
| Aanmeldingen | loper aangemeld, afgemeld, door coördinator verwijderd, automatisch uitgeschreven (inactief lid) |
| Na de ronde | terugmelding geplaatst of verwijderd, opmerking geplaatst of verwijderd |
| Leden | toegevoegd, rol gewijzigd, (de)geactiveerd, hersteld, toestemming contactgegevens gewijzigd |
| Account | ingelogd (met wachtwoord, code of uitnodiging), mislukte inlog, inlogcode aangevraagd, uitnodiging verstuurd, wachtwoord gewijzigd, API-token aangemaakt of ingetrokken |
| Beheer | instelling gewijzigd, startpunt of uitkomst gewijzigd, update uitgevoerd of teruggezet |

**Per regel:** tijdstip (UTC, getoond in Europe/Amsterdam), wie (lid-id plus naam op dat moment, of "systeem"), actie (bv. `round.walker_removed`), onderwerp (type + id), kanaal, en de gewijzigde velden als oud → nieuw. Nooit wachtwoorden, tokens of volledige IP-adressen; bij mislukte inlogpogingen alleen een ingekort IP-adres.

**Wie ziet wat.**

| Rol | Ziet | Waar |
| --- | --- | --- |
| Lid | Eigen activiteit | Account → "Mijn activiteit" (`GET /api/v1/me/activity`) |
| Coördinator | Geschiedenis van rondes en aanmeldingen | Ronde → "Geschiedenis" (`GET /api/v1/rounds/{id}/history`) |
| Beheerder | Alles, met filters op lid, actie en periode, plus CSV-export | Beheer → "Auditlog" (`GET /api/v1/admin/audit`) |

**Hoe het gebouwd wordt.**

- Een eigen module `Audit` met entiteit `AuditEntry`. Andere modules schrijven via de interface `Shared\Domain\AuditTrail::record()`, die `Audit` implementeert.
- Handlers in `UseCase/` roepen `record()` expliciet aan, in dezelfde databasetransactie als de wijziging. Geen Doctrine-listeners: die weten niet wat de bedoeling was of via welk kanaal het kwam.
- Het kanaal komt uit de requestcontext (`Http`, `Mcp`, `Console`) en hoeft dus niet door elke handler doorgegeven te worden.
- De app heeft geen endpoint om regels te wijzigen of te verwijderen. Elke regel draagt een HMAC-keten met de vorige, zodat achteraf geknoei in de database opvalt; uitgewerkt in Fase 3.
- Er zijn geen generieke blame-velden meer; het auditlog is de volledige geschiedenis, en entiteiten houden alleen velden met betekenis, zoals de auteur van een opmerking.
- Een test faalt als een schrijvende handler geen auditregel achterlaat.

**Bewaren en privacy (AVG).**

- Bewaartermijn instelbaar: standaard 24 maanden voor acties, 90 dagen voor inlogregels; de scheduler ruimt ouder op.
- Wordt een lid op verzoek gewist, dan wordt de naam in het log vervangen door "verwijderd lid"; de handelingen zelf blijven.
- Het auditlog en de bewaartermijn horen in de privacyverklaring van de vereniging; de standaardtekst in de app noemt ze.

## Privacy en AVG

De vereniging is verwerkingsverantwoordelijke: zij stelt de privacyverklaring, bewaartermijnen en afspraken met verwerkers vast. De app zorgt dat dat kan met instellingen, standaardteksten en vaste voorzieningen. Dit overzicht is geen juridisch advies.

**Rollen.**

- **Vereniging:** verwerkingsverantwoordelijke.
- **Hoster**: verwerker, via de afspraken in het hostingcontract.
- **Externe diensten** (maildienst, externe tick, later pushdiensten en kaart): zie Privacy per externe dienst bij Afhankelijkheden.
- **Ontwikkelaar:** werkt tijdens de ontwikkeling alleen met verzonnen voorbeelddata. Beheert een vrijwilliger van de vereniging de installatie, dan werkt die onder verantwoordelijkheid van het bestuur. Een verwerkersovereenkomst is nodig als iemand van buiten de vereniging de app beheert. Kopieën van de database voor de conversie worden na afloop gewist.

**Welke gegevens, waarvoor, en hoe lang.** De bewaartermijnen zijn standaardwaarden; de vereniging past ze aan in de instellingen.

| Gegevens | Doel | Grondslag | Wie ziet het | Bewaartermijn |
| --- | --- | --- | --- | --- |
| Naam, e-mailadres | Inloggen, inroosteren, mails | Gerechtvaardigd belang: de loopgroep organiseren | Naam: leden; alles: beheerder | Tot 3 maanden na einde lidmaatschap |
| Telefoon, adres | Contact tussen loopmaatjes | Toestemming (per lid aan/uit) | Andere leden alleen met toestemming; beheerder altijd | Idem; bij intrekken meteen onzichtbaar voor leden |
| Rollen, certificaat, actief | Rechten in de app | Gerechtvaardigd belang | Beheerder | Tot einde lidmaatschap |
| Rondes en aanmeldingen | Inroosteren, statistiek | Gerechtvaardigd belang | Leden | 24 maanden, daarna zonder namen (alleen aantallen) |
| Terugmeldingen en opmerkingen | Doorgeven aan politie en gemeente | Gerechtvaardigd belang | Lopers van die ronde, coördinator, analist | 12 maanden, daarna zonder tekst (uitkomst blijft) |
| Auditlog | Verantwoording, beveiliging | Gerechtvaardigd belang | Lid: eigen; coördinator: rondes; beheerder: alles | 24 maanden; inlogregels 90 dagen |
| Meldingsvoorkeuren, push-abonnement | Herinneringen, uitnodigingen | Toestemming | Alleen de app | Tot intrekken |
| Toestemmingen en uitnodigingen vast loopmaatje (uitbreiding U1) | Gericht uitnodigen | Toestemming | De twee betrokkenen | Tot intrekken; uitnodigingen 12 maanden |
| Routes en pins (uitbreiding U9) | Dekking, plek van een incident | Toestemming | Coördinator, analist, lopers van de ronde | Routes 3 maanden; pins zo lang als de terugmelding |
| API-tokens | Toegang voor scripts en MCP | Gerechtvaardigd belang | Het lid zelf (naam, laatst gebruikt) | Tot intrekken of verlopen |
| Back-ups | Herstel na een fout | Gerechtvaardigd belang | Beheerder | Laatste 5, niet via het web bereikbaar |
| Serverlogs | Fouten opsporen | Gerechtvaardigd belang | Beheerder | 30 dagen |

**Statistiek na de bewaartermijn.** Oude rondes worden geanonimiseerd, niet verwijderd: de koppeling met leden verdwijnt en de tekst van terugmeldingen ook, maar datum, tijd, dagdeel, startpunt, aantal lopers, status en uitkomst blijven. Daarmee blijven alle statistieken over elke periode exact te berekenen, ook voor indelingen die later bedacht worden. Een aparte tabel met totalen is dus niet nodig. Wat er overblijft zijn geen persoonsgegevens meer.

**Gegevens over anderen in terugmeldingen.** Een notitie over een incident kan over een verdachte gaan. Gegevens over mogelijke strafbare feiten horen niet bij een vereniging. Daarom:

- Het formulier zegt erbij: geen namen, kentekens of signalementen; die gaan direct naar de politie (instelbare tekst, standaard 112 of 0900-8844).
- Notities zijn alleen zichtbaar voor de lopers van die ronde, de coördinator en de analist, ook via MCP.
- Korte bewaartermijn: na 12 maanden verdwijnt de tekst.

**Rechten van leden.**

- **Inzage en export:** Account → "Mijn gegevens downloaden" (`GET /api/v1/me/export`, JSON).
- **Correctie:** zelf onder Account, anders via de beheerder.
- **Toestemming intrekken:** elke toestemming apart aan en uit, met direct effect.
- **Verwijdering:** de beheerder kiest "Lid wissen". Profiel en contactgegevens verdwijnen, tokens worden ingetrokken, en in de historie staat "verwijderd lid". Het auditlog legt vast dát er gewist is, niet wat.
- **Bezwaar of vragen:** via het contact van de vereniging uit de instellingen.

**Beveiliging.**

- Alleen HTTPS; de installer weigert zonder.
- Beveiligingsheaders: de API stuurt een CSP die niets toestaat, `nosniff`, `Referrer-Policy: no-referrer` en `Cross-Origin-Opener-Policy: same-origin` (fase 1); de frontend krijgt een eigen CSP en `Permissions-Policy` (fase 5). HSTS staat in de API per installatie aan met `HSTS_MAX_AGE` (opt-in, sinds fase 2, zonder `includeSubDomains`); de installer van fase 6a kan het zetten na zijn HTTPS-controle.
- Wachtwoorden en tokens alleen gehasht opgeslagen.
- Elk geheim maakt de app zelf, met `random_bytes` (een inlogcode met `random_int`), en niemand typt het in: API-tokens, uitnodigingen, inlogcodes en het id van hun aanvraag, het installer-token, `APP_SECRET` en de tick-sleutel. Zo kan er geen kort of zelfbedacht geheim in komen. De installer schrijft `APP_SECRET` en `TICK_SECRET` in `shared/.env.local`; Onderhoud vervangt de tick-sleutel met een knop, zonder invoerveld. Het domein weigert een geheim dat niet het gegenereerde formaat heeft.
- Rechten via rollen en Voters, gecontroleerd met de matrixtest.
- Opnieuw het wachtwoord invoeren bij gevoelige acties.
- Beperkt aantal inlogpogingen.
- Geheimen in `shared/.env.local`, back-ups buiten de webmap.
- Snelle beveiligingsupdates, zie Beveiligingsupdates.

**Datalek.** `docs/admin-guide/data-breach.md` geeft de stappen:

1. Wachtwoorden en tokens resetten.
2. API en MCP uitzetten met de schakelaar.
3. In het auditlog nagaan wat er gebeurd is.
4. Bij risico voor leden binnen 72 uur melden bij de Autoriteit Persoonsgegevens, en de leden informeren.

**Voor de vereniging.**

- Standaard privacyverklaring in de app, met alle bovenstaande punten en de externe diensten. De vereniging past hem aan in de instellingen.
- Sjabloon voor een verwerkingsregister in `docs/privacy/`.
- Vóór het aanzetten van routes en kaart (uitbreiding U9): een korte risicoafweging (DPIA) en afstemming met het bestuur.

## Branding en configuratie

De repo bevat geen enkele verwijzing naar een specifieke vereniging: technische instellingen staan in `.env.local`, alles wat een vereniging zelf wil aanpassen staat in de database en is via een beheerscherm te wijzigen.

| Instelling | Waar | Voorbeeld / standaard |
| --- | --- | --- |
| Database, mail, geheime sleutels, basis-URL | `.env.local` | `DATABASE_URL`, `MAILER_DSN`, `APP_SECRET`, `TICK_SECRET` |
| Naam vereniging, korte naam | `Setting` | "Buurtpreventie" |
| Logo, favicon, app-icoon | `Setting` + upload naar `shared/uploads/brand/`, geserveerd via `GET /media/brand/{bestand}` | neutraal standaardlogo in repo |
| Kleuren (primair, accent) | `Setting` → CSS custom properties | toegankelijk contrast gecontroleerd bij opslaan |
| Afzender e-mail en ondertekening | `Setting` | valt terug op `.env` |
| Contact coördinator (naam, e-mail, telefoon) | `Setting` | leeg |
| Teksten: welkom, "wat te doen bij spoed" (112, 14040, app), privacyverklaring-URL | `Setting` (Markdown, per taal) | neutrale Nederlandse standaardtekst |
| Minimum- en maximumaantal lopers, herinnering N dagen vooraf | `Setting` | 2, 7 en 1 |
| Taal, tijdzone | `Setting` | `nl`, `Europe/Amsterdam` |

Hoe het doorwerkt:

- `GET /api/v1/config` levert het publieke deel; de frontend zet kleuren als CSS-variabelen en toont logo en teksten.
- `manifest.webmanifest` wordt uit dezelfde instellingen gegenereerd (naam, icoon, themakleur), zodat de app op een telefoon met de eigen naam op het beginscherm komt.
- E-mails blijven server-side Twig-templates met dezelfde branding.
- Vertalingen: `nl` en `en` voor API-meldingen, e-mails en frontend; een vereniging kan losse teksten overschrijven zonder vertaalbestanden aan te passen.
- Een voorbeeldconfiguratie staat in `docs/examples/`, niet in de code.

## Beveiligingsupdates en zelf bijwerken

Ja: na de eerste installatie kan de webbeheerder updates zelf installeren met één knop in het beheerscherm. Voor beveiligingspatches hoeft de ontwikkelaar meestal alleen een automatisch klaargezette update goed te keuren; de release wordt daarna vanzelf gebouwd.

Van beveiligingsadvies tot bijgewerkte site: Dependabot zet een update klaar en CI test hem; een groene patch- of minor-update wordt automatisch samengevoegd, de rest keurt de ontwikkelaar goed. Elke samengevoegde fix levert een ondertekende patchversie, en de beheerder krijgt een melding en klikt op Bijwerken.

Alleen als CI rood blijft, moet de ontwikkelaar zelf iets doen; de beheerder merkt daar niets van tot de release er is.

**Wie houdt wat bij.**

| Laag | Wie | Hoe |
| --- | --- | --- |
| Server, PHP-patches, MySQL | Hoster | Automatisch, buiten de app |
| PHP-versie kiezen (bv. 8.4 → 8.5) | Webbeheerder | Eén keuzemenu in het hostingpaneel; de app waarschuwt een half jaar voor het einde van security-support (PHP 8.4: eind 2028) |
| Symfony, Doctrine, MCP SDK, overige Composer-pakketten | Ontwikkelaar, grotendeels automatisch | Dependabot + `composer audit`; patchrelease automatisch gebouwd |
| Frontend-bibliotheek (Lit) | Ontwikkelaar | Versie in `frontend/vendor.json`, gecontroleerd in CI |
| Eigen code | Ontwikkelaar | `SECURITY.md`, privé melden via GitHub, GitHub Security Advisory (met CVE-aanvraag) |
| De app op de server bijwerken | Webbeheerder | Knop "Bijwerken" in het beheerscherm |

**Kant ontwikkelaar: eenmalig inrichten, daarna bijna vanzelf.**

- Dependabot-beveiligingsupdates voor Composer en GitHub Actions; patch- en minor-updates worden automatisch samengevoegd als CI groen is, behalve minor-updates van pakketten onder 1.0.
- **Cooldown tegen gekaapte releases:** Dependabot stelt een nieuwe Composer-versie pas voor na 7 dagen (patch of minor) of 14 dagen (major), en een nieuwe action na 7 dagen (Dependabot kent voor actions geen dagen per soort update), zonder uitzonderingen: ook een vertrouwd project kan een gekaapt account van een maintainer hebben. Beveiligingsupdates vallen niet onder die cooldown. Composer zelf (2.11 of nieuwer) houdt elke release van minder dan 24 uur oud tegen bij `composer update` en `composer require`, ook op de machine van de ontwikkelaar en ook voor een beveiligingsupdate; daarom blijft die periode kort.
- CI faalt op `composer audit`, dat `composer.lock` toetst aan de beveiligingsadviezen van Packagist ([Composer audit](https://github.com/composer/composer/pull/10798)); een wekelijkse geplande run opent een issue als er iets nieuws is.
- **Een kwetsbaarheid in PHP zelf** (zoals PHP2Shell) ziet Dependabot niet. De ontwikkelaar verhoogt dan de ondergrens in `composer.json` (`require` en `config.platform.php`) en brengt een patchrelease uit; de installer en Onderhoud controleren dezelfde ondergrens.
- **Codescan (SAST) in CI** met Opengrep (LGPL-2.1) en een eigen regelset in de repo, elke regel met een regeltest; een bevinding laat CI falen. De binary komt uit de releases van `opengrep/opengrep`, nooit via `install.sh` of PyPI. CI controleert de Cosign-handtekening tegen de release-workflow van Opengrep als vaste certificaatidentiteit en issuer, en zet de gekozen versie en de SHA-256 in de log.
- **Cooldown voor Opengrep:** er is geen Composer-pakket of image, dus Dependabot kan Opengrep niet bijwerken. CI kiest daarom bij elke run zelf de nieuwste release binnen de huidige major, met dezelfde cooldown als Dependabot: een patch of minor na 7 dagen. Een nieuwe major gaat nooit automatisch. Zodra die 14 dagen oud is, waarschuwt `composer update` de ontwikkelaar en opent de wekelijkse workflow een issue; overstappen gaat via een pull request. De waarschuwing laat `composer update` nooit falen, ook niet zonder internet.
- **Geen regels of scanners met een licentie die dit project uitsluit.** Semgrep-regels komen niet in de repo: hun licentie verbiedt verspreiden. CodeQL valt af: de licentie staat analyse alleen toe op code onder een OSI-licentie, en PolyForm Noncommercial is dat niet.
- Symfony 7.4 LTS krijgt alleen bug- en securityfixes, dus patch-updates zijn klein en weinig riskant. De overstap naar 8.4 LTS volgt in 2028 en komt bij de webbeheerder als gewone release via de knop Bijwerken.
- Elke samengevoegde fix levert automatisch een patchversie (4.0.x): zip, SHA-256 en een handtekening (libsodium) waarvan de publieke sleutel in de app zit.

**Kant webbeheerder: het scherm "Onderhoud".**

- **Dagelijkse controle** (via cron of tick): nieuwste release op GitHub, en de geïnstalleerde pakketten tegen de Packagist-adviezen. Bij een beveiligingsupdate: rode melding in beheer en een e-mail aan de beheerders.
- **Status in één oogopslag:** versie, PHP-versie en einddatum, laatste back-up, open adviezen, een test of de `Authorization`-header werkt, welke tijdtrigger actief is met de laatste run, en de laatste verzonden mail met een knop "Testmail sturen".
- **Bijwerken in één klik:** handtekening controleren → database-back-up (downloadbaar) → nieuwe versie uitpakken naast de oude in `releases/<versie>/` → migraties → root-`.htaccess` naar de nieuwe map → gezondheidscheck. Mislukt een stap, dan zet de app zelf de vorige map en de back-up terug.
- **Terugzetten** naar de vorige versie blijft beschikbaar tot de volgende update. Daarom zijn migraties in patchversies altijd terugdraaibaar: alleen toevoegen, nooit kolommen weggooien.
- **Data staat buiten de releases**, in `shared/`: `.env.local`, uploads, back-ups en logs. Een update raakt die nooit. Elke release leest `.env.local` uit `shared/` via de `dotenv_path`-optie van Symfony Runtime; cache staat per release, logs in `shared/`.
- Alleen beheerders mogen bijwerken, en ze voeren eerst opnieuw hun wachtwoord in.

**Wat toch kennis of hulp vraagt.**

- De eerste installatie: eenmalig, met een stappenhandleiding (FTP-upload + web-installer, circa 30 minuten).
- Grote versies (v4 → v5) en een hogere minimale PHP-versie: de app kondigt ze vooraf aan, met een eigen handleiding; de ontwikkelaar is vangnet, geen vereiste.
- Hosts die PHP niet laten schrijven in de app-map: dan dezelfde stappen handmatig, via FTP volgens de handleiding.
- Een lek zonder patch: de beheerder kan MCP en API-tokens met één schakelaar uitzetten tot de fix er is.

**Handleiding voor de beheerder** in `docs/admin-guide/`, in gewone taal en met schermafbeeldingen: bijwerken, terugzetten, back-up terugzetten, PHP-versie wijzigen in gangbare hostingpanelen, en wat te doen bij een beveiligingsmelding. In fase 6b loopt een webbeheerder zonder dev-achtergrond hem zelf door; wat onduidelijk is, wordt herschreven.

## Overstap naar Symfony 8.4 LTS

v4 start op 7.4 LTS en stapt in 2028 over naar 8.4 LTS (verwacht november 2027), ruim vóór de 7.4-bugfixes stoppen in november 2028. Wat nu al gebeurt, maakt die overstap een gewone release.

**Wat 8.x brengt dat hier van pas komt.** Tot de overstap bouwt v4 dit zelf achter een kleine eigen interface, gemarkeerd met `@upgrade-8.4`, zodat het later één vervanging is.

| Symfony 8.x | Waarvoor in dit project | Tot dan op 7.4 |
| --- | --- | --- |
| Sudo mode: opnieuw inloggen vóór gevoelige acties ([8.2](https://symfony.com/blog/new-in-symfony-8-2-sudo-mode)) | Bijwerken, terugzetten, rollen wijzigen, tokens aanmaken | Eigen "wachtwoord opnieuw"-controle |
| `#[Lock]` op controllers tegen gelijktijdige verzoeken ([8.2](https://symfony.com/blog/category/living-on-the-edge?page=2)) | Twee lopers tegelijk op de laatste plek; dubbel klikken op Bijwerken | Lock-component met bestandsopslag |
| `#[Encrypted]` voor Doctrine-velden ([8.2](https://symfony.com/blog/category/living-on-the-edge?page=2)) | Telefoon en adres versleuteld opslaan | Velden onversleuteld, achter toestemming |
| Eenmalige ondertekende URL's ([8.2](https://symfony.com/blog/category/a-week-of-symfony?page=1)) | Niet nodig: een uitnodiging werkt één keer en moet dus opgeslagen zijn, en inloggen per mail gaat met een code | Uitnodiging en inlogcode als eigen entiteit, met hash en verloopdatum |
| `#[Serialize]` voor controller-uitvoer en betere request-mapping, ook uploads in DTO's ([8.1](https://symfony.com/blog/symfony-8-1-curated-new-features)) | Dunnere controllers, logo-upload | Serializer handmatig aanroepen |
| SSRF-allow-list in HttpClient ([8.1](https://symfony.com/blog/symfony-8-1-curated-new-features)) | Updater haalt alleen bij GitHub op | Vaste host-check in de updater |

**Nu al voorbereiden (vanaf fase 0).**

- **Nul deprecations:** PHPUnit faalt op deprecation-meldingen, plus PHPStan-deprecationregels.
- **Vooruitkijk-job in CI:** een wekelijkse, niet-blokkerende run met de nieuwste Symfony 8 en PHP 8.5. Wat daar breekt, komt meteen als issue binnen.
- **Alleen afhankelijkheden die Symfony 8 al ondersteunen**, zoals `symfony/mcp-bundle` (7.3 én 8).
- **Rector met de Symfony-regels** in de repo, om de overstap grotendeels automatisch te doen.
- **Alleen attributen**, geen verouderde configuratievormen.

**De overstap zelf (begin 2028).**

1. Wachten tot 8.4 een paar patchreleases heeft (rond februari 2028).
2. Op een branch: `extra.symfony.require` naar `8.4.*`, `composer update`, Rector, tests, alle `@upgrade-8.4`-plekken vervangen.
3. Proefinstallatie, daarna een gewone release (v4.x) via de knop Bijwerken; terugzetten blijft mogelijk.
4. In dezelfde periode PHP naar 8.5, omdat de security-ondersteuning van PHP 8.4 eind 2028 stopt; de app waarschuwt de beheerder vooraf.

## Implementatieplan

Fase 0 tot en met 6b vormen de MVP: na fase 2 is de API bruikbaar, na fase 6b is de MVP klaar en kan de uitrol beginnen. Daarna volgen de uitbreidingen U1 tot en met U11: extra functies, elk los aan of uit te zetten, in volgorde van aanpak (zie Uitbreidingen na de MVP). De overstap van bestaande installaties is een apart project. Elke fase levert iets testbaars op en krijgt bij de start een eigen gedetailleerd taakplan (test eerst, kleine commits).

Fase 3, 4 en 5 hangen alleen van de API af; MCP (fase 4) gaat vóór de frontend (fase 5).

### Fase 0 — Basis

- [x] Oude repo afgerond (verwijzing in de README, tag `v3-mit-final`, hernoemd naar `buurtpreventie-legacy`); nieuwe repo `zabuto/buurtpreventie` met `main` en `develop` (zie Werkwijze), en `LICENSE` (PolyForm NC + `Required Notice:`) vanaf de eerste commit.
- [x] Symfony 7.4 LTS-skelet, PHP 8.4, modulaire mapindeling uit Codestructuur, PHPat-regels in PHPStan, docs/glossary.md, docs/architecture.md met voorbeeld, ADR 0001 over de structuur, en de generator app:make:use-case.
- [x] CI (GitHub Actions): PHPStan level max, Mago (opmaak, lint en analyse), PHPUnit, `composer audit`, controle op deprecations, wekelijkse vooruitkijk-job met de nieuwste Symfony 8 en PHP 8.5, Rector met Symfony-regels; Dependabot met automatisch samenvoegen van groene patch- en minor-updates (minor niet onder 1.0), wekelijkse audit-run, SECURITY.md en privé melden van kwetsbaarheden aan.
- [x] `AGENTS.md` + `CLAUDE.md`, en `docs/` met `getting-started`, `configuration`, `architecture`, `operations`, `testing`, `gotchas`, `writing`, `code-style`.
- [x] Dependabot-cooldown van 7, 7 en 14 dagen voor patch-, minor- en major-updates van Composer en 7 dagen voor actions, zonder uitzonderingen, en een Composer-cooldown van 24 uur (zie Beveiligingsupdates en zelf bijwerken).
- [x] Codescan in CI met Opengrep en een eigen regelset met regeltests, als verplichte check op `develop`; CI kiest de Opengrep-versie zelf binnen de huidige major, met cooldown, en `composer update` en de wekelijkse workflow melden een nieuwe major (zie Beveiligingsupdates en zelf bijwerken).

**Klaar als:** CI groen op een lege app; `docs/getting-started.md` brengt een nieuwe dev in één keer tot een draaiende lokale omgeving.

### Fase 1 — Domein en use cases

- [x] Entiteiten in `src/*/Domain` met Doctrine-mapping volgens het verbeterde model; nieuw zijn ook `ApiToken`/`Setting`. De conversie uit v3 volgt in fase 5b.
- [x] Use cases in UseCase/ per module, elk met invoerklasse, handler en later de adapters (zie Codestructuur): `SignUpForRound`, `WithdrawFromRound`, `CreateRound`, `ChangeRound`, `ReportRoundResult`, `AddRoundComment`, `SendReminders`, `NotifyBuddyNeeded`, …
- [x] Voters per onderwerp en de module Audit (elke schrijvende handler legt een auditregel vast, met test); regels uit de oude `WalkService` (minimum lopers, inactieve lopers uit toekomstige rondes, eigen melding verwijderen) als unit tests vastgelegd; fixture-sets demo, test en edge (zie Voorbeelddata); de set v3 volgt met de conversie in fase 5b.
- [x] Database: lokaal MariaDB 10.11 (`compose.yaml`), omdat shared hosting meestal MariaDB biedt. CI draait de migraties vanaf een lege database en de databasetests op MariaDB 10.11 én MySQL 8.4, omdat niet elke hoster MariaDB heeft en Doctrine niet alles gelijktrekt: gegenereerde migraties, collation en gelijktijdige schrijfacties kunnen per database verschillen. Standaard-tabelopties in `doctrine.yaml`: `utf8mb4` met `utf8mb4_unicode_ci`, dat op beide bestaat. Wat mensen intypen en waarop de app zoekt (e-mailadressen, namen) is hoofdletterongevoelig: `Anke@example.org` en `anke@example.org` zijn hetzelfde adres. Namen zijn ook accentongevoelig: zoeken op "Jose" vindt "José". De app maakt in PHP een zoeksleutel, vóór opslag en vóór het zoeken, zodat zoeken en unieke sleutels niet van de collation afhangen; de weergave blijft zoals getypt. Bij leden zijn e-mailadres en weergavenaam uniek: twee leden mogen dezelfde volledige naam hebben, maar in rondes en lijsten staan ze met een eigen weergavenaam ("Jan H." en "Jan B."). Geheimen (inlogcode, uitnodiging, API-token) zijn de uitzondering: die worden exact vergeleken, als hash. Geen eigen SQL, behalve in een repositorymethode met een integratietest op beide. Coördinaten zijn twee getallen in WGS84 (`DECIMAL(9,6)`), geen spatial types.

**Klaar als:** alle domeinregels uit de oude app hebben een test, en de migraties en databasetests draaien op MariaDB 10.11 én MySQL 8.4.

**Ter goedkeuring in het taakplan:** `dama/doctrine-test-bundle` als nieuwe dev-afhankelijkheid. Elke databasetest rolt dan terug, en controles filteren op het id van wat de test zelf aanmaakt, nooit op de hele tabel. De test voor twee lopers die tegelijk aanmelden (review-focus 1) zet DAMA voor zichzelf uit: hij werkt met twee eigen verbindingen die echt committen, en ruimt zijn rijen zelf op. Het schema staat er vóór de tests, omdat MariaDB bij DDL stil commit.

### Fase 2 — REST-API v1

- [x] Auth: sessie met eigen authenticators voor wachtwoord, code per mail en uitnodiging (geen `json_login` en geen inloglink), CSRF en ingelogd blijven (90 dagen); authenticator voor API-tokens (zie hieronder); `/me/tokens`; aan- en afmelden namens een loper (coördinator); auditlog-endpoints. Vastgelegd in [ADR 0006](../adr/0006-authentication.md); de uitwerking staat in `docs/plans/phase-2.md`.
- [x] Authenticator voor `Authorization: Bearer <token>` (vastgelegd op 15 november 2026):
  - hij neemt elk verzoek op de API-firewall aan, zodat een ontbrekend token net zo geweigerd en gelogd wordt als een fout token;
  - hij zoekt het token op de SHA-256-hash; het token zelf staat nergens, en draagt geen inhoud en geen controlegetal;
  - één `match` met elke reden om te weigeren: onbekend, ingetrokken, verlopen, lid gedeactiveerd of gewist (review-focus 4: het token werkt meteen niet meer);
  - een weigering is een 401 zonder body, met `WWW-Authenticate: Bearer`; de reden gaat alleen naar ons log, in woorden die het datamodel niet prijsgeven, en de exceptie draagt een algemene melding;
  - bij succes: `markUsed()` op het token en `ExecutionContext` gevuld met het lid, zijn rollen en kanaal `api` (of `mcp` in fase 4), zodat er één bron is voor wie de actie uitvoert;
  - geen JWT: dat is niet meteen in te trekken zonder toch bij elk verzoek op te zoeken, de inhoud is leesbaar, en één gelekte sleutel ondertekent elk token.
  Het werd een eigen authenticator, geen `access_token` met handler: die slaat een verzoek zonder header over.
- [x] **Afwijking: scopes per verzoek, niet in de Voters** (beslissing 17 van `phase-2.md`). Een listener op de tokenfirewall eist `read` voor `GET` en `HEAD`, `write` voor de andere methodes en `admin` voor `/api/v1/admin/`; de Voters gaan alleen over rollen. Rol én scope moeten kloppen, en één test dekt elke scope.
- [x] **Afwijking: Identity vindt een lid via `Shared\Domain\MemberDirectory`, niet via een use case** (beslissing 4 van `phase-2.md`): een use case vraagt een Voter, en bij inloggen is nog niemand ingelogd. De user provider leest rollen en status bij elk verzoek opnieuw.
- [x] Alle endpoints uit het API-ontwerp, `problem+json`-fouten, OpenAPI via NelmioApiDocBundle. De spec noemt per operatie de problem-antwoorden; de geslaagde antwoorden met hun uitvoer volgen (`phase-2.md`, T14).
- [x] Functionele tests per endpoint plus één matrixtest rol × actie (`HttpMatrixTest`), naast de matrix op de Voters.
- [x] Opengrep-regelset uitgebreid voor wat deze fase toevoegt: verzoekinvoer naar SQL en bestandspaden, inloggen en CSRF; elke regel met een regeltest.

**Klaar als:** de OpenAPI-spec dekt elk endpoint, de matrixtest is groen, en `curl` met een token kan een lid aanmelden voor een ronde.

### Fase 3 — Branding en configuratie

- [ ] `Setting`-repository met standaardwaarden, `/admin/settings`, logo-upload, `GET /config`, `manifest.webmanifest`.
- [ ] E-mailtemplates (uitnodiging, inlogcode, herinnering, nieuwe ronde zoekt loopmaatje, loopmaatje afgemeld) met branding en `nl`/`en`; altijd met een tekstversie, geen informatie die alleen in een afbeelding staat, en zo min mogelijk persoonsgegevens in de mail zelf.
- [ ] Berichten gaan pas na de commit uit, niet binnen de transactie; een test bewijst dat een teruggedraaide wijziging niets verstuurt.
- [ ] Hashketen in het auditlog (beslissing 16 van fase 1, besloten op 16 november 2026), vóór de conversie van fase 5b, die de eerste echte regels schrijft. Hij maakt geknoei zichtbaar voor wie de database kan wijzigen maar niet bij de bestanden kan, zoals bij een SQL-injectie of een gelekte databaselogin; wie ook bij de bestanden kan, houdt hij niet tegen.
  - **HMAC-SHA-256 met een eigen sleutel** `AUDIT_SECRET` in `shared/.env.local`, buiten de database en los van `APP_SECRET`, zodat het vervangen van `APP_SECRET` de keten niet breekt. Zonder sleutel is een geldige keten niet na te maken. De installer van fase 6a maakt de sleutel aan, zoals de andere geheimen.
  - **De hash dekt alleen wat nooit verandert**: id, tijdstip, lid-id, actie, onderwerp en kanaal, plus de hash van de vorige regel. De naam van de uitvoerder valt erbuiten, zodat wissen hem kan vervangen door "verwijderd lid".
  - **De gewijzigde velden krijgen een eigen hash**, en de keten loopt over die hash. Wissen haalt de inhoud weg en laat de hash staan: van die regel is de inhoud dan niet meer te controleren, de keten wel.
  - **Een ankerrij** bewaart de hash van de laatst opgeruimde regel, zodat de keten na opruimen volgens de bewaartermijn vanaf daar te controleren blijft.
  - **Een slot op één rij met de laatste hash**, als laatste stap van de transactie, zodat twee gelijktijdige schrijfacties nooit aan dezelfde vorige regel koppelen. Het zet alle schrijfacties achter elkaar, wat bij tientallen gebruikers niets uitmaakt; omdat het altijd als laatste komt, kan het niet vastlopen op het slot van een ronde.
  - `app:audit:verify` meldt "intact tot en met …" of de eerste regel die niet klopt; Onderhoud (fase 6b) toont hetzelfde.
  - Tests: een gewijzigde, verwijderde of tussengevoegde regel valt op; wissen en opruimen breken de keten niet; twee gelijktijdige schrijfacties op MariaDB en MySQL geven een kloppende keten.

**Klaar als:** een tweede vereniging alleen via `/admin/settings` en de logo-upload (API) een eigen naam, logo, kleuren en teksten krijgt, zonder codewijziging; het beheerscherm in fase 5 gebruikt dezelfde endpoints.

### Fase 4 — MCP-server

- [ ] `symfony/mcp-bundle` + `mcp/sdk` (minor vastgepind), tools als `…Tool`-klasse in de map van de use case: dunne wrappers om de handler, met een pariteitstest tegen de REST-controller.
- [ ] Use case `RoundStatistics` met de tool `round_statistics` (het scherm volgt in uitbreiding U5); token-scopes, rate limit, audit `via: mcp`, DNS-rebinding-instelling.
- [ ] Tests met de SDK-client in PHPUnit: elke tool, en elke tool geweigerd zonder juiste scope of rol.

**Klaar als:** de MCP Inspector alle tools ziet en Claude Code met een token `staffing_gaps` en `sign_up_for_round` kan gebruiken.

### Fase 5 — Frontend

**Eerst ontwerp.** Vóór de Lit-proef komen er schetsen op telefoonformaat van de vijf belangrijkste schermen: Zoekt een loopmaatje, ronde (aan- en afmelden), terugmelden, Mijn rondes en inloggen. Twee lopers, onder wie een minder ervaren loper, kijken ernaar; pas daarna wordt er gebouwd.

- [ ] `frontend/`: buildless app (ES-modules, gevendorde Lit-bundel, geen importmap), die alleen `/api/v1` aanroept. Begin met een proef van één scherm (inloggen en "Zoekt een loopmaatje") om Lit te leren kennen, met de gebruikte patronen in docs/frontend.md.
- [ ] Schermen: inloggen/wachtwoord, kalender, ronde (aan-/afmelden), gelopen rondes + terugmelding, account/toestemming/tokens, leden, analyse, beheer.
- [ ] Mobiel eerst en WCAG 2.2 AA (zie Frontend); PWA met manifest, service worker en offline lezen; axe-core in CI.
- [ ] Beveiligingsheaders voor de statische frontend via `.htaccess`: CSP `default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'`, en `Permissions-Policy: camera=(), microphone=(), geolocation=()`. Geen inline scripts of stijlen, dus ook geen `'unsafe-inline'`.

**Klaar als:** elk scherm van de oude app een tegenhanger heeft en een loper op een telefoon in drie tikken kan aanmelden, ook op een iPhone met iOS 15, en de minst ervaren testloper dat zonder hulp doet. De evaluatie van Lit staat in een ADR.

**Ter goedkeuring in het taakplan:** Renovate om de versies van gevendorde bibliotheken (Lit, later Leaflet) te volgen, met dezelfde cooldown; Dependabot ziet gevendorde bestanden niet. Alleen zelf gedraaid in GitHub Actions, nooit de gehoste app van Mend, omdat die schrijfrechten op de repo krijgt (ADR 0004).

### Fase 5b — Conversie v3 → v4

Na fase 2 tot en met 5 staat het model dat uitgebracht wordt; de conversie volgt dat model, en de web-installer van fase 6a moet haar kunnen starten.

- [ ] `app:import-v3` met `--dry-run`, volgens Conversie v3 → v4 in Domeinmodel en API-ontwerp, met de beslissingen uit het taakplan van fase 1 (tweede verbinding `legacy`, alleen een lege v4-database, dry-run in een teruggerolde transactie, koppeltabel `legacy_id_map`).
- [ ] Fixture-set `v3`: een verzonnen v3-database met soft-deleted rijen, een verwijderd lid in de historie, een tijd rond de wintertijdwissel, leden met telefoon én mobiel, en een ronde boven het maximum.
- [ ] Review-focus 2 voor de conversie: een v3-tijd in het dubbele uur geeft een waarschuwing en een vaste keuze.
- [ ] Integratietests op MariaDB 10.11 en MySQL 8.4; CI converteert de set `v3`; een korte pagina in `docs/admin-guide/`.

**Klaar als:** `app:import-v3 --dry-run` en `app:import-v3` foutloos draaien op de verzonnen v3-set, de aantallen kloppen, voorbeeldlopers inloggen met hun v3-wachtwoord, en een tweede conversie naar een lege database hetzelfde oplevert.

### Fase 6a — Installeren en releasen

- [ ] Release-workflow: zip met `vendor/`, frontend en `.htaccess` (pad of subdomein, `Authorization`-header) bij elke GitHub Release, met SHA-256 en libsodium-handtekening; patchversies automatisch na een samengevoegde fix.
- [ ] Web-installer (eenmalig token): controle op HTTPS, PHP-versie, schrijfrechten en een map binnen een andere applicatie; daarna migraties, eerste beheerder en optioneel voorbeelddata.
- [ ] Mapindeling `releases/` + `shared/`; `bin/scheduler.php` voor cron en `POST /api/v1/system/tick`, voor herinneringen, mails over open rondes en opruimen volgens bewaartermijnen.
- [ ] HSTS (`Strict-Transport-Security: max-age=31536000`) zodra de installer HTTPS heeft gecontroleerd, zonder `includeSubDomains`: de app staat vaak op een subdomein van een domein met andere sites, die anders ook HTTPS moeten hebben.
- [ ] Proefinstallatie op shared hosting, op een subdomein én in een pad, zonder SSH, met cron en testmail.

**Klaar als:** installatie volgens `docs/getting-started.md` lukt op shared hosting zonder SSH, op een subdomein en in een pad; herinneringen gaan via cron of tick; de testmail komt aan.

### Fase 6b — Bijwerken, onderhoud en overdracht

- [ ] Scherm Onderhoud: versie, PHP, certificaat, tijdtrigger en laatste mail; updatecheck en beveiligingsadviezen; knoppen Testmail sturen, Nu opruimen en Voorbeelddata wissen.
- [ ] Updater: handtekening controleren, back-up, nieuwe release naast de oude, migraties, omzetten, gezondheidscheck, automatisch terugzetten bij een fout; knop Terugzetten.
- [ ] Overdracht: updatebron en vertrouwde sleutels zijn te wijzigen via een ondertekende overdrachtsrelease, en met de hand in Onderhoud.
- [ ] `docs/admin-guide/` in gewone taal: installeren, bijwerken, terugzetten, back-up terugzetten, PHP-versie, cron, testmail, datalek.

**Klaar als:** een webbeheerder zonder dev-achtergrond met alleen `docs/admin-guide/` een update én een terugzetting uitvoert op de proefinstallatie, een overdrachtsrelease naar een proef-fork werkt, en de overdrachtstoets uit Overdraagbaarheid is gedaan. Daarmee is de MVP klaar.

### Uitbreidingen na de MVP (U1–U11)

Extra functies, geen onderdeel van de MVP. Ze komen pas na de uitrol van fase 6b, elk met een eigen taakplan, en elke uitbreiding is per vereniging los aan of uit te zetten.

Geen enkele uitbreiding is nodig voor de beveiliging: de MVP is zonder ze compleet. Elke uitbreiding heeft een feature flag (fase 3) met een standaard: wat een nieuw risico voor beveiliging of privacy meebrengt, staat standaard uit en zet de beheerder per vereniging aan; de rest staat aan en kan uit. De volgorde:

1. **Eerst wat lopers snel helpt**, want zij gebruiken de app het meest, en een loopmaatje vinden is het kernprobleem.
2. **Dan wat coördinator en analist helpt.**
3. **Als laatste wat zwaar is of nog uitgewerkt moet worden**, en wat nieuwe risico's voor beveiliging of privacy meebrengt.

Binnen elke groep gaat waarde vóór moeite.

| Uitbreiding | Inhoud | Voor | Waarde | Moeite | Beveiliging en privacy | Standaard | Waarom op deze plek |
| --- | --- | --- | --- | --- | --- | --- | --- |
| U1 | Vast loopmaatje uitnodigen | lopers | hoog | middel | Alleen met toestemming per persoon; geen uitnodigingen via MCP | aan | Pakt het kernprobleem aan: ruim een kwart van de rondes vindt geen loopmaatje |
| U2 | iCal-feed met eigen rondes | lopers | middel | klein | Een geheime agenda-URL, in te trekken; de agenda-app haalt de feed op, dus alleen weergavenamen, geen contactgegevens | uit, per lid aan te zetten | Snelle winst: rondes in de eigen agenda, met de herinnering van de telefoon, ook zonder cron |
| U3 | Reageren op de terugmelding | lopers | laag tot middel | klein | Geen nieuwe risico's | aan | Snelle winst: de andere lopers laten zien of ze het met de ene terugmelding eens zijn |
| U4 | Pushmeldingen | lopers | middel | middel | Nieuwe afhankelijkheid en pushdiensten van derden; alleen als het lid het aanzet | uit | Maakt uitnodigingen (U1) en herinneringen sneller |
| U5 | Statistieken voor analist | analist, coördinator | middel tot hoog | klein | Alleen totalen, geen cijfers per loper | aan | De use case bestaat al sinds fase 4 (MCP); alleen het scherm komt erbij |
| U6 | Offline terugmelden | lopers | laag tot middel | middel | Geen nieuwe risico's | aan | Handig bij slecht bereik; terugmelden kan ook thuis |
| U7 | Terugmelden met spraak | lopers | middel | klein tot middel | De browser stuurt spraak naar zijn maker; opt-in, de server krijgt alleen tekst | uit | Dicteren via het toetsenbord kan al in fase 5; de route via MCP volgt na OAuth (U10) |
| U8 | Vaste export voor politie en gemeente | coördinator | nog te bepalen | klein tot middel | Geen persoonsgegevens in de export | aan | Eerst met de coördinator uitwerken welke cijfers, in welke vorm en hoe vaak |
| U9 | Route en incidentkaart | coördinator, analist | middel | groot | Locatie is gevoelig: toestemming per loper, DPIA en afstemming met het bestuur vooraf | uit | Veel werk en privacy-afwegingen |
| U10 | OAuth voor MCP-clients | lopers via een AI-app | laag | groot | Een eigen autorisatieserver is het grootste nieuwe aanvalsoppervlak; per vereniging aan of uit | uit | Maakt MCP bruikbaar met inloggen in plaats van een token; tot dan werkt MCP met tokens |
| U11 | No-shows bijhouden | coördinator | middel | klein | Een oordeel over betrouwbaarheid is gevoelig: opt-in per vereniging; alleen coördinator en het lid zelf, vaste bewaartermijn, inzage en bezwaar | uit | Helpt de coördinator een loper aan te spreken die vaak niet komt, maar vraagt eerst een privacy-afweging |

### Uitbreiding U1 — Vast loopmaatje uitnodigen

Een loper kan iemand met wie hij vaak loopt vragen of die weer meeloopt, maar alleen als die ander dat uitdrukkelijk heeft toegestaan. Standaard kan niemand een ander uitnodigen; de vereniging kan de functie in de instellingen uitzetten.

- [ ] **Toestemming per persoon (opt-in):** lid B zet in zijn account "A mag mij uitnodigen" aan. Dat kan op eigen initiatief, of na een verzoek van A ("A wil je kunnen uitnodigen voor rondes. Toestaan?"). Een verzoek dat B laat liggen, vervalt na 30 dagen; A ziet nooit of B weigerde, alleen of het is toegestaan.
- [ ] **Altijd terug te draaien:** B kan toestemming per persoon intrekken of alle uitnodigingen pauzeren ("even niet"). A krijgt daar geen bericht over.
- [ ] **Suggesties voor A:** "Vaak met je gelopen" op basis van gezamenlijke rondes in het afgelopen halfjaar. Dat zijn gegevens die A in de rondes al kon zien.
- [ ] **Uitnodigen:** bij een eigen ronde die een loopmaatje zoekt, tikt A op "Vraag B mee". B krijgt een mail (en later pushmelding) met de knoppen "Ik loop mee" en "Kan deze keer niet". "Ik loop mee" meldt B direct aan.
- [ ] **Geen druk:** hooguit één uitnodiging per ronde per maatje, hooguit twee per week per maatje, geen pushmeldingen 's nachts. A ziet alleen "uitgenodigd", "loopt mee" of "kan niet", zonder reden.
- [ ] **Model:** in module `Patrol` de entiteiten `BuddyPermission` (wie mag wie uitnodigen, gepauzeerd tot) en `BuddyInvite` (ronde, van, aan, verstuurd, antwoord); endpoints `GET /me/buddies`, `POST /me/buddies/requests`, `PUT/DELETE /me/buddy-permissions/{memberId}`, `POST /rounds/{id}/invites`, `POST /invites/{id}/response`.
- [ ] **Auditlog en privacy:** uitnodigingen en toestemmingen komen in het auditlog, zichtbaar voor de twee betrokkenen. Geen statistieken per persoon; niet via MCP in de eerste versie, zodat een AI-client geen uitnodigingen kan versturen.
- [ ] **Voorbeelddata:** de `demo`-set krijgt een paar vaste duo's met toestemming, een openstaand verzoek en een gepauzeerd lid.

**Klaar als:** een loper een vast loopmaatje met toestemming in twee tikken uitnodigt, het maatje met één tik meeloopt, en een lid zonder toestemming nooit een uitnodiging krijgt.

### Uitbreiding U2 — iCal-feed met eigen rondes

- [ ] Persoonlijke, alleen-lezen agenda-URL per lid (`/ical/{token}.ics`) met de eigen komende rondes, wie meeloopt en of er nog een loopmaatje nodig is.
- [ ] Eigen token, los van de API-tokens: alleen lezen, in te trekken en te vernieuwen onder Account.
- [ ] Geen contactgegevens in de feed, alleen namen van loopmaatjes.
- [ ] Uitleg in `docs/user-guide/` voor de agenda op iPhone, Android en in Outlook.

**Klaar als:** een loper zijn rondes in de agenda van zijn telefoon ziet, en wijzigingen doorkomen bij de volgende verversing van die agenda (dat bepaalt de agenda-app zelf).

### Uitbreiding U3 — Reageren op de terugmelding

Een ronde heeft één terugmelding, van één loper of de coördinator. De andere lopers van die ronde geven er een duim omhoog of omlaag bij, zodat zichtbaar is of ze het eens zijn zonder een tweede terugmelding.

- [ ] Per loper van de ronde één reactie (eens of oneens) op de terugmelding, te wijzigen of in te trekken; in het auditlog.
- [ ] Coördinator en analist zien de reacties bij de ronde; een terugmelding met een "oneens" valt op in het overzicht.

**Klaar als:** een loper die meeliep met één tik laat zien of hij het met de terugmelding eens is.

### Uitbreiding U4 — Pushmeldingen

- [ ] Web Push via de service worker, alleen als het lid het aanzet, per soort: herinnering, nieuwe open ronde, uitnodiging van een loopmaatje.
- [ ] Werkt op Android en desktop; op een iPhone pas vanaf iOS 16.4 en alleen als de app op het beginscherm staat. E-mail blijft daarom altijd het hoofdkanaal.
- [ ] Korte tekst zonder namen ("Je loopt morgen om 20:00"), geen meldingen 's nachts.
- [ ] Herinneringen gaan via de tijdtrigger; meldingen bij een uitnodiging of open ronde gaan direct. Zonder cron dus alleen de directe meldingen.
- [ ] Eén kleine Web Push-bibliotheek als afhankelijkheid, vastgelegd in een ADR.

**Klaar als:** een loper met meldingen aan een uitnodiging en een herinnering op zijn telefoon krijgt, en een lid zonder toestemming niets.

### Uitbreiding U5 — Statistieken voor analist

Er komt een scherm Statistieken voor analist en coördinator, met exacte cijfers uit de database in plaats van tellingen uit schermafbeeldingen. Er zijn geen externe diensten voor nodig.

- [ ] **Grafieken:** per maand gelopen tegenover niet gelopen (geen loopmaatje), met percentage; per weekdag; per dagdeel; uitkomsten en incidenten per maand; de komende weken met rondes die nog een loopmaatje zoeken.
- [ ] **Filters:** periode, dagdeel en startpunt, en vergelijken met de vorige periode.
- [ ] **API en MCP:** `GET /api/v1/stats/rounds?from=&to=&groupBy=month|weekday|daypart` en `GET /api/v1/stats/outcomes`, gebouwd op de use case `RoundStatistics` uit fase 4, die ook de MCP-tool `round_statistics` voedt.
- [ ] **Getekend als eigen SVG-componenten in Lit**, zonder grafiekbibliotheek. Elke grafiek heeft een titel die de conclusie zegt, een tabelweergave voor schermlezers, en kleur is nooit de enige drager (WCAG).
- [ ] **Privacy:** alleen totalen, geen ranglijsten of cijfers per loper. Een loper ziet alleen zijn eigen aantallen onder Mijn rondes.
- [ ] **Export:** de getoonde cijfers als CSV; een vaste rapportage voor politie en gemeente volgt eventueel in uitbreiding U8.
- [ ] **Snel op shared hosting:** tellingen als SQL-aggregaties, een dag gecachet in de bestandscache.

**Klaar als:** de analist de grafieken voor elke periode zelf maakt, de cijfers kloppen met een handtelling op de voorbeelddata, en een schermlezer de conclusie en de tabel voorleest.

### Uitbreiding U6 — Offline terugmelden

- [ ] Een terugmelding zonder bereik gaat in een wachtrij in de browser en wordt verstuurd zodra er weer verbinding is; de loper ziet "Wordt verstuurd zodra je weer bereik hebt".
- [ ] Elke terugmelding heeft een eigen ID, zodat dubbel versturen nooit dubbele meldingen geeft.
- [ ] Versturen gebeurt bij het volgende openen van de app, dus ook op iOS 15 zonder achtergrondsynchronisatie.

**Klaar als:** een terugmelding die in vliegtuigmodus is gedaan, na het uitzetten daarvan precies één keer aankomt.

### Uitbreiding U7 — Terugmelden met spraak (later)

Een loper zegt na de ronde wat er was, in plaats van te typen. Dat helpt onderweg, met koude handen, en voor lopers die moeilijk typen. Opt-in per vereniging, en de browserroute ook per lid.

- [ ] **Dicteren via het toetsenbord:** het notitieveld is groot genoeg en de uitleg wijst op de microfoonknop van het telefoontoetsenbord (iPhone en Android). Dat vraagt geen code en werkt ook op iOS 15; de tip kan al in fase 5 mee.
- [ ] **Microfoonknop in de app:** spraakherkenning van de browser, alleen waar de browser dat ondersteunt; anders is de knop er niet. De tekst komt eerst in het veld, de loper controleert en verstuurt zelf. Vooraf een melding dat de browser de spraak verwerkt, bij Chrome via Google.
- [ ] **Via MCP:** de loper spreekt in een AI-app, bijvoorbeeld "Ronde van vanavond gelopen, niets bijzonders, lantaarnpaal kapot bij de school". De AI-app maakt daar een uitkomst en notitie van en roept `report_round_result` aan, na bevestiging door de loper. Voor lopers op de telefoon is OAuth nodig (uitbreiding U10). De terugmelding is daarna in de app aan te passen of te verwijderen; het auditlog noteert kanaal `mcp`.
- [ ] **Bescherming in de notitie:** lijkt iets op een kenteken, telefoonnummer of e-mailadres, dan waarschuwt de app ("Geef dit liever direct door aan de politie"), in het scherm én in de uitvoer van de MCP-tool.
- [ ] **Geen opnames:** de server krijgt alleen tekst, nooit audio.

**Klaar als:** een loper zonder typen een terugmelding plaatst, in de app of via een AI-app, en een kenteken in de notitie een waarschuwing geeft.

### Uitbreiding U8 — Vaste export voor politie en gemeente (later, nog uit te werken)

Nog open: of de coördinator een vaste rapportage wil, en zo ja welke. Vóór het bouwen eerst met de coördinator vastleggen:

- [ ] Welke cijfers en welke periode (bijvoorbeeld per maand: gelopen rondes, uitkomsten, incidenten).
- [ ] In welke vorm: CSV, een afdrukbare pagina of PDF.
- [ ] Hoe vaak, en of de export automatisch per mail moet (dat vraagt dan een tijdtrigger).

De bouwstenen zijn er dan al: de use case `RoundStatistics` (fase 4), het statistiekenscherm (uitbreiding U5) en de MCP-prompt `monthly_report`. Uitgangspunt: geen persoonsgegevens in de export.

### Uitbreiding U9 — Route en incidentkaart

Een opt-in-functie achter een feature flag, die pas start als fase 0 t/m 6 draaien: standaard uit, de beheerder zet hem per vereniging aan in de instellingen, en daarna kiest elke loper zelf of hij meedoet.

- [ ] **Toestemming per loper:** apart vinkje "mijn route mag worden vastgelegd", standaard uit en altijd in te trekken. Pas na toestemming verschijnt de knop "Route opnemen".
- [ ] **Locatie in `Permissions-Policy`:** `geolocation=(self)` alleen als de uitbreiding aan staat; uit fase 5 staat locatie uit.
- [ ] **Route opnemen tijdens de ronde** via de locatie van de browser, alleen zolang de app open en het scherm aan is: een webapp kan niet betrouwbaar op de achtergrond volgen. Duidelijke melding "Route wordt opgenomen" met een stopknop.
- [ ] **Alternatief zonder volgen:** de route achteraf op de kaart intekenen of alleen de gelopen straten aantikken. Werkt ook op oude telefoons en kost geen batterij.
- [ ] **Pin bij een melding:** bij een terugmelding "Locatie toevoegen", met de huidige plek of door op de kaart te tikken, zodat de coördinator weet waar het was.
- [ ] **Kaart** met Leaflet (gevendord, geen buildstap). De kaartondergrond is instelbaar; voor Nederland ligt de gratis BRT-Achtergrondkaart van PDOK voor de hand, anders OpenStreetMap met naamsvermelding.
- [ ] **Overzicht voor coördinator en analist:** route en pins per ronde, en een dekkingskaart over een periode (waar weinig wordt gelopen, geteld per rastervak van bijvoorbeeld 50 × 50 m) om routes af te spreken.
- [ ] **Model:** module `Tracking` met `RoundTrack` (ronde, loper, vereenvoudigde lijn, start/eind) en een optionele `location` op `RoundReport`; endpoints `POST/GET /rounds/{id}/track`. Uitzetten verwijdert de knoppen, niet de rest van de app.
- [ ] **Geo in PHP, niet in SQL:** de lijn als GeoJSON-tekst (`json_encode`) in een `TEXT`-kolom, vereenvoudigd vóór opslag, want een JSON-kolom geeft op MariaDB en MySQL een ander schema; een pin als twee getallen in WGS84; de dekking telt punten per rastervak in PHP. Geen spatial types of `ST_*`-functies: MariaDB en MySQL rekenen daarmee verschillend, Doctrine kent ze niet zonder extra pakket, en de aantallen (tientallen lopers, routes 3 maanden bewaard) vragen er niet om.
- [ ] **Privacy (AVG):** locatie is gevoelige persoonsgegevens. Alleen zichtbaar voor coördinator, analist en de lopers van die ronde; niet via MCP behalve geaggregeerd; routes standaard na 3 maanden weg, pins zo lang als de terugmelding. Vooraf de privacyverklaring aanpassen en met het bestuur afstemmen.

**Klaar als:** een loper met toestemming een route opneemt of intekent en een pin bij een melding zet, de coördinator dat op de kaart ziet, en alles uit staat voor wie geen toestemming gaf.

### Uitbreiding U10 — OAuth voor MCP-clients (later)

- [ ] OAuth 2.1-autorisatieserver (authorization code + PKCE) naast de bestaande tokens, voor MCP-clients die alleen OAuth ondersteunen. Opt-in per vereniging: standaard uit, de beheerder zet het aan in de instellingen, dus elke vereniging beslist zelf. Uitzetten trekt alle OAuth-koppelingen meteen in.
- [ ] Een latere uitbreiding: tot die tijd werkt MCP met de tokens uit fase 4, vooral voor ontwikkelaar en coördinator vanuit bijvoorbeeld Claude Code of Claude Desktop. Met OAuth kunnen ook lopers MCP gebruiken vanuit een web- of telefoonapp, door in te loggen in plaats van een token in te vullen. De MCP SDK controleert tokens maar geeft ze niet uit, dus dit is extra code en extra beveiligingsrisico.

**Klaar als:** de gewenste client via OAuth inlogt, met dezelfde scopes en rollen als een token.

### Uitbreiding U11 — No-shows bijhouden

Een opt-in-functie achter een feature flag: standaard uit, de beheerder zet hem per vereniging aan in de instellingen. Zolang hij uit staat, is er geen knop, geen veld in de uitvoer en wordt er niets vastgelegd.

- [ ] **Markeren bij annuleren:** wie een begonnen ronde annuleert (`CancelRound`, een loper van die ronde of de coördinator), vinkt aan welke lopers niet kwamen. De coördinator kan dat achteraf aanpassen.
- [ ] **Model:** `noShowAt` op de aanmelding (`Participation`) van die loper in die ronde; geen eigen tabel. Elke markering en elke verwijdering laat een auditregel achter.
- [ ] **Zichtbaar voor de coördinator:** het aantal no-shows per lid over een periode, bij het lid in het ledenoverzicht, zodat hij iemand kan aanspreken. Geen ranglijst; niet zichtbaar voor andere lopers of de analist, en via MCP alleen voor de coördinator.
- [ ] **Zichtbaar voor het lid zelf:** de eigen no-shows bij "Mijn activiteit", met de mogelijkheid bezwaar te maken bij de coördinator.
- [ ] **Privacy (AVG):** een oordeel over hoe betrouwbaar iemand is, is gevoelige persoonsinformatie. Vaste bewaartermijn (standaard 12 maanden, instelbaar), daarna verwijdert de scheduler de markering; vermelding in de privacyverklaring van de vereniging; inzage en correctie voor het lid; uitzetten verwijdert alle markeringen.

**Klaar als:** een no-show bij het annuleren vastligt, de coördinator het aantal per lid ziet, het lid zijn eigen no-shows ziet, en markeringen na de bewaartermijn weg zijn.

## Overstap van bestaande v3-installaties

Een vereniging die nu op v3 draait, blijft dat doen tot de MVP klaar is (fase 0 tot en met 3, 5, 5b en 6a/6b). De overstap is per vereniging een apart project, buiten deze repo; de ontwikkeling van v4 wacht er niet op. De stappen in het kort:

1. **Voorbereiden:** subdomein en certificaat, PHP 8.4, cron, een mailadres voor de app.
2. **Installeren en oefenen:** web-installer, cronregel, testmail; een generale repetitie van de conversie op een kopie van de database, eerst met `--dry-run`, en de kopie daarna wissen; testen met de coördinator en een paar lopers.
3. **Overstappen:** v3 alleen-lezen, laatste conversie, oude URL's doorverwijzen, korte parallelle periode.

`docs/admin-guide/` beschrijft deze stappen voor een webbeheerder.

## Werkwijze: lokale opzet en Claude Code-prompts

v4 komt in een **nieuwe repo** `zabuto/buurtpreventie`, met vanaf de eerste commit PolyForm Noncommercial en een schone branchindeling. De oude repo wordt hernoemd naar `zabuto/buurtpreventie-legacy`; hij blijft MIT en houdt zijn branches `v1`, `v2` en `v3`. Lokaal staan beide naast elkaar:

| Map | Branch | Rol |
| --- | --- | --- |
| `~/Sites/buurtpreventie-legacy` | `v3` (oude repo) | Naslag: blijft draaien en wordt niet aangeraakt |
| `~/Sites/buurtpreventie` | `develop` (nieuwe repo) | Hier wordt gebouwd |

**Oude repo afronden.** Op `v3` komt één laatste commit met bovenaan de README een verwijzing: v3 wordt niet verder ontwikkeld, de opvolger staat op `zabuto/buurtpreventie` onder PolyForm Noncommercial, en deze repo blijft MIT. Het `LICENSE`-bestand blijft ongewijzigd. Die commit krijgt de tag `v3-mit-final`. `v3` krijgt daarna alleen nog fixes zolang bestaande installaties erop draaien; na de overstap wordt de repo op GitHub gearchiveerd.

**Branches en versies in de nieuwe repo.**

- **`develop`:** hier gebeurt het werk, en tot `v4.0.0` is dit de standaardbranch op GitHub. Elke taak krijgt een eigen branch `feature/<korte-naam>` en gaat via een pull request terug, altijd als squash merge: één commit per pull request.
- **`main`:** alleen uitgebrachte versies. Een release is een merge van `develop` naar `main` (een merge commit, geen squash, zodat `main` de commits van `develop` houdt) met een tag `vX.Y.Z`; die tag start de release-workflow. `v4.0.0` is de MVP; vanaf dan is `main` de standaardbranch.
- **Spoedfix:** `hotfix/<korte-naam>` vanaf `main`, terug naar `main` met een nieuwe patchtag, én naar `develop`.
- **Dependabot:** gewone updates richten zich op `develop`; beveiligingsupdates gaan via de standaardbranch.

**Versienummers.** Buurtpreventie is geen SaaS: elke vereniging draait een eigen installatie en werkt die zelf bij. Daarom zijn er twee losse versienummers:

- **App: semantic versioning (`vX.Y.Z`).** De updater gebruikt het nummer om te bepalen hoe voorzichtig hij moet zijn. Een patch bevat alleen fixes en mag automatisch. Een minor brengt nieuwe functies en migraties die terug kunnen. Een major vraagt handwerk, zoals een hogere minimale PHP-versie, en wordt vooraf aangekondigd.
- **API: alleen een hoofdnummer in het pad (`/api/v1`).** Dat gaat pas omhoog bij een wijziging die bestaande clients breekt. Nieuwe velden en endpoints blijven in `v1`. Komt er ooit een `v2`, dan draait `v1` minstens één major van de app nog mee. MCP-tools volgen dezelfde regel.

**Eenmalig opzetten.**

```bash
# Oude repo: laatste commit, tag, hernoemen (vereist de GitHub CLI, gh)
cd ~/Sites/buurtpreventie
git fetch origin && git switch v3 && git pull --ff-only
# README: verwijzing naar de opvolger bovenaan zetten, dan:
git commit -am "docs: point to successor repository" && git push
git tag v3-mit-final && git push origin v3-mit-final
gh repo rename buurtpreventie-legacy --repo zabuto/buurtpreventie --yes
git remote set-url origin git@github.com:zabuto/buurtpreventie-legacy.git
cd .. && mv buurtpreventie buurtpreventie-legacy

# Nieuwe repo met main en develop
gh repo create zabuto/buurtpreventie --public
mkdir buurtpreventie && cd buurtpreventie
git init -b main
# LICENSE (Required Notice + PolyForm NC 1.0.0) en een korte README toevoegen, dan:
git add LICENSE README.md && git commit -m "chore: initial commit with license and readme"
git remote add origin git@github.com:zabuto/buurtpreventie.git && git push -u origin main
git switch -c develop && git push -u origin develop
gh repo edit zabuto/buurtpreventie --default-branch develop
```

Daarna dit plan exporteren als Markdown naar `docs/plans/plan.md` in de nieuwe repo en committen op `develop`. Claude Code starten met de oude repo erbij als naslag:

```bash
cd ~/Sites/buurtpreventie
claude --add-dir ../buurtpreventie-legacy
```

**`AGENTS.md` en `CLAUDE.md`.** `AGENTS.md` bevat de regels voor iedereen, mens of AI-tool; `CLAUDE.md` verwijst ernaar. De geldende tekst staat in de repo; dit plan houdt er geen kopie van bij.

**Prompts per fase.** Elke prompt begint met een taakplan en wacht op akkoord; zo blijft de ontwikkelaar aan het stuur.

Fase 0 — basis:

```text
Lees AGENTS.md en docs/plans/plan.md, vooral Samenvatting, Uitgangspunten, Licentie,
Codestructuur, Overdraagbaarheid en Fase 0. v3 staat in ../buurtpreventie-legacy (alleen lezen).
Maak eerst een taakplan in docs/plans/phase-0.md en wacht op mijn akkoord.

Daarna, op een branch feature/phase-0 vanaf develop:
- Symfony 7.4 LTS-skelet met PHP 8.4 en de modulemappen uit Codestructuur.
- PHPStan level max met PHPat-regels (met uitleg in de foutmeldingen), Mago,
  PHPUnit, Rector met Symfony-regels.
- GitHub Actions: tests, PHPStan, Mago, composer audit, falen op deprecations, plus de
  wekelijkse vooruitkijk-job met de nieuwste Symfony 8 en PHP 8.5 (niet-blokkerend).
- Dependabot met target-branch develop, en SECURITY.md.
- docs/: getting-started.md, architecture.md met uitgewerkt voorbeeld, glossary.md,
  operations.md, adr/0001 over de structuur, lege admin-guide/ en user-guide/.
- De generator bin/console app:make:use-case <Module> <Naam>.
- Vul in AGENTS.md de sectie Commands in. LICENSE, README, AGENTS.md en CLAUDE.md
  bestaan al; LICENSE niet wijzigen.
Klaar als CI lokaal en op GitHub groen is en één voorbeeld-use case via de generator
alle PHPat-regels doorstaat. Daarna een pull request naar develop.
```

Fase 1 — domein en use cases:

```text
Fase 1 uit docs/plans/plan.md. Taakplan eerst in docs/plans/phase-1.md, wacht op akkoord.
Lees ../buurtpreventie-legacy/src/Entity, src/Service/WalkService.php en migrations/.
Leg elke domeinregel uit v3 eerst vast als test, bouw dan Patrol, Membership, Identity
en Audit volgens Codestructuur. Verbeter het domeinmodel volgens het plan (v3 is geen keurslijf); de conversie
uit v3 volgt in fase 5b.
Vergeet de review-focuspunten 1, 2 en 4 uit het plan niet.
```

Fase 2 — REST-API:

```text
Fase 2 uit docs/plans/plan.md. Taakplan eerst, wacht op akkoord.
Bouw de endpoints uit Domeinmodel en API-ontwerp als …Controller in UseCase/<Naam>/: json_login
+ CSRF, access_token-authenticator met gehashte tokens en scopes, problem+json,
OpenAPI via NelmioApiDocBundle. Per endpoint een functionele test, plus één matrixtest
rol × actie. Contactgegevens alleen bij toestemming (review-focus 3).
```

Fase 3 — branding en configuratie:

```text
Fase 3 uit docs/plans/plan.md. Taakplan eerst, wacht op akkoord.
Module Settings met standaardwaarden zonder verenigingsnaam, /admin/settings, logo-upload,
GET /config, manifest.webmanifest, contrastcontrole bij opslaan, e-mailtemplates nl/en.
Neem de teksten uit ../buurtpreventie-legacy/translations als startpunt.
Links in e-mails moeten werken in een submap (review-focus 5).
```

Fase 4 — MCP:

```text
Fase 4 uit docs/plans/plan.md. Taakplan eerst, wacht op akkoord.
symfony/mcp-bundle + mcp/sdk (minor vastgepind), stateless HTTP op /mcp, tools uit de
sectie MCP-server als dunne wrappers om bestaande handlers. Scopes, rate limit, kanaal
"mcp" in het auditlog. Test elke tool met de SDK-client, ook geweigerd zonder scope of rol.
```

Fase 5 — frontend:

```text
Fase 5 uit docs/plans/plan.md, met de sectie Frontend: PWA, responsive en toegankelijk.
Taakplan eerst, wacht op akkoord.
Bouw frontend/ buildless met de gevendorde Lit-bundel (geen importmap), alleen tegen
/api/v1. Doelgroep: lopers van elk digitaal niveau, ook op iOS 15; zie die alinea. Gebruik
../buurtpreventie-legacy/templates als lijst van schermen, niet als ontwerp. Mobiel eerst,
WCAG 2.2 AA, formulieren in light DOM, service worker op het basispad, axe-core in CI.
```

Fase 5b — conversie v3 → v4:

```text
Fase 5b uit docs/plans/plan.md, met de conversiebeslissingen uit docs/plans/phase-1.md. Taakplan eerst.
Lees ../buurtpreventie-legacy/src/Entity en migrations/. Bouw de fixture-set v3 en app:import-v3
met --dry-run, test op MariaDB en MySQL; echte data hoort bij het overstapproject.
```

Fase 6a en 6b — installeren, releasen en bijwerken:

```text
Fase 6a en daarna 6b uit docs/plans/plan.md, met Hosting en Beveiligingsupdates. De overstap van
bestaande installaties hoort er niet bij; die is een apart project. Taakplan eerst.
Release-workflow met ondertekende zip, web-installer, scherm Onderhoud met updater en
terugzetten (releases/ + shared/), .htaccess voor submap en Authorization-header, tick.
Schrijf docs/admin-guide/ in gewone taal voor een webbeheerder zonder dev-achtergrond.
```

Uitbreiding U1 — vast loopmaatje uitnodigen:

```text
Uitbreiding U1 uit docs/plans/plan.md. Taakplan eerst, wacht op akkoord.
BuddyPermission en BuddyInvite in Patrol, opt-in per persoon, intrekken en pauzeren zonder
bericht aan de ander, suggesties uit gezamenlijke rondes, limieten (1 per ronde, 2 per week),
mail met "Ik loop mee"/"Kan deze keer niet". Test vooral: zonder toestemming nooit een
uitnodiging, en de uitnodiger ziet nooit een weigering van toestemming. Niet via MCP.
```

Uitbreiding U5 — statistieken voor analist:

```text
Uitbreiding U5 uit docs/plans/plan.md. Taakplan eerst, wacht op akkoord.
Use cases en endpoints voor statistieken per maand, weekdag, dagdeel en uitkomst, met
SQL-aggregaties en een dagcache. Scherm Statistieken in de frontend met eigen SVG-
componenten in Lit, elk met conclusie-titel en tabelweergave. Geen cijfers per loper.
Controleer de uitkomsten tegen een handtelling op de demo-fixtures.
```

Uitbreiding U9 — route en incidentkaart:

```text
Uitbreiding U9 uit docs/plans/plan.md. Taakplan eerst, wacht op akkoord.
Module Tracking, standaard uit via een instelling. Toestemming per loper, route opnemen
alleen met app open, route achteraf intekenen als alternatief, pin bij terugmelding.
Leaflet gevendord, kaartondergrond instelbaar (PDOK BRT-Achtergrondkaart als standaard).
Zichtbaarheid, bewaartermijn en MCP-beperkingen precies zoals in het plan; test dat een
loper zonder toestemming nergens locatiegegevens achterlaat.
```

**Na elke fase:**

```text
Review de branch tegen docs/plans/plan.md: wat ontbreekt uit deze fase, welke uitgangspunten
of PHPat-regels worden geschonden, welke review-focuspunten hebben nog geen test?
Alleen rapporteren, niets wijzigen.
```

## Risico's en review-focus

**Risico's.**

| Risico | Gevolg | Maatregel |
| --- | --- | --- |
| MCP SDK en bundle zijn nog pre-1.0 | Breaking changes bij updates | Minor vastpinnen, alle MCP-code in `…Tool`-klassen, tests per tool; MCP pas in fase 4 |
| Hosting zonder SSH | Geen `bin/console` voor installatie en migraties | Release-zip + web-installer; herinneringen via cron in het hostingpaneel, of een externe tick |
| Host stript `Authorization`-header | Tokens en MCP werken niet | Regel in `.htaccess` + een diagnosepagina in de installer die het controleert |
| Persoonsgegevens via AI-clients | AVG-vraag voor de vereniging | Dataminimalisatie in MCP-uitvoer, MCP standaard uit, per vereniging aan te zetten |
| Gekaapte release van een afhankelijkheid | Kwaadaardige code via een automatisch samengevoegde update | Dependabot-cooldown (Composer 7, 7 en 14 dagen, actions 7) en een Composer-cooldown van 24 uur; actions vastgepind op commit-SHA; Opengrep alleen met een geldige handtekening van zijn release-workflow, na dezelfde cooldown en binnen de huidige major; CI zonder geheimen in pull requests |
| Eén onderhouder | Kennis zit bij één persoon | `docs/`, `AGENTS.md`, weinig afhankelijkheden, geen buildstap; webbeheerder werkt zelf bij via het scherm Onderhoud |
| Slot op alle ledenrijen bij de laatste-beheerdercontrole (geaccepteerd risico) | Terwijl een beheerder rollen wijzigt, een lid deactiveert of wist, wacht elke andere schrijfactie op een lid enkele milliseconden; een deadlock laat één verzoek falen met een fout | Geaccepteerd bij enkele honderden leden (ADR 0005); bij wachttijden of deadlocks in het log een geïndexeerde beheerderskolom of één slotrij |
| Overstap 7.4 → 8.4 LTS blijft liggen | Na november 2029 geen security-fixes meer | Geen deprecations (CI faalt erop); overstap gepland in 2028 en vastgelegd in docs/operations.md |

**Review-focus** — invoer die geen enkele fasetest vanzelf raakt en die lopers het eerst zullen merken. Elk punt krijgt een test in de genoemde fase:

1. **Twee lopers melden tegelijk aan** voor de laatste plek of de eerste aanmelding: geen dubbele `Participation`, nooit meer lopers dan het maximum (de ander krijgt "ronde is vol"), minimumstatus klopt (slot op de ronde, unique constraint + test, fase 1).
2. **Rondes rond de zomer-/wintertijdwissel**: een ronde om 22:00 blijft 22:00 lokale tijd in kalender, API, MCP en herinnering (fase 1 en 2).
3. **Lid zonder toestemming** verschijnt in `/members`, `get_round` of een e-mail: nooit telefoon, e-mail of adres (fase 2 en 4).
4. **Gedeactiveerd of verwijderd lid** in toekomstige rondes, ledenlijst of als token-eigenaar: wordt uitgeschreven, en het token werkt meteen niet meer (fase 1 en 2).
5. **App in een pad of op een subdomein**: links in e-mails, ook die van een uitnodiging, en `manifest` gebruiken de ingestelde basis-URL (subdomein of `/buurtpreventie/`) en bevatten nooit `/public/` (fase 3 en 6).
