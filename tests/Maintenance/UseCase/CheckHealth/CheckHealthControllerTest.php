<?php

declare(strict_types=1);

namespace App\Tests\Maintenance\UseCase\CheckHealth;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CheckHealthControllerTest extends WebTestCase
{
    public function testAnswersOkAsJsonWithoutAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame('{"status":"ok"}', $client->getResponse()->getContent());
    }

    public function testRefusesWritesBecauseTheCheckChangesNothing(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/v1/health');

        self::assertResponseStatusCodeSame(405);
    }
}
