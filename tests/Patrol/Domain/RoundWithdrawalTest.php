<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain;

use App\Patrol\Domain\RoundHasStarted;
use App\Patrol\Domain\Withdrawal;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Withdrawing from a round. Every round starts on 20 November 2026 at 22:00 in Europe/Amsterdam, 21:00 in UTC.
 */
final class RoundWithdrawalTest extends TestCase
{
    private Uuid $firstWalker;

    #[Override]
    protected function setUp(): void
    {
        $this->firstWalker = Uuid::v7();
    }

    #[Test]
    public function it_lets_a_walker_withdraw_before_the_start(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 1);
        $walker = Uuid::v7();
        $round->signUp($walker, new DateTimeImmutable(RoundFixture::BEFORE_START));

        $withdrawal = $round->withdraw($walker, new DateTimeImmutable(RoundFixture::BEFORE_START));

        $this->assertSame(Withdrawal::Withdrawn, $withdrawal);
        $this->assertFalse($round->isSignedUp($walker));
        $this->assertSame(1, $round->walkerCount());
    }

    #[Test]
    public function it_ignores_a_withdrawal_of_a_member_who_is_not_signed_up(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 1);

        $withdrawal = $round->withdraw(Uuid::v7(), new DateTimeImmutable(RoundFixture::BEFORE_START));

        $this->assertSame(Withdrawal::NotSignedUp, $withdrawal);
        $this->assertSame(1, $round->walkerCount());
    }

    #[Test]
    public function it_asks_for_deletion_when_the_last_walker_withdraws(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 1);

        $withdrawal = $round->withdraw($this->firstWalker, new DateTimeImmutable(RoundFixture::BEFORE_START));

        $this->assertSame(Withdrawal::LastWalkerLeft, $withdrawal);
        $this->assertSame(0, $round->walkerCount());
    }

    #[Test]
    public function it_refuses_a_withdrawal_after_the_start(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 2);

        $this->expectException(RoundHasStarted::class);

        $round->withdraw($this->firstWalker, new DateTimeImmutable(RoundFixture::AT_START));
    }

    #[Test]
    public function it_removes_an_inactive_member_from_rounds_that_have_not_started(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 1);
        $inactive = Uuid::v7();
        $round->signUp($inactive, new DateTimeImmutable(RoundFixture::BEFORE_START));

        $withdrawal = $round->withdrawInactiveMember($inactive, new DateTimeImmutable(RoundFixture::BEFORE_START));

        $this->assertSame(Withdrawal::Withdrawn, $withdrawal);
        $this->assertFalse($round->isSignedUp($inactive));
    }

    #[Test]
    public function it_keeps_an_inactive_member_in_a_round_that_has_started(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 1);
        $inactive = Uuid::v7();
        $round->signUp($inactive, new DateTimeImmutable(RoundFixture::BEFORE_START));

        $withdrawal = $round->withdrawInactiveMember($inactive, new DateTimeImmutable(RoundFixture::AT_START));

        $this->assertNull($withdrawal);
        $this->assertTrue($round->isSignedUp($inactive));
    }
}
