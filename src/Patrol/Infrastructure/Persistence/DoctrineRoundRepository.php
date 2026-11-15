<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Persistence;

use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundRepository;
use App\Shared\Domain\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineRoundRepository implements RoundRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(Uuid $id): Round
    {
        return $this->entityManager->find(Round::class, $id) ?? throw NotFound::forId('round', $id);
    }

    #[Override]
    public function add(Round $round): void
    {
        $this->entityManager->persist($round);
    }

    #[Override]
    public function remove(Round $round): void
    {
        $this->entityManager->remove($round);
    }
}
