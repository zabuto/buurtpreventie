<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Infrastructure\Persistence;

use App\Patrol\Domain\Outcome;
use App\Patrol\Domain\OutcomeKind;
use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundStart;
use App\Patrol\Domain\StaffingRule;
use App\Patrol\Infrastructure\Persistence\DoctrineRoundReportRepository;
use App\Shared\Domain\NotFound;
use App\Tests\Database\TestDatabase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

#[Group('database')]
final class DoctrineRoundReportRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineRoundReportRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineRoundReportRepository($entityManager);
    }

    #[Test]
    public function it_brings_a_report_stored_with_its_round_back_by_id(): void
    {
        $firstWalker = Uuid::v7();
        $outcome = Outcome::create('Opmerkingen ' . Uuid::v7()->toBase58(), OutcomeKind::Remarks);
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable('2026-11-10T21:00:00+00:00')),
            $firstWalker,
            new StaffingRule(minimumWalkers: 1, maximumWalkers: 7),
            new DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        );
        $report = $round->submitReport($firstWalker, $outcome, 'Poort stond open.', new DateTimeImmutable('2026-11-10T23:00:00+00:00'));
        $this->entityManager->persist($outcome);
        $this->entityManager->persist($round);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->repository->get($report->id());

        $this->assertSame([$round->id()->toRfc4122(), 'Poort stond open.'], [$read->round()->id()->toRfc4122(), $read->note()]);
    }

    #[Test]
    public function it_reports_an_unknown_report(): void
    {
        $this->expectException(NotFound::class);

        $this->repository->get(Uuid::v7());
    }
}
