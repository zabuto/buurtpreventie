<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Catalogue;

use App\Shared\Domain\LookupKey;
use Symfony\Component\Uid\Uuid;

/**
 * Refuses a meeting point or outcome name another item of the same list has on its lookup key, archived ones
 * included: in a pick list "Café" and "cafe" cannot be told apart. The unique indexes catch two requests at the same
 * moment; this gives the common case its own error, naming the item that holds the name.
 */
final readonly class UniqueCatalogueName
{
    public function __construct(
        private MeetingPointRepository $meetingPoints,
        private OutcomeRepository $outcomes,
    ) {
    }

    /**
     * @param Uuid|null $self the meeting point taking the name, which may take another spelling of its own
     *
     * @throws MeetingPointNameTaken
     */
    public function ensureMeetingPointNameAvailable(string $name, ?Uuid $self = null): void
    {
        $existing = $this->meetingPoints->findByNameKey(LookupKey::fromName($name));
        if (null === $existing || null !== $self && $existing->id()->equals($self)) {
            return;
        }

        throw MeetingPointNameTaken::by($existing);
    }

    /**
     * @param Uuid|null $self the outcome taking the name, which may take another spelling of its own
     *
     * @throws OutcomeNameTaken
     */
    public function ensureOutcomeNameAvailable(string $name, ?Uuid $self = null): void
    {
        $existing = $this->outcomes->findByNameKey(LookupKey::fromName($name));
        if (null === $existing || null !== $self && $existing->id()->equals($self)) {
            return;
        }

        throw OutcomeNameTaken::by($existing);
    }
}
