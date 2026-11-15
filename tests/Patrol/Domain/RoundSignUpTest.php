<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain;

use App\Patrol\Domain\RoundHasStarted;
use App\Patrol\Domain\RoundIsFull;
use App\Patrol\Domain\RoundStatus;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Signing up for a round. Every round starts on 20 November 2026 at 22:00 in Europe/Amsterdam, 21:00 in UTC.
 */
final class RoundSignUpTest extends TestCase
{
    private Uuid $creator;

    #[Override]
    protected function setUp(): void
    {
        $this->creator = Uuid::v7();
    }

    #[Test]
    public function it_records_who_signed_a_walker_up_on_their_behalf(): void
    {
        $round = RoundFixture::withWalkers($this->creator, 1);
        $walker = Uuid::v7();
        $coordinator = Uuid::v7();

        $round->signUp($walker, new DateTimeImmutable(RoundFixture::BEFORE_START), onBehalfOf: $coordinator);

        $this->assertEquals($coordinator, $round->participationOf($walker)?->onBehalfOf());
    }

    #[Test]
    public function it_ignores_a_second_sign_up_of_the_same_member(): void
    {
        $round = RoundFixture::withWalkers($this->creator, 1);
        $walker = Uuid::v7();
        $round->signUp($walker, new DateTimeImmutable(RoundFixture::BEFORE_START));

        $joined = $round->signUp($walker, new DateTimeImmutable(RoundFixture::BEFORE_START));

        $this->assertFalse($joined);
        $this->assertSame(2, $round->walkerCount());
    }

    #[Test]
    public function it_refuses_a_sign_up_after_the_start(): void
    {
        $round = RoundFixture::withWalkers($this->creator, 1);

        $this->expectException(RoundHasStarted::class);

        $round->signUp(Uuid::v7(), new DateTimeImmutable(RoundFixture::AT_START));
    }

    #[Test]
    public function it_refuses_a_sign_up_when_the_round_is_full(): void
    {
        $round = RoundFixture::withWalkers($this->creator, 2, maximum: 2);

        $this->expectException(RoundIsFull::class);

        $round->signUp(Uuid::v7(), new DateTimeImmutable(RoundFixture::BEFORE_START));
    }

    #[Test]
    public function it_fills_the_last_open_spot(): void
    {
        $round = RoundFixture::withWalkers($this->creator, 1, maximum: 2);

        $joined = $round->signUp(Uuid::v7(), new DateTimeImmutable(RoundFixture::BEFORE_START));

        $this->assertTrue($joined);
        $this->assertSame(2, $round->walkerCount());
    }

    #[Test]
    public function it_ignores_a_second_sign_up_in_a_full_round(): void
    {
        $round = RoundFixture::withWalkers($this->creator, 1, maximum: 2);
        $walker = Uuid::v7();
        $round->signUp($walker, new DateTimeImmutable(RoundFixture::BEFORE_START));

        $joined = $round->signUp($walker, new DateTimeImmutable(RoundFixture::BEFORE_START));

        $this->assertFalse($joined);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function openSpotsAroundTheStart(): iterable
    {
        yield 'a minute before the start' => [RoundFixture::BEFORE_START, 5];
        yield 'at the start' => [RoundFixture::AT_START, 0];
    }

    #[DataProvider('openSpotsAroundTheStart')]
    #[Test]
    public function it_has_no_open_spots_once_started(string $now, int $expected): void
    {
        $round = RoundFixture::withWalkers($this->creator, 2, maximum: 7);

        $this->assertSame($expected, $round->openSpots(new DateTimeImmutable($now)));
    }

    #[Test]
    public function it_reports_a_full_round_as_staffed(): void
    {
        $round = RoundFixture::withWalkers($this->creator, 3, maximum: 3);

        $this->assertSame(RoundStatus::Staffed, $round->status(new DateTimeImmutable(RoundFixture::BEFORE_START)));
        $this->assertSame(0, $round->openSpots(new DateTimeImmutable(RoundFixture::BEFORE_START)));
    }
}
