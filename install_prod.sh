#!/bin/bash

echo ""
echo ""
echo "-------------------------------------"
echo "Installatie productieomgeving"
echo "-------------------------------------"

echo ""
echo "- Installeren van vendor pakketten"
echo ""
composer install --no-dev --optimize-autoloader --quiet

echo ""
echo "- Cache en logs initialiseren"
echo ""
app/console cache:clear --env=prod --no-debug
chmod 773 app/cache
chmod 773 app/logs

echo ""
echo "- Installeren van assets in web directory"
echo ""
app/console assets:install
app/console assetic:dump --env=prod --no-debug

echo ""
echo "- Bijwerken en vullen van databasetabellen"
echo ""
app/console doctrine:schema:update --force
app/console widop:fixtures:load --env=prod

echo ""
echo "-------------------------------------"
echo "Productieomgeving geinitialiseerd!"
echo "-------------------------------------"
