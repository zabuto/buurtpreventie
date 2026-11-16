<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class HurlRequestsFolderTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/hurl-requests-' . \bin2hex(\random_bytes(6));
        new Filesystem()->mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    #[Test]
    public function it_reads_every_hurl_file_of_a_folder_in_the_order_of_their_names(): void
    {
        $filesystem = new Filesystem();
        $filesystem->dumpFile($this->directory . '/rounds.hurl', "GET {{baseUrl}}/api/v1/rounds/{{roundId}}\nHTTP 200\n");
        $filesystem->dumpFile($this->directory . '/public.hurl', "GET {{baseUrl}}/api/v1/health\nHTTP 200\n");
        $filesystem->dumpFile($this->directory . '/local.variables', "baseUrl=https://example.org\n");

        $requests = HurlRequests::fromDirectory($this->directory);

        $this->assertSame(['GET /api/v1/health', 'GET /api/v1/rounds/{{roundId}}'], $requests);
    }
}
