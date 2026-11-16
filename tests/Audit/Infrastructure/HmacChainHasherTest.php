<?php

declare(strict_types=1);

namespace App\Tests\Audit\Infrastructure;

use App\Audit\Infrastructure\HmacChainHasher;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HmacChainHasherTest extends TestCase
{
    /**
     * Every stored hash was made in this form: another JSON form or another algorithm breaks the chain of every
     * existing installation.
     */
    #[Test]
    public function it_signs_the_fields_as_compact_json_with_hmac_sha_256(): void
    {
        $hasher = new HmacChainHasher('audit-key');

        $hash = $hasher->hash([1, 'round/é', null]);

        $this->assertSame(\hash_hmac('sha256', '[1,"round/é",null]', 'audit-key', binary: true), $hash);
    }

    /**
     * Without the key nobody can make a hash that fits, so whoever can only change the database cannot repair the
     * chain after tampering.
     */
    #[Test]
    public function it_gives_another_hash_for_another_key(): void
    {
        $hash = new HmacChainHasher('audit-key')->hash([1, 'round.created']);

        $this->assertNotSame($hash, new HmacChainHasher('other-key')->hash([1, 'round.created']));
    }

    #[Test]
    public function it_refuses_an_empty_key(): void
    {
        $this->expectException(LogicException::class);

        new HmacChainHasher('');
    }
}
