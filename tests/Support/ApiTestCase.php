<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * An endpoint test on the in-memory kernel: no database, real security and voters.
 */
abstract class ApiTestCase extends WebTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return InMemoryKernel::class;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected static function json(KernelBrowser $client): array
    {
        $body = \json_decode((string) $client->getResponse()->getContent(), associative: true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }
}
