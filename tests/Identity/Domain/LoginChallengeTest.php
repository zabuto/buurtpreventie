<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\CodeAttempt;
use App\Identity\Domain\LoginChallenge;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class LoginChallengeTest extends TestCase
{
    private const string ID = '0123456789abcdef0123456789abcdef';
    private const string RIGHT = 'hash-of-the-right-code';
    private const string WRONG = 'hash-of-a-wrong-code';
    private const string OPENED = '2026-11-16T10:00:00+00:00';

    #[Test]
    public function it_accepts_the_right_code_once(): void
    {
        $challenge = self::challenge();

        $first = $challenge->attempt(self::RIGHT, self::at('+5 minutes'));
        $second = $challenge->attempt(self::RIGHT, self::at('+6 minutes'));

        $this->assertSame([CodeAttempt::Accepted, CodeAttempt::Ended], [$first, $second]);
    }

    #[Test]
    public function it_tells_how_many_attempts_are_left_after_a_wrong_code(): void
    {
        $challenge = self::challenge();

        $attempt = $challenge->attempt(self::WRONG, self::at('+1 minute'));

        $this->assertSame([CodeAttempt::Wrong, 4], [$attempt, $challenge->attemptsLeft()]);
    }

    #[Test]
    public function it_refuses_even_the_right_code_after_five_wrong_ones(): void
    {
        $challenge = self::challenge();
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->assertSame(CodeAttempt::Wrong, $challenge->attempt(self::WRONG, self::at('+1 minute')), \sprintf('attempt %d', $attempt));
        }

        $this->assertSame(CodeAttempt::Ended, $challenge->attempt(self::RIGHT, self::at('+2 minutes')));
    }

    #[Test]
    public function it_accepts_the_right_code_after_four_wrong_ones(): void
    {
        $challenge = self::challenge();
        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            $challenge->attempt(self::WRONG, self::at('+1 minute'));
        }

        $this->assertSame(CodeAttempt::Accepted, $challenge->attempt(self::RIGHT, self::at('+2 minutes')));
    }

    /**
     * @return iterable<string, array{string, CodeAttempt}>
     */
    public static function momentsAroundTheHour(): iterable
    {
        yield 'one second before the hour' => ['+59 minutes 59 seconds', CodeAttempt::Accepted];
        yield 'at the hour' => ['+60 minutes', CodeAttempt::Ended];
    }

    #[DataProvider('momentsAroundTheHour')]
    #[Test]
    public function it_takes_the_code_for_an_hour(string $after, CodeAttempt $expected): void
    {
        $this->assertSame($expected, self::challenge()->attempt(self::RIGHT, self::at($after)));
    }

    #[Test]
    public function it_counts_no_attempt_on_an_ended_challenge(): void
    {
        $challenge = self::challenge();
        $challenge->attempt(self::WRONG, self::at('+61 minutes'));

        $this->assertSame(5, $challenge->attemptsLeft());
    }

    #[Test]
    public function it_takes_no_code_once_a_newer_challenge_replaced_it(): void
    {
        $challenge = self::challenge();

        $challenge->end(self::at('+2 minutes'));

        $this->assertSame(CodeAttempt::Ended, $challenge->attempt(self::RIGHT, self::at('+3 minutes')));
    }

    #[Test]
    public function it_lets_nobody_in_for_an_address_without_a_member(): void
    {
        $challenge = LoginChallenge::open(self::ID, null, self::RIGHT, self::at());

        $this->assertSame(CodeAttempt::Wrong, $challenge->attempt(self::RIGHT, self::at('+1 minute')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function idsNotFromTheGenerator(): iterable
    {
        yield 'too short' => ['0123456789abcdef'];
        yield 'capitals' => ['0123456789ABCDEF0123456789ABCDEF'];
        yield 'trailing newline' => [self::ID . "\n"];
    }

    #[DataProvider('idsNotFromTheGenerator')]
    #[Test]
    public function it_refuses_an_id_that_is_not_128_random_bits_in_hex(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);

        LoginChallenge::open($id, Uuid::v7(), self::RIGHT, self::at());
    }

    private static function challenge(): LoginChallenge
    {
        return LoginChallenge::open(self::ID, Uuid::v7(), self::RIGHT, self::at());
    }

    private static function at(string $after = '+0 seconds'): DateTimeImmutable
    {
        return new DateTimeImmutable(self::OPENED)->modify($after);
    }
}
