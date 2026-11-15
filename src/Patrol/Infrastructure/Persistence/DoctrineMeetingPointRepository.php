<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Persistence;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\MeetingPointNameTaken;
use App\Patrol\Domain\Catalogue\MeetingPointRepository;
use App\Shared\Domain\LookupKey;
use App\Shared\Domain\NotFound;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMeetingPointRepository implements MeetingPointRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(Uuid $id): MeetingPoint
    {
        return $this->entityManager->find(MeetingPoint::class, $id) ?? throw NotFound::forId('meeting point', $id);
    }

    #[Override]
    public function findByNameKey(LookupKey $nameKey): ?MeetingPoint
    {
        return $this->entityManager->getRepository(MeetingPoint::class)->findOneBy(['nameKey' => $nameKey->value]);
    }

    #[Override]
    public function add(MeetingPoint $meetingPoint): void
    {
        $this->entityManager->persist($meetingPoint);
        $this->write($meetingPoint);
    }

    #[Override]
    public function update(MeetingPoint $meetingPoint): void
    {
        $this->write($meetingPoint);
    }

    /**
     * Flushes now rather than at the commit, so a name another request took in the meantime becomes the error the
     * handler would have given.
     *
     * @throws MeetingPointNameTaken
     */
    private function write(MeetingPoint $meetingPoint): void
    {
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw $this->clashWith($meetingPoint->nameKey()) ?? $exception;
        }
    }

    /**
     * A failed flush closes the entity manager, so the item holding the name comes through the connection. The
     * locking read sees the latest commit, which a plain read in this transaction may predate.
     */
    private function clashWith(string $nameKey): ?MeetingPointNameTaken
    {
        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT id, name, archived FROM meeting_point WHERE name_key = ? FOR UPDATE',
            [$nameKey],
        );
        if (false === $row) {
            return null;
        }

        ['id' => $id, 'name' => $name] = $row;
        \assert(\is_string($id) && \is_string($name), 'The columns id and name are BINARY and VARCHAR.');

        return MeetingPointNameTaken::existing(Uuid::fromBinary($id), $name, (bool) $row['archived']);
    }
}
