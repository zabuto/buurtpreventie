<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure;

use App\Identity\Infrastructure\HmacLoginCodeHasher;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HmacLoginCodeHasherTest extends TestCase
{
    private const string CHALLENGE = '0123456789abcdef0123456789abcdef';

    #[Test]
    public function it_gives_the_same_hash_for_the_same_challenge_and_code(): void
    {
        $hasher = new HmacLoginCodeHasher('key-one');

        $this->assertSame($hasher->hash(self::CHALLENGE, '482913'), $hasher->hash(self::CHALLENGE, '482913'));
    }

    /**
     * Each part alone changes the hash: a code works only with its own challenge, and only with the key of this
     * installation, so a copy of the database cannot try the million codes without it.
     */
    #[Test]
    public function it_gives_another_hash_for_another_key_challenge_or_code(): void
    {
        $hash = new HmacLoginCodeHasher('key-one')->hash(self::CHALLENGE, '482913');

        $this->assertNotSame($hash, new HmacLoginCodeHasher('key-two')->hash(self::CHALLENGE, '482913'));
        $this->assertNotSame($hash, new HmacLoginCodeHasher('key-one')->hash('fedcba9876543210fedcba9876543210', '482913'));
        $this->assertNotSame($hash, new HmacLoginCodeHasher('key-one')->hash(self::CHALLENGE, '482914'));
    }

    /**
     * Without a key a copy of the database tries the million codes as easily as with a plain hash.
     */
    #[Test]
    public function it_refuses_an_empty_key(): void
    {
        $this->expectException(LogicException::class);

        new HmacLoginCodeHasher('');
    }

    #[Test]
    public function it_is_no_plain_sha_256_of_the_code(): void
    {
        $hash = new HmacLoginCodeHasher('key-one')->hash(self::CHALLENGE, '482913');

        $this->assertNotContains($hash, [\hash('sha256', '482913', binary: true), \hash('sha256', self::CHALLENGE . '482913', binary: true)]);
        $this->assertSame(32, \strlen($hash));
    }
}
