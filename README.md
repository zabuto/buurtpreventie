# Actieve buurtpreventie
Met deze applicatie kunnen geregistreerde leden van de actieve buurtpreventie het lopen door de buurt inroosteren.
Ook is het mogelijk om achteraf het resultaat en de bijzonderheden te registreren.

## Installatie
```
composer install
bin/console app:setup
```

Als een console command niet kan worden uitgevoerd dan kan `setup.php` via de browser worden gedraaid. 
_Let op: gooi deze file weg na uitrol op productie!_

## Configuratie
Controleer de onderstaande instellingen:

- server_version in `config/packages/doctrine.yaml` (Doctrine)
- $siteName, $fromName en $fromEmail in `config/services.yaml` (MailService)
- $walkerMinimum in `config/services.yaml` (WalkService)

#### Omgevingsvariabelen
Zie voor informatie over environment variables: 
https://symfony.com/doc/current/best_practices/configuration.html#infrastructure-related-configuration

Als het voor productie niet mogelijk is om environment variables op serverniveau vast te leggen, bijvoorbeeld bij shared hosting, dan kunnen de variabelen vastgelegd worden in een `/env.local` bestand in de root directory.

```
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=s$cretf0rt3st
DATABASE_URL=mysql://username:password@localhost:3306/dbname
MAILER_URL=sendmail://127.0.0.1
```
