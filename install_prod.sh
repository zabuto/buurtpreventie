#!/bin/bash

echo ""
echo ""
echo "-------------------------------------"
echo "Installatie productieomgeving"
echo "-------------------------------------"

echo ""
echo "- Installeren van vendor pakketten"
echo ""
php composer.phar install --no-dev --optimize-autoloader --quiet

echo ""
echo "- Cache en logs initialiseren"
echo ""
php app/console cache:clear --env=prod --no-debug
chmod 773 app/cache
chmod 773 app/logs

echo ""
echo "- Installeren van assets in web directory"
echo ""
php app/console assets:install
php app/console assetic:dump --env=prod --no-debug

echo ""
echo "- Bijwerken en vullen van databasetabellen"
echo ""
php app/console doctrine:schema:update --force
php app/console widop:fixtures:load --env=prod

echo ""
echo "-------------------------------------"
echo "Productieomgeving geinitialiseerd!"
echo "-------------------------------------"
