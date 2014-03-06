#!/bin/bash

echo ""
echo ""
echo "-------------------------------------"
echo "Installatie ontwikkelomgeving"
echo "-------------------------------------"

echo ""
echo "- Installeren van vendor pakketten"
echo ""
php composer.phar install --optimize-autoloader --quiet

echo ""
echo "- Cache en logs initialiseren"
echo ""
php app/console cache:clear
chmod 773 app/cache
chmod 773 app/logs

echo ""
echo "- Installeren van assets in web directory"
echo ""
php app/console assets:install
php app/console assetic:dump

echo ""
echo "- Aanmaken en vullen van databasetabellen"
echo ""
php app/console doctrine:schema:drop --force
php app/console doctrine:schema:create
php app/console widop:fixtures:load --env=dev

echo ""
echo "-------------------------------------"
echo "Ontwikkelomgeving geinitialiseerd!"
echo "-------------------------------------"
