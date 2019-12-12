# Actieve buurtpreventie
Met deze applicatie kunnen geregistreerde leden van de actieve buurtpreventie het lopen door de buurt inroosteren.
Ook is het mogelijk om achteraf het resultaat en de bijzonderheden te registreren.

## Installatie
```
composer install
bin/console app:setup
```

Als een console command niet kan worden uitgevoerd dan kan `setup.php` via de browser worden gedraaid. 

_Let op: gooi dit bestand weg na de uitrol op productie!_

## Configuratie
Alle instellingen voor de applicatie staan in omgevingsvariabelen. 
Zie voor informatie over environment variables: 
https://symfony.com/doc/current/best_practices/configuration.html#infrastructure-related-configuration

Alleen de database versie voor Doctrine staat in een config bestand dat gecontroleerd dient te worden.
- server_version in `config/packages/doctrine.yaml`

Als het voor productie niet mogelijk is om environment variables op serverniveau vast te leggen, bijvoorbeeld bij shared hosting, dan kunnen de variabelen vastgelegd worden in een `/env.local` bestand in de root directory.

```
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=s$cretf0rt3st
DATABASE_URL=mysql://username:password@localhost:3306/dbname
MAILER_URL=sendmail://127.0.0.1
WEBSITE_NAME=Buurtpreventie
WEBSITE_EMAIL=noreply@buurtpreventie.nl
WEBSITE_SCHEME=https
WEBSITE_HOST=example.org
WEBSITE_BASE_URL=my/path
WALKER_MINIMUM=2
```
