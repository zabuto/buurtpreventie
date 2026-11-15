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

    /**
     * A fake or other service of the kernel the client runs on, to arrange data before the request or check it after.
     *
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected static function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
