<?php

declare(strict_types=1);

namespace App\Tests\Maintenance\UseCase\CheckHealth;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class CheckHealthControllerTest extends WebTestCase
{
    #[Test]
    public function it_answers_ok_as_json_without_authentication(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame('{"status":"ok"}', $client->getResponse()->getContent());
    }

    #[Test]
    public function it_refuses_writes_because_the_check_changes_nothing(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/v1/health');

        self::assertResponseStatusCodeSame(405);
    }
}
