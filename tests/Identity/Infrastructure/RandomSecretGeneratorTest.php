<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure;

use App\Identity\Infrastructure\RandomSecretGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RandomSecretGeneratorTest extends TestCase
{
    #[Test]
    public function it_makes_a_prefixed_secret_of_32_random_bytes(): void
    {
        $secret = new RandomSecretGenerator()->newSecret();

        $this->assertMatchesRegularExpression('/^bpt_[0-9a-f]{64}$/', $secret);
    }

    #[Test]
    public function it_never_repeats_a_secret(): void
    {
        $generator = new RandomSecretGenerator();

        $this->assertNotSame($generator->newSecret(), $generator->newSecret());
    }
}
