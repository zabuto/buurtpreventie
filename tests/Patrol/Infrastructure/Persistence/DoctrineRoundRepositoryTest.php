<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Infrastructure\Persistence;

use App\Patrol\Domain\MeetingPoint;
use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundStart;
use App\Patrol\Domain\StaffingRule;
use App\Patrol\Infrastructure\Persistence\DoctrineRoundRepository;
use App\Shared\Domain\NotFound;
use App\Tests\Database\TestDatabase;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Every round is read back after the entity manager forgot it, so the values come from the database.
 */
#[Group('database')]
final class DoctrineRoundRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineRoundRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineRoundRepository($entityManager);
    }

    /**
     * In 2026 summer time starts on 29 March and ends on 25 October, in Europe/Amsterdam.
     *
     * @return iterable<string, array{string}>
     */
    public static function eveningsAroundTheClockChanges(): iterable
    {
        yield 'the evening before summer time' => ['2026-03-28'];
        yield 'the first evening of summer time' => ['2026-03-29'];
        yield 'the last evening of summer time' => ['2026-10-24'];
        yield 'the first evening of winter time' => ['2026-10-25'];
    }

    #[DataProvider('eveningsAroundTheClockChanges')]
    #[Test]
    public function it_brings_a_round_at_ten_in_the_evening_back_at_the_same_instant(string $date): void
    {
        $startsAt = new DateTimeImmutable($date . 'T22:00:00', new DateTimeZone('Europe/Amsterdam'));
        $round = $this->round($startsAt);

        $read = $this->storeAndReadBack($round);

        $this->assertEquals($startsAt, $read->startsAt());
        $this->assertSame($date . ' 22:00', $read->startsAt()->setTimezone(new DateTimeZone('Europe/Amsterdam'))->format('Y-m-d H:i'));
    }

    #[Test]
    public function it_stores_the_walkers_and_the_staffing_rule_with_the_round(): void
    {
        $firstWalker = Uuid::v7();
        $round = $this->round(new DateTimeImmutable('2026-11-20T21:00:00+00:00'), $firstWalker);

        $read = $this->storeAndReadBack($round);

        $this->assertEquals([$firstWalker], $read->walkerIds());
        $this->assertEquals(new StaffingRule(minimumWalkers: 2, maximumWalkers: 7), $read->staffingRule());
    }

    #[Test]
    public function it_reports_an_unknown_round(): void
    {
        $this->expectException(NotFound::class);

        $this->repository->get(Uuid::v7());
    }

    #[Test]
    public function it_finds_only_the_rounds_of_the_member_that_have_not_started(): void
    {
        $member = Uuid::v7();
        $now = new DateTimeImmutable('2026-11-15T12:00:00+00:00');
        $started = $this->round(new DateTimeImmutable('2026-11-15T12:00:00+00:00'), $member);
        $upcoming = $this->round(new DateTimeImmutable('2026-11-15T12:01:00+00:00'), $member);
        $alsoUpcoming = $this->round(new DateTimeImmutable('2026-11-22T21:00:00+00:00'));
        $alsoUpcoming->signUp($member, new DateTimeImmutable('2026-01-01T12:00:00+00:00'));
        $withoutTheMember = $this->round(new DateTimeImmutable('2026-11-16T21:00:00+00:00'));
        foreach ([$started, $upcoming, $alsoUpcoming, $withoutTheMember] as $round) {
            $this->repository->add($round);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->wrapInTransaction(fn(): array => $this->repository->upcomingWithWalker($member, $now));

        $this->assertEqualsCanonicalizing(
            [$upcoming->id()->toRfc4122(), $alsoUpcoming->id()->toRfc4122()],
            \array_map(static fn(Round $round): string => $round->id()->toRfc4122(), $found),
        );
    }

    #[Test]
    public function it_loads_every_walker_of_a_round_it_finds(): void
    {
        $member = Uuid::v7();
        $buddy = Uuid::v7();
        $round = $this->round(new DateTimeImmutable('2026-11-22T21:00:00+00:00'), $member);
        $round->signUp($buddy, new DateTimeImmutable('2026-01-01T12:00:00+00:00'));
        $this->repository->add($round);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->wrapInTransaction(fn(): array => $this->repository->upcomingWithWalker(
            $member,
            new DateTimeImmutable('2026-11-15T12:00:00+00:00'),
        ));

        $this->assertCount(1, $found);
        $this->assertEqualsCanonicalizing([$member, $buddy], $found[0]->walkerIds());
    }

    #[Test]
    public function it_stores_the_cancellation_with_the_round(): void
    {
        $round = $this->round(new DateTimeImmutable('2026-11-20T21:00:00+00:00'));
        $round->cancel('Loopmaatje kwam niet', new DateTimeImmutable('2026-11-20T21:30:00+00:00'));

        $read = $this->storeAndReadBack($round);

        $this->assertSame(
            ['2026-11-20T21:30:00+00:00', 'Loopmaatje kwam niet'],
            [$read->cancellation()->at()?->format(\DATE_ATOM), $read->cancellation()->reason()],
        );
    }

    #[Test]
    public function it_counts_the_rounds_that_have_not_started_at_a_meeting_point(): void
    {
        $speeltuin = MeetingPoint::create('Speeltuin ' . Uuid::v7()->toRfc4122());
        $kerkplein = MeetingPoint::create('Kerkplein ' . Uuid::v7()->toRfc4122());
        $this->entityManager->persist($speeltuin);
        $this->entityManager->persist($kerkplein);
        foreach ([
            '2026-11-15T12:00:00+00:00' => $speeltuin,
            '2026-11-15T12:00:01+00:00' => $speeltuin,
            '2026-11-22T21:00:00+00:00' => $speeltuin,
            '2026-11-16T21:00:00+00:00' => $kerkplein,
        ] as $startsAt => $meetingPoint) {
            $this->repository->add(Round::plan(
                new RoundStart(new DateTimeImmutable($startsAt), $meetingPoint),
                Uuid::v7(),
                new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
                new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
            ));
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        $count = $this->repository->countUpcomingAt($speeltuin, new DateTimeImmutable('2026-11-15T12:00:00+00:00'));

        $this->assertSame(2, $count);
    }

    #[Test]
    public function it_finds_the_rounds_from_the_first_instant_up_to_the_last_by_start(): void
    {
        $justBefore = $this->stored('2026-11-19T22:59:59+00:00');
        $atFrom = $this->stored('2026-11-19T23:00:00+00:00');
        $atTo = $this->stored('2026-11-20T23:00:00+00:00');
        $between = $this->stored('2026-11-20T21:00:00+00:00');
        $this->entityManager->clear();

        $found = $this->repository->between(new DateTimeImmutable('2026-11-19T23:00:00+00:00'), new DateTimeImmutable('2026-11-20T23:00:00+00:00'));

        $this->assertEquals([$atFrom, $between], $this->idsOf($found, $justBefore, $atFrom, $atTo, $between));
    }

    #[Test]
    public function it_finds_the_rounds_that_have_not_started_by_start(): void
    {
        $later = $this->stored('2026-11-22T21:00:00+00:00');
        $started = $this->stored('2026-11-15T12:00:00+00:00');
        $next = $this->stored('2026-11-15T12:00:01+00:00');
        $this->entityManager->clear();

        $found = $this->repository->upcoming(new DateTimeImmutable('2026-11-15T12:00:00+00:00'));

        $this->assertEquals([$next, $later], $this->idsOf($found, $later, $started, $next));
    }

    #[Test]
    public function it_brings_a_round_locked_with_all_its_walkers(): void
    {
        $firstWalker = Uuid::v7();
        $buddy = Uuid::v7();
        $round = $this->round(new DateTimeImmutable('2026-11-20T21:00:00+00:00'), $firstWalker);
        $round->signUp($buddy, new DateTimeImmutable('2026-01-01T12:00:00+00:00'));
        $this->repository->add($round);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->entityManager->wrapInTransaction(fn(): Round => $this->repository->getForUpdate($round->id()));

        $this->assertEqualsCanonicalizing([$firstWalker, $buddy], $read->walkerIds());
    }

    #[Test]
    public function it_refuses_to_lock_an_unknown_round(): void
    {
        $id = Uuid::v7();

        $this->expectExceptionObject(NotFound::forId('round', $id));

        $this->entityManager->wrapInTransaction(fn(): Round => $this->repository->getForUpdate($id));
    }

    private function storeAndReadBack(Round $round): Round
    {
        $this->repository->add($round);
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $this->repository->get($round->id());
    }

    private function round(DateTimeImmutable $startsAt, ?Uuid $firstWalker = null): Round
    {
        return Round::plan(
            new RoundStart($startsAt),
            $firstWalker ?? Uuid::v7(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
        );
    }

    private function stored(string $startsAt): Uuid
    {
        $round = $this->round(new DateTimeImmutable($startsAt));
        $this->repository->add($round);
        $this->entityManager->flush();

        return $round->id();
    }

    /**
     * Only the rounds this test made, in the order found, so rows of other tests cannot interfere.
     *
     * @param list<Round> $found
     *
     * @return list<Uuid>
     */
    private function idsOf(array $found, Uuid ...$ownIds): array
    {
        $own = \array_map(static fn(Uuid $id): string => $id->toRfc4122(), $ownIds);

        return \array_values(\array_filter(
            \array_map(static fn(Round $round): Uuid => $round->id(), $found),
            static fn(Uuid $id): bool => \in_array($id->toRfc4122(), $own, strict: true),
        ));
    }
}
