<?php

namespace Zabuto\Bundle\UserBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserControllerTest extends WebTestCase
{

    public function testList()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/beheer/gebruiker/lijst');

        $this->assertGreaterThan(
            0,
            $crawler->filter('html:contains("Gebruiker overzicht")')->count()
        );
    }

    public function testNew()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/beheer/gebruiker/nieuw');

        $this->assertGreaterThan(
            0,
            $crawler->filter('html:contains("Gebruiker toevoegen")')->count()
        );


        $formData = $crawler->filter('form')->form();

        $formData['fos_user_registration_form[realname]'] = 'Test Gebruiker';
        $formData['fos_user_registration_form[email]'] = 'testgebruiker@noreply.com';
        $formData['fos_user_registration_form[address]'] = 'Straat 11a';
        $formData['fos_user_registration_form[phone]'] = '001-1234567';
        $formData['fos_user_registration_form[plainPassword][first]'] = 'password123';
        $formData['fos_user_registration_form[plainPassword][second]'] = 'password123';
        $formData['fos_user_registration_form[plainPassword][_token]'] = '1f9f69d7a45adf941082e3f1274e79ad1b181f90';
        /* @todo fix form token */

        $client->submit($formData);

        $crawler = $client->followRedirect();
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

}
