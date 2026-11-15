<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Persistence;

use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeNameTaken;
use App\Patrol\Domain\Catalogue\OutcomeRepository;
use App\Shared\Domain\LookupKey;
use App\Shared\Domain\NotFound;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineOutcomeRepository implements OutcomeRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(Uuid $id): Outcome
    {
        return $this->entityManager->find(Outcome::class, $id) ?? throw NotFound::forId('outcome', $id);
    }

    #[Override]
    public function findByNameKey(LookupKey $nameKey): ?Outcome
    {
        return $this->entityManager->getRepository(Outcome::class)->findOneBy(['nameKey' => $nameKey->value]);
    }

    #[Override]
    public function add(Outcome $outcome): void
    {
        $this->entityManager->persist($outcome);
        $this->write($outcome);
    }

    #[Override]
    public function update(Outcome $outcome): void
    {
        $this->write($outcome);
    }

    /**
     * Flushes now rather than at the commit, so a name another request took in the meantime becomes the error the
     * handler would have given.
     *
     * @throws OutcomeNameTaken
     */
    private function write(Outcome $outcome): void
    {
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw $this->clashWith($outcome->nameKey()) ?? $exception;
        }
    }

    /**
     * A failed flush closes the entity manager, so the item holding the name comes through the connection. The
     * locking read sees the latest commit, which a plain read in this transaction may predate.
     */
    private function clashWith(string $nameKey): ?OutcomeNameTaken
    {
        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT id, name, archived FROM outcome WHERE name_key = ? FOR UPDATE',
            [$nameKey],
        );
        if (false === $row) {
            return null;
        }

        ['id' => $id, 'name' => $name] = $row;
        \assert(\is_string($id) && \is_string($name), 'The columns id and name are BINARY and VARCHAR.');

        return OutcomeNameTaken::existing(Uuid::fromBinary($id), $name, (bool) $row['archived']);
    }
}
