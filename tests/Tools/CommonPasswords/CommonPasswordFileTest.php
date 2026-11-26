<?php

declare(strict_types=1);

namespace App\Tests\Tools\CommonPasswords;

use App\Tools\CommonPasswords\CommonPasswordFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommonPasswordFileTest extends TestCase
{
    private const string COMMIT = '0123456789abcdef0123456789abcdef01234567';
    private const string LICENCE = "MIT License\n\nCopyright (c) 2018 Someone\n";

    #[Test]
    public function it_writes_each_password_in_lower_case_without_whitespace(): void
    {
        $file = CommonPasswordFile::build(self::COMMIT, self::LICENCE, "Pass Word\r\nQWERTY\n", '');

        $this->assertSame(['password', 'qwerty'], CommonPasswordFile::passwords($file));
    }

    #[Test]
    public function it_puts_the_dutch_passwords_first_and_lists_each_password_once(): void
    {
        $file = CommonPasswordFile::build(self::COMMIT, self::LICENCE, "qwerty\nwelkom123456\nqwerty\n", "welkom123456\nwachtwoord123456\n");

        $this->assertSame(['welkom123456', 'wachtwoord123456', 'qwerty'], CommonPasswordFile::passwords($file));
    }

    /**
     * A listed password starting with # would read as a comment, and an empty one would refuse nothing.
     */
    #[Test]
    public function it_leaves_out_comments_empty_lines_and_passwords_starting_with_a_hash(): void
    {
        $file = CommonPasswordFile::build(self::COMMIT, self::LICENCE, "#1secret\n\n   \nqwerty\n", "# Dutch passwords\nwelkom123456\n");

        $this->assertSame(['welkom123456', 'qwerty'], CommonPasswordFile::passwords($file));
    }

    #[Test]
    public function it_names_the_seclists_commit_it_was_built_from(): void
    {
        $file = CommonPasswordFile::build(self::COMMIT, self::LICENCE, "qwerty\n", '');

        $this->assertSame(self::COMMIT, CommonPasswordFile::commitOf($file));
    }

    #[Test]
    public function it_carries_the_licence_of_seclists_as_comments(): void
    {
        $file = CommonPasswordFile::build(self::COMMIT, self::LICENCE, "qwerty\n", '');

        $this->assertStringContainsString("# MIT License\n#\n# Copyright (c) 2018 Someone\n", $file);
    }

    #[Test]
    public function it_finds_no_commit_in_a_file_without_one(): void
    {
        $this->assertNull(CommonPasswordFile::commitOf("# A list without a source\nqwerty\n"));
    }
}
