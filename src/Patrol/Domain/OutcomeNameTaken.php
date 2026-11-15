<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * Another outcome has the same name on its lookup key. When that one is archived, the admin restores it instead of
 * adding a look-alike.
 */
final class OutcomeNameTaken extends DomainException
{
    private function __construct(
        string $message,
        public readonly Uuid $existingId,
        public readonly bool $existingIsArchived,
    ) {
        parent::__construct($message);
    }

    public static function by(Outcome $existing): self
    {
        return self::existing($existing->id(), $existing->name(), $existing->isArchived());
    }

    /**
     * For the outcome another request committed, which this entity manager never loaded.
     */
    public static function existing(Uuid $id, string $name, bool $archived): self
    {
        return new self(
            \sprintf(
                $archived ? 'The archived outcome "%s" (%s) has this name; restore it instead.' : 'The outcome "%s" (%s) has this name.',
                $name,
                $id->toRfc4122(),
            ),
            $id,
            $archived,
        );
    }
}
