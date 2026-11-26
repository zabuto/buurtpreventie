<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Password;

use App\Identity\Infrastructure\Password\CommonPasswordList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

#[Group('integration')]
final class CommonPasswordListTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function commonPasswords(): iterable
    {
        yield 'Dutch, with digits' => ['welkom123456'];
        yield 'Dutch, a known sentence' => ['ditwachtwoordisgeheim'];
        yield 'English, from SecLists' => ['iloveyou'];
        yield 'the last line of the file' => ['eyphed'];
    }

    #[DataProvider('commonPasswords')]
    #[Test]
    public function it_holds_a_common_password(#[SensitiveParameter] string $password): void
    {
        $this->assertTrue(new CommonPasswordList()->contains($password));
    }

    #[Test]
    public function it_does_not_hold_a_passphrase_of_unrelated_words(): void
    {
        $this->assertFalse(new CommonPasswordList()->contains('fietslampregen'));
    }

    #[Test]
    public function it_does_not_hold_its_comment_lines(): void
    {
        $this->assertFalse(new CommonPasswordList()->contains('# MIT License'));
    }
}
