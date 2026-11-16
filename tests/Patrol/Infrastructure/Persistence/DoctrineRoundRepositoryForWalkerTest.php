<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Infrastructure\Persistence;

use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\ListingPeriod;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Patrol\Domain\Round\WalkerRoundQuery;
use App\Patrol\Domain\Round\WalkerRoundView;
use App\Patrol\Infrastructure\Persistence\DoctrineRoundRepository;
use App\Tests\Database\TestDatabase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The query behind GET /me/rounds. Each test walks with a member of its own, so rounds of other tests never match.
 */
#[Group('database')]
final class DoctrineRoundRepositoryForWalkerTest extends KernelTestCase
{
    private const string NOW = '2026-11-16T12:00:00+00:00';

    private EntityManagerInterface $entityManager;
    private DoctrineRoundRepository $repository;
    private Uuid $member;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineRoundRepository($entityManager);
        $this->member = Uuid::v7();
    }

    #[Test]
    public function it_finds_the_rounds_of_the_member_that_have_not_started_earliest_first(): void
    {
        $later = $this->walked('2026-11-25T20:00:00+00:00');
        $sooner = $this->walked('2026-11-18T20:00:00+00:00');
        $this->walked('2026-11-10T20:00:00+00:00');
        $this->round('2026-11-17T20:00:00+00:00', Uuid::v7());
        $this->flushAndForget();

        $found = $this->repository->forWalker($this->query(WalkerRoundView::Upcoming));

        $this->assertSame(self::ids($sooner, $later), self::ids(...$found));
    }

    /**
     * Walked is started, staffed and not cancelled, as RoundStatus derives it.
     */
    #[Test]
    public function it_finds_the_walked_rounds_of_the_member_without_a_report_latest_first(): void
    {
        $earlier = $this->walked('2026-11-01T20:00:00+00:00');
        $later = $this->walked('2026-11-10T20:00:00+00:00');
        $this->reported($this->walked('2026-11-12T20:00:00+00:00'));
        $this->walked('2026-11-13T20:00:00+00:00')->cancel('Regen', new DateTimeImmutable(self::NOW));
        $this->round('2026-11-14T20:00:00+00:00', $this->member);
        $this->walked('2026-11-20T20:00:00+00:00');
        $this->flushAndForget();

        $found = $this->repository->forWalker($this->query(WalkerRoundView::ToReport, newestFirst: true));

        $this->assertSame(self::ids($later, $earlier), self::ids(...$found));
    }

    #[Test]
    public function it_finds_every_round_of_the_member_that_starts_in_the_period(): void
    {
        $this->walked('2026-10-31T23:00:00+00:00');
        $first = $this->walked('2026-11-01T00:00:00+00:00');
        $reported = $this->reported($this->walked('2026-11-10T20:00:00+00:00'));
        $upcoming = $this->walked('2026-11-20T20:00:00+00:00');
        $this->walked('2026-12-01T00:00:00+00:00');
        $this->flushAndForget();

        $found = $this->repository->forWalker($this->query(
            WalkerRoundView::All,
            new ListingPeriod(new DateTimeImmutable('2026-11-01T00:00:00+00:00'), new DateTimeImmutable('2026-12-01T00:00:00+00:00')),
            newestFirst: true,
        ));

        $this->assertSame(self::ids($upcoming, $reported, $first), self::ids(...$found));
    }

    #[Test]
    public function it_gives_no_more_rounds_than_the_limit(): void
    {
        $first = $this->walked('2026-11-18T20:00:00+00:00');
        $second = $this->walked('2026-11-19T20:00:00+00:00');
        $this->walked('2026-11-20T20:00:00+00:00');
        $this->flushAndForget();

        $found = $this->repository->forWalker($this->query(WalkerRoundView::Upcoming, limit: 2));

        $this->assertSame(self::ids($first, $second), self::ids(...$found));
    }

    private function query(
        WalkerRoundView $view,
        ?ListingPeriod $period = null,
        bool $newestFirst = false,
        int $limit = 50,
    ): WalkerRoundQuery {
        return new WalkerRoundQuery($this->member, $view, new DateTimeImmutable(self::NOW), $period, $newestFirst, $limit);
    }

    /**
     * A round of the member with a buddy, so it is staffed and counts as walked once it started.
     */
    private function walked(string $startsAt): Round
    {
        $round = $this->round($startsAt, $this->member);
        $round->signUp(Uuid::v7(), new DateTimeImmutable('2026-10-01T12:00:00+00:00'));

        return $round;
    }

    private function round(string $startsAt, Uuid $firstWalker): Round
    {
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable($startsAt)),
            $firstWalker,
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            new DateTimeImmutable('2026-10-01T12:00:00+00:00'),
        );
        $this->repository->add($round);

        return $round;
    }

    private function reported(Round $round): Round
    {
        $outcome = Outcome::create('Rustig ' . \bin2hex(\random_bytes(4)), OutcomeKind::NothingToReport);
        $this->entityManager->persist($outcome);
        $round->submitReport($this->member, $outcome, null, new DateTimeImmutable(self::NOW));

        return $round;
    }

    private function flushAndForget(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @return list<string>
     */
    private static function ids(Round ...$rounds): array
    {
        return \array_values(\array_map(static fn(Round $round): string => $round->id()->toRfc4122(), $rounds));
    }
}
