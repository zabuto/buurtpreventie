<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Persistence;

use App\Patrol\Domain\Round\RoundReport;
use App\Patrol\Domain\Round\RoundReportRepository;
use App\Shared\Domain\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineRoundReportRepository implements RoundReportRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(Uuid $id): RoundReport
    {
        return $this->entityManager->find(RoundReport::class, $id) ?? throw NotFound::forId('round report', $id);
    }
}
