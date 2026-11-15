<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A walker signed up for a round. The member lives in another module, so it is held by id: no foreign key crosses a
 * module boundary.
 *
 * No property is readonly: locking a round refreshes its walkers, which Doctrine refuses for a readonly object value.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'participation_round_member', columns: ['round_id', 'member_id'])]
#[ORM\Index(name: 'participation_member', columns: ['member_id'])]
#[ORM\Index(name: 'participation_on_behalf_of', columns: ['on_behalf_of'])]
final class Participation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $remindedAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Round::class, inversedBy: 'participations')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Round $round,
        #[ORM\Column(type: 'uuid')]
        private Uuid $memberId,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private DateTimeImmutable $joinedAt,
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $onBehalfOf = null,
    ) {
        $this->id = Uuid::v7();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function round(): Round
    {
        return $this->round;
    }

    public function memberId(): Uuid
    {
        return $this->memberId;
    }

    public function joinedAt(): DateTimeImmutable
    {
        return $this->joinedAt;
    }

    /**
     * The coordinator who signed the walker up or created the round for them, or null when the walker did it.
     */
    public function onBehalfOf(): ?Uuid
    {
        return $this->onBehalfOf;
    }

    /**
     * @param iterable<self> $participations
     */
    public static function earliest(iterable $participations): ?self
    {
        $earliest = null;
        foreach ($participations as $participation) {
            if (null !== $earliest && !$participation->joinedBefore($earliest)) {
                continue;
            }

            $earliest = $participation;
        }

        return $earliest;
    }

    /**
     * Joining earlier wins; for the same instant, the older id, as UUIDv7 ids sort by creation.
     */
    public function joinedBefore(self $other): bool
    {
        if ($this->joinedAt < $other->joinedAt) {
            return true;
        }

        if ($this->joinedAt > $other->joinedAt) {
            return false;
        }

        return \strcmp($this->id->toBinary(), $other->id->toBinary()) < 0;
    }

    public function remindedAt(): ?DateTimeImmutable
    {
        return $this->remindedAt;
    }

    public function markReminded(DateTimeImmutable $now): void
    {
        $this->remindedAt = $now;
    }
}
