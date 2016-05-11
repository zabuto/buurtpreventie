#!/bin/bash

echo ""
echo ""
echo "-------------------------------------"
echo "Installatie ontwikkelomgeving"
echo "-------------------------------------"

echo ""
echo "- Installeren van vendor pakketten"
echo ""
composer install

echo ""
echo "- Cache en logs initialiseren"
echo ""
app/console cache:clear
chmod 773 app/cache
chmod 773 app/logs

echo ""
echo "- Installeren van assets in web directory"
echo ""
app/console assets:install
app/console assetic:dump

echo ""
echo "- Aanmaken en vullen van databasetabellen"
echo ""
app/console doctrine:schema:drop --force
app/console doctrine:schema:create
app/console widop:fixtures:load --env=dev
app/console doctrine:migrations:migrate

echo ""
echo "-------------------------------------"
echo "Ontwikkelomgeving geinitialiseerd!"
echo "-------------------------------------"
