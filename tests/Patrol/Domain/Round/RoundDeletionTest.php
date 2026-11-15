<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain\Round;

use App\Patrol\Domain\Round\RoundHasStarted;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * A round that started is part of the history: it can be cancelled, not deleted.
 */
final class RoundDeletionTest extends TestCase
{
    #[Test]
    public function it_refuses_deletion_once_started(): void
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 2);

        $this->expectException(RoundHasStarted::class);

        $round->ensureDeletable(new DateTimeImmutable(RoundFixture::AT_START));
    }

    #[Test]
    public function it_allows_deletion_until_the_start(): void
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 2);

        $round->ensureDeletable(new DateTimeImmutable(RoundFixture::BEFORE_START));

        $this->expectNotToPerformAssertions();
    }
}
