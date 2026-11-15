<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * One line in the append-only audit log. No actor means the system acted; an actor without a name is a member who
 * was erased, shown as "removed member".
 *
 * The changed fields are JSON text, not a JSON column: MariaDB and MySQL store a JSON column differently, so one
 * migration would give two schemas.
 */
#[ORM\Entity]
#[ORM\Index(name: 'audit_entry_actor', columns: ['actor_id'])]
#[ORM\Index(name: 'audit_entry_subject', columns: ['subject_id'])]
#[ORM\Index(name: 'audit_entry_occurred_at', columns: ['occurred_at'])]
final class AuditEntry
{
    private const string SECRET_FIELD = '/password|secret|token|hash/i';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private readonly Uuid $id;

    #[ORM\Column(type: 'text', nullable: true)]
    private readonly ?string $changes;

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

    public function eraseActorName(): void
    {
        $this->actorName = null;
    }
}
