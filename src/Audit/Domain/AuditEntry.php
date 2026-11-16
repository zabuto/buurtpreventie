<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * One line in the append-only audit log. No actor means the system acted; an actor without a name is a member who
 * was erased, shown as "removed member".
 *
 * The changed fields are JSON text, not a JSON column: MariaDB and MySQL store a JSON column differently, so one
 * migration would give two schemas.
 *
 * Each entry takes the next position in a chain of hashes, so a change to a stored entry shows. The hash covers what
 * never changes: not the actor's name, and the changed fields only through a hash of their own, as erasing a member
 * removes both. An entry from before the chain has a position but no hash.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'audit_entry_chain_position', columns: ['chain_position'])]
#[ORM\Index(name: 'audit_entry_actor', columns: ['actor_id'])]
#[ORM\Index(name: 'audit_entry_subject', columns: ['subject_id'])]
#[ORM\Index(name: 'audit_entry_occurred_at', columns: ['occurred_at'])]
final class AuditEntry
{
    private const string SECRET_FIELD = '/password|secret|token|hash/i';
    private const string MEMBER_SUBJECT = 'member';
    private const int CHAIN_VERSION = 1;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private readonly Uuid $id;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $changes;

    #[ORM\Column(type: 'bigint')]
    private ?int $chainPosition = null;

    #[ORM\Column(type: 'binary', length: 32, nullable: true, options: ['fixed' => true])]
    private ?string $changesHash = null;

    #[ORM\Column(type: 'binary', length: 32, nullable: true, options: ['fixed' => true])]
    private ?string $chainHash = null;

    private function __construct(
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private readonly DateTimeImmutable $occurredAt,
        #[ORM\Column(type: 'uuid', nullable: true)]
        private readonly ?Uuid $actorId,
        #[ORM\Column(length: 100, nullable: true)]
        private ?string $actorName,
        #[ORM\Column(length: 60)]
        private readonly string $action,
        #[ORM\Column(length: 30)]
        private readonly string $subjectType,
        #[ORM\Column(type: 'uuid')]
        private readonly Uuid $subjectId,
        #[ORM\Column(length: 10, enumType: Channel::class)]
        private readonly Channel $channel,
        string $changes,
    ) {
        $this->id = Uuid::v7();
        $this->changes = '[]' === $changes ? null : $changes;
    }

    /**
     * @param Uuid|null $actorId null when the system acts
     * @param string|null $actorName the actor's display name at this moment
     *
     * @throws InvalidArgumentException for a change to a field that holds a password, token or other secret
     */
    public static function of(
        AuditedAction $action,
        ?Uuid $actorId,
        ?string $actorName,
        Channel $channel,
        DateTimeImmutable $now,
    ): self {
        foreach (\array_keys($action->changes) as $field) {
            if (1 === \preg_match(self::SECRET_FIELD, $field)) {
                throw new InvalidArgumentException(\sprintf('The audit log never holds the field "%s".', $field));
            }
        }

        return new self(
            $now,
            $actorId,
            $actorName,
            $action->action,
            $action->subjectType,
            $action->subjectId,
            $channel,
            \json_encode($action->changes, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function actorId(): ?Uuid
    {
        return $this->actorId;
    }

    public function actorName(): ?string
    {
        return $this->actorName;
    }

    public function isBySystem(): bool
    {
        return null === $this->actorId;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function subjectType(): string
    {
        return $this->subjectType;
    }

    public function subjectId(): Uuid
    {
        return $this->subjectId;
    }

    public function channel(): Channel
    {
        return $this->channel;
    }

    /**
     * @return array<string, array{old: scalar|null, new: scalar|null}>
     */
    public function changes(): array
    {
        if (null === $this->changes) {
            return [];
        }

        /** @var array<string, array{old: scalar|null, new: scalar|null}> */
        return \json_decode($this->changes, associative: true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Whether the entry concerns a member other than $memberId, such as a role an admin gave: what a member reads of
     * their own actions then leaves out who it was and what changed, as that is about someone else.
     */
    public function isAboutAnotherMemberThan(Uuid $memberId): bool
    {
        return self::MEMBER_SUBJECT === $this->subjectType && !$this->subjectId->equals($memberId);
    }

    public function eraseActorName(): void
    {
        $this->actorName = null;
    }

    /**
     * Removes the changed fields of an entry about an erased member; their hash stays, so the chain holds.
     */
    public function eraseChanges(): void
    {
        $this->changes = null;
    }

    public function hasErasedChanges(): bool
    {
        return null === $this->changes && null !== $this->changesHash;
    }

    /**
     * Takes the position after $previous and signs the entry with the hash of $previous in it.
     *
     * @throws LogicException for an entry that has a position
     */
    public function link(ChainLink $previous, ChainHasher $hasher): ChainLink
    {
        if (null !== $this->chainPosition) {
            throw new LogicException(\sprintf('Audit entry %s has position %d in the chain.', $this->id, $this->chainPosition));
        }

        $position = $previous->position + 1;
        $this->chainPosition = $position;
        $this->changesHash = null === $this->changes ? null : $hasher->hash([$this->id->toRfc4122(), $this->changes]);
        $hash = $this->chainHashAfter($previous, $hasher);
        $this->chainHash = $hash;

        return new ChainLink($position, $hash);
    }

    public function chainPosition(): ?int
    {
        return $this->chainPosition;
    }

    /**
     * @return ChainLink|null null for an entry without a hash: not linked, or from before the chain
     */
    public function chainLink(): ?ChainLink
    {
        if (null === $this->chainPosition || null === $this->chainHash) {
            return null;
        }

        return new ChainLink($this->chainPosition, $this->chainHash);
    }

    /**
     * Whether the entry takes the position after $previous with a hash that fits its stored values and $previous.
     */
    public function linksTo(ChainLink $previous, ChainHasher $hasher): bool
    {
        return (
            null !== $this->chainHash
                && $this->chainPosition === ($previous->position + 1)
                && \hash_equals($this->chainHash, $this->chainHashAfter($previous, $hasher))
        );
    }

    /**
     * Whether the changed fields match their own hash. Erased fields cannot be checked and count as intact.
     */
    public function hasIntactChanges(ChainHasher $hasher): bool
    {
        if (null === $this->changes) {
            return true;
        }

        return (
            null !== $this->changesHash
                && \hash_equals($this->changesHash, $hasher->hash([$this->id->toRfc4122(), $this->changes]))
        );
    }

    /**
     * The fields and their order are fixed by the version: another form makes every stored hash fail. The time is in
     * whole seconds, as the database stores it.
     */
    private function chainHashAfter(ChainLink $previous, ChainHasher $hasher): string
    {
        return $hasher->hash([
            self::CHAIN_VERSION,
            $this->chainPosition,
            $this->id->toRfc4122(),
            $this->occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z'),
            $this->actorId?->toRfc4122(),
            $this->action,
            $this->subjectType,
            $this->subjectId->toRfc4122(),
            $this->channel->value,
            null === $this->changesHash ? null : \bin2hex($this->changesHash),
            \bin2hex($previous->hash),
        ]);
    }
}
