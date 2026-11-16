<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use DateTimeImmutable;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

interface InvitationRepository
{
    /**
     * Stores the invitation when the running transaction commits.
     */
    public function add(#[SensitiveParameter] Invitation $invitation): void;

    /**
     * The invitation whose secret has this SHA-256 hash, locked for writing until the running transaction ends, so
     * the same link opened twice at once logs in only once.
     */
    public function bySecretHashForUpdate(#[SensitiveParameter] string $secretHash): ?Invitation;

    /**
     * @return list<Invitation> the invitations of the member that can still be accepted
     */
    public function openOfMember(Uuid $memberId, DateTimeImmutable $now): array;
}
