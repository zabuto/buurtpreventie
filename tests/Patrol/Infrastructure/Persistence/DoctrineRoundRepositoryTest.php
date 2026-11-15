<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Infrastructure\Persistence;

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
        $creator = Uuid::v7();
        $round = $this->round(new DateTimeImmutable('2026-11-20T21:00:00+00:00'), $creator);

        $read = $this->storeAndReadBack($round);

        $this->assertEquals([$creator], $read->walkerIds());
        $this->assertEquals(new StaffingRule(minimumWalkers: 2, maximumWalkers: 7), $read->staffingRule());
    }

    #[Test]
    public function it_reports_an_unknown_round(): void
    {
        $this->expectException(NotFound::class);

        $this->repository->get(Uuid::v7());
    }

    private function storeAndReadBack(Round $round): Round
    {
        $this->repository->add($round);
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $this->repository->get($round->id());
    }

    private function round(DateTimeImmutable $startsAt, ?Uuid $creator = null): Round
    {
        return Round::plan(
            new RoundStart($startsAt),
            $creator ?? Uuid::v7(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
        );
    }
}
