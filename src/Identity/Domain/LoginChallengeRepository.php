<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface LoginChallengeRepository
{
    /**
     * Stores the challenge when the running transaction commits.
     */
    public function add(LoginChallenge $challenge): void;

    /**
     * Locked for writing until the running transaction ends, so two attempts at the same moment both count.
     */
    public function forUpdate(string $id): ?LoginChallenge;

    /**
     * @return list<LoginChallenge> the challenges of the member that still take a code
     */
    public function openOfMember(Uuid $memberId, DateTimeImmutable $now): array;
}
