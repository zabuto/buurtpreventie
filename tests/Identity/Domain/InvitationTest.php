<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\Invitation;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final class InvitationTest extends TestCase
{
    // @mago-expect lint:no-literal-password - a made-up secret in the generated format
    private const string SECRET = 'bpi_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    private const string ISSUED = '2026-11-16T10:00:00+00:00';

    #[Test]
    public function it_is_accepted_once(): void
    {
        $invitation = self::invitation();

        $first = $invitation->accept(self::at('+1 hour'));
        $second = $invitation->accept(self::at('+2 hours'));

        $this->assertSame([true, false], [$first, $second]);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function momentsAroundTwoDays(): iterable
    {
        yield 'one second before 48 hours' => ['+47 hours 59 minutes 59 seconds', true];
        yield 'at 48 hours' => ['+48 hours', false];
    }

    #[DataProvider('momentsAroundTwoDays')]
    #[Test]
    public function it_is_valid_for_48_hours(string $after, bool $accepted): void
    {
        $this->assertSame($accepted, self::invitation()->accept(self::at($after)));
    }

    #[Test]
    public function it_is_not_accepted_once_a_newer_invitation_replaced_it(): void
    {
        $invitation = self::invitation();

        $invitation->end(self::at('+1 hour'));

        $this->assertFalse($invitation->accept(self::at('+2 hours')));
    }

    #[Test]
    public function it_stores_only_the_sha_256_hash_of_its_secret(): void
    {
        $this->assertSame(\hash('sha256', self::SECRET, binary: true), self::invitation()->secretHash());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function secretsNotFromTheGenerator(): iterable
    {
        yield 'token prefix' => ['bpt_' . \str_repeat('a', 64)];
        yield 'too short' => ['bpi_' . \str_repeat('a', 63)];
        yield 'trailing newline' => [self::SECRET . "\n"];
    }

    #[DataProvider('secretsNotFromTheGenerator')]
    #[Test]
    public function it_refuses_a_secret_in_another_format(#[SensitiveParameter] string $secret): void
    {
        $this->expectException(InvalidArgumentException::class);

        Invitation::issue(Uuid::v7(), $secret, self::at());
    }

    private static function invitation(): Invitation
    {
        return Invitation::issue(Uuid::v7(), self::SECRET, self::at());
    }

    private static function at(string $after = '+0 seconds'): DateTimeImmutable
    {
        return new DateTimeImmutable(self::ISSUED)->modify($after);
    }
}
