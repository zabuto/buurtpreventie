<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Catalogue;

use App\Shared\Domain\StateConflict;
use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * Another meeting point has the same name on its lookup key. When that one is archived, the admin restores it instead of
 * adding a look-alike.
 */
final class MeetingPointNameTaken extends DomainException implements StateConflict
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
        return self::existing($existing->id(), $existing->name(), $existing->isArchived());
    }

    /**
     * For the meeting point another request committed, which this entity manager never loaded.
     */
    public static function existing(Uuid $id, string $name, bool $archived): self
    {
        return new self(
            \sprintf(
                $archived ? 'The archived meeting point "%s" (%s) has this name; restore it instead.' : 'The meeting point "%s" (%s) has this name.',
                $name,
                $id->toRfc4122(),
            ),
            $id,
            $archived,
        );
    }
}
