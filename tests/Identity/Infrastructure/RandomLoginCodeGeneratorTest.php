<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure;

use App\Identity\Domain\LoginChallenge;
use App\Identity\Infrastructure\RandomLoginCodeGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RandomLoginCodeGeneratorTest extends TestCase
{
    #[Test]
    public function it_makes_a_challenge_id_that_a_login_challenge_accepts(): void
    {
        $challenge = LoginChallenge::open(new RandomLoginCodeGenerator()->newChallengeId(), null, 'hash', new DateTimeImmutable('2026-11-16T10:00:00+00:00'));

        $this->assertSame(32, \strlen($challenge->id()));
    }

    #[Test]
    public function it_never_repeats_a_challenge_id(): void
    {
        $generator = new RandomLoginCodeGenerator();

        $this->assertNotSame($generator->newChallengeId(), $generator->newChallengeId());
    }

    /**
     * A thousand codes cover each of the low digits many times, so a code without leading zeros or of another length
     * shows up.
     */
    #[Test]
    public function it_makes_codes_of_six_digits_leading_zeros_included(): void
    {
        $generator = new RandomLoginCodeGenerator();
        $codes = [];
        for ($code = 1; $code <= 1000; ++$code) {
            $codes[] = $generator->newCode();
        }

        $this->assertSame([], \array_values(\array_filter($codes, static fn(string $code): bool => 1 !== \preg_match('/^\d{6}\z/', $code))));
        $this->assertGreaterThan(900, \count(\array_unique($codes)));
    }
}
