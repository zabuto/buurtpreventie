<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * What the admin narrows the audit log to; a null field narrows nothing.
 */
final readonly class AuditFilter
{
    /**
     * @param Uuid|null $member entries the member made, and entries about the member
     * @param DateTimeImmutable|null $from from this moment on
     * @param DateTimeImmutable|null $to before this moment
     */
    public function __construct(
        public ?Uuid $member = null,
        public ?string $action = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
    ) {
    }

    public function matches(AuditEntry $entry): bool
    {
        return (
            (null === $this->member || $this->isAboutMember($entry, $this->member))
                && (null === $this->action || $entry->action() === $this->action)
                && (null === $this->from || $entry->occurredAt() >= $this->from)
                && (null === $this->to || $entry->occurredAt() < $this->to)
        );
    }

    private function isAboutMember(AuditEntry $entry, Uuid $member): bool
    {
        return true === $entry->actorId()?->equals($member) || 'member' === $entry->subjectType() && $entry->subjectId()->equals($member);
    }
}
