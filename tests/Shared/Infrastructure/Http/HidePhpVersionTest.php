<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * PHP on the command line keeps no response headers, so a test cannot see PHP's own header disappear; it proves
 * that the response carries the fixed value instead.
 */
#[Group('functional')]
final class HidePhpVersionTest extends WebTestCase
{
    #[Test]
    public function it_names_the_stack_without_a_version(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/health');

        self::assertResponseHeaderSame('X-Powered-By', 'PHP Symfony');
    }
}
