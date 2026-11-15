<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * Another meeting point has the same name on its lookup key. When that one is archived, the admin restores it instead of
 * adding a look-alike.
 */
final class MeetingPointNameTaken extends DomainException
{
    private function __construct(
        string $message,
        public readonly Uuid $existingId,
        public readonly bool $existingIsArchived,
    ) {
        parent::__construct($message);
    }

    public static function by(MeetingPoint $existing): self
    {
        return new self(
            \sprintf(
                $existing->isArchived()
                    ? 'The archived meeting point "%s" (%s) has this name; restore it instead.'
                    : 'The meeting point "%s" (%s) has this name.',
                $existing->name(),
                $existing->id()->toRfc4122(),
            ),
            $existing->id(),
            $existing->isArchived(),
        );
    }
}
