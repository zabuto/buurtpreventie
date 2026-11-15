<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Persistence;

use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundComment;
use App\Patrol\Domain\Round\RoundCommentRepository;
use App\Shared\Domain\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineRoundCommentRepository implements RoundCommentRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(Uuid $id): RoundComment
    {
        return $this->entityManager->find(RoundComment::class, $id) ?? throw NotFound::forId('round comment', $id);
    }

    #[Override]
    public function add(RoundComment $roundComment): void
    {
        $this->entityManager->persist($roundComment);
    }

    #[Override]
    public function remove(RoundComment $roundComment): void
    {
        $this->entityManager->remove($roundComment);
    }

    #[Override]
    public function forRound(Round $round): array
    {
        /** @var list<RoundComment> */
        return $this->entityManager
            ->getRepository(RoundComment::class)
            ->findBy(['round' => $round], ['postedAt' => 'ASC', 'id' => 'ASC'])
        ;
    }
}
