# Actieve Buurtpreventie
Met deze applicatie kunnen leden van de actieve buurtpreventie het lopen door de buurt inroosteren.
Ook is het mogelijk om achteraf het resultaat en de bijzonderheden te registreren.
Daarnaast beschikt het systeem over gebruikersbeheer voor het afschermen van gegevens op de website.

## Systeemeisen
De applicatie is gemaakt met Symfony2.
[Symfony](http://symfony.com) is een open-source PHP-framework voor het bouwen van dynamische websites.

Voor het draaien gelden de onderstaande randvoorwaarden:

-   Webhosting pakket / webserver
-   PHP, minimaal versie 5.3.3
-   MySQL database met InnoDB storage engine (vanaf MySQL 5.5 is dit de standaard)
-   Minimaal 100 MB schijfruimte

## Installatie via Github en Composer
Deze applicatie is publiekelijk beschikbaar via GitHub

    https://github.com/zabuto/buurtpreventie.git

Daarna kunnen de benodigde aanvullende via de dependency manager [Composer](https://getcomposer.org/).
Via het onderstaande bash script kan het project worden geïnitialiseerd:

    ./install_prod.sh

Voor het installeren van een ontwikkelomgeving kan het script `./install_dev.sh` worden gebruikt.


## Configuratie
Alle configuratie instellingen voor de applicatie zitten in 1 [YAML](http://en.wikipedia.org/wiki/YAML) bestand:

	/app/config/parameters.yml

Dit bestand wordt tijdens de installatie aangemaakt via het distributiebestand `/app/copnfig/parameters.yml.dist`.
De instellingen in dit bestand moeten worden aangepast aan uw eigen situatie.
De benodigde aanpassingen worden hieronder verder toegelicht.

### Database en tabellen
De applicatie maakt gebruik van een [MySQL](http://nl.wikipedia.org/wiki/MySQL) database.
Zonder de aanwezigheid van de database en de daarin benodigde tabellen is het niet mogelijk om het systeem te draaien.

#### Connectie instellingen
In het bestand `/app/config/parameters.yml` staan de settings voor de connectiegegevens.
Pas deze aan naar uw eigen situatie.

    database_driver: pdo_mysql (laat deze setting zo staan)
    database_host: hostnaam of ipadres (bv localhost)
    database_port: poort (standaard poort = null)
    database_name: naam van database
    database_user: gebruiker met schrijfrechten
    database_password: wachtwoord van de gebruiker

#### Structuur
De encoding van de tabellen is [UTF-8](http://nl.wikipedia.org/wiki/UTF-8).
De onderstaande tabellen worden aangemaakt:

    buurtprev_loopresultaat
    buurtprev_loopschema
    buurtprev_looptoelichting
    zabuto_user
    zabuto_usergroup
    zabuto_user_usergroup


### Email instellingen
De applicatie verstuurt mail naar gebruikers.
Hiervoor is het nodig dat er een [Gmail](https://mail.google.com/intl/nl/mail/help/about.html) emailaccount is vastgelegd in `/app/config/parameters.yml`.

Hieronder ziet u de instellingen voor het gebruik van het Gmail account:

    mailer_address: voorbeeld@gmail.com
    mailer_transport: gmail (laat deze setting zo staan)
    mailer_host: null (laat deze setting zo staan)
    mailer_user: voorbeeld (zonder @gmail.com)
    mailer_password: wachtwoord


### Overige instellingen
In `/app/config/parameters.yml` staan ook instellingen voor taal, beveiliging en weergave doeleinden.
Ook zijn er instellingen voor het lopen zelf.

    locale: nl                                          taalinstelling (laat deze setting zo staan)
    secret: SecretTokenNogAanpassen                     geheime code voor beveiligingsdoeleinden (pas deze altijd aan)

    app_author: 'Buurtvereniging Voorbeeld Eindhoven'   naam van uw organisatie
    app_title: 'Buurtpreventie Voorbeeld'               title van de applicatie
    app_description: 'Buurtpreventie in Eindhoven.'     aanvullende omschrijving
    app_theme:  voorbeeld                               gebruikt thema voor de layout, standaard is null

    loopschema_minimum_aantal_lopers: 2                 minimum aantal benodigde lopers
    loopschema_maanden_vooruit: 3                       aantal maanden vooruit plannen


## Cache en log mappen
Er zijn een tweetal mappen waarin de applicatie bestanden wegschrijft:

    /app/cache
    /app/logs

Op deze mappen zijn dan ook schrijfrechten nodig. Wijzig de CHMOD permissies voor beide mappen naar:

    773

Zorg ervoor dat de rechten recursief gelden voor alle submappen en bestanden.


## Eerste aanroep en aammaken cache
De eerste keer dat de applicatie wordt aangeroepen wordt de cache aangemaakt.
Hierdoor duurt het wat langer voordat de pagina is ingeladen.
Daarna wordt er gebruik gemaakt van de gecachte informatie en zullen de pagina's sneller inladen.


## Validatie gebruik Gmail account
Gebruik van het Gmail account door de applicatie dient gevalideerd te worden.

Om deze actie uit te voeren in het bestand `/web/validatie.php` toegevoegd.
Roep dit bestand aan via de webbrowser. Het systeem zal proberen een email te verzenden.

Als validatie nodig is kan de email niet worden verzonden.
Log in de browser in op de opgegeven Google Account en ga naar
[https://accounts.google.com/DisplayUnlockCaptcha](https://accounts.google.com/DisplayUnlockCaptcha) om het gebruik goed te keuren.

Laad daarna de validatie.php pagina opnieuw in en probeer de email alsnog te verzenden.

Als de validatie is voltooid kan het bestand `/web/validatie.php` worden verwijderd. Dit wordt verder niet meer gebruikt voor andere doeleinden.


## Eigen layout thema
Een afwijkende css stylesheet en bijbehorende afbeeldingen kunnen geplaatst worden in de ThemeBundle binnen de applicatie:

    /src/Zabuto/Bundle/ThemeBundle/Resources/public

Zorg ervoor dat stylesheet in de map `css` staan.
De naam van het bestand dient overeen te komen met de ingevulde waarde in de setting `app_theme` in `/app/config/parameters.yml`.
Afbeeldingen kunnen geplaatst worden in de `images` map.

## Inloggen in productieomgeving
Na initialisatie voor de productieomegving is er één gebruiker beschikbaar. Wijzig hiervan direct het emailadres en wachtwoord.
Om de eerste keer in te loggen gelden de onderstaande inloggegevens:

    emailadres: anke@zabuto.com
    wachtwoord: buurtpreventie

## Email notificatie via cronjob
Het is mogelijk om lopers vooraf een herinneringsemail te sturen. Hiervoor is het nodig om een cronjob in te richten.
Het bestand `/app/cron.php` moet uitvoerbaar zijn om de cronjob te kunnen draaien.

Het uit te voeren commando heeft één argument: het aantal dagen dat vooruit gekeken moet worden (0 is vandaag, 1 morgen, etc.):

    /absoluut-pad-naar-bestand/cron.php zabutobuurtpreventie:reminder 1

Lees [hier](https://www.antagonist.nl/help/nl/webhosting/advanced/cronjob) meer over het inrichten van cronjobs.