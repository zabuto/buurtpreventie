<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain\Catalogue;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\MeetingPointInUse;
use App\Patrol\Domain\Catalogue\MeetingPointInUseGuard;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MeetingPointInUseGuardTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    private InMemoryRoundRepository $rounds;
    private MeetingPoint $speeltuin;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->speeltuin = MeetingPoint::create('Speeltuin');
    }

    /**
     * Walkers of an upcoming round must know where to meet; archiving would leave them a place nobody can pick.
     */
    #[Test]
    public function it_refuses_a_meeting_point_where_upcoming_rounds_start_and_counts_them(): void
    {
        $this->roundAt('2026-11-16T21:00:00+00:00');
        $this->roundAt('2026-11-22T21:00:00+00:00');

        $refusal = null;
        try {
            new MeetingPointInUseGuard($this->rounds)->refuseWhileInUse($this->speeltuin, new DateTimeImmutable(self::NOW));
        } catch (MeetingPointInUse $exception) {
            $refusal = $exception;
        }

        $this->assertEquals(MeetingPointInUse::byUpcomingRounds($this->speeltuin->id(), 2), $refusal);
    }

    #[Test]
    public function it_lets_go_of_a_meeting_point_where_only_rounds_that_started_began(): void
    {
        $this->roundAt(self::NOW);

        new MeetingPointInUseGuard($this->rounds)->refuseWhileInUse($this->speeltuin, new DateTimeImmutable(self::NOW));

        $this->expectNotToPerformAssertions();
    }

    private function roundAt(string $startsAt): void
    {
        $this->rounds->add(Round::plan(
            new RoundStart(new DateTimeImmutable($startsAt), $this->speeltuin),
            Uuid::v7(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            new DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        ));
    }
}
