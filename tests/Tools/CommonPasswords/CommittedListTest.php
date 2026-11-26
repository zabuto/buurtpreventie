<?php

declare(strict_types=1);

namespace App\Tests\Tools\CommonPasswords;

use App\Tools\CommonPasswords\CommonPasswordFile;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The app reads only the built list, so a Dutch password added to the source but not rebuilt would refuse nothing.
 */
#[Group('integration')]
final class CommittedListTest extends TestCase
{
    private const string LIST = __DIR__ . '/../../../src/Identity/Infrastructure/Password/common-passwords.txt';
    private const string DUTCH = __DIR__ . '/../../../tools/common-passwords-nl.txt';

    #[Test]
    public function the_built_list_starts_with_every_dutch_password_of_the_source(): void
    {
        $list = (string) \file_get_contents(self::LIST);
        $dutch = CommonPasswordFile::build('', '', '', (string) \file_get_contents(self::DUTCH));

        $passwords = CommonPasswordFile::passwords($dutch);
        $this->assertSame($passwords, \array_slice(CommonPasswordFile::passwords($list), 0, \count($passwords)));
    }

    #[Test]
    public function the_built_list_names_its_seclists_commit(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', (string) CommonPasswordFile::commitOf((string) \file_get_contents(self::LIST)));
    }
}
