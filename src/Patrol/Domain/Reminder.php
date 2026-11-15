<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * One message for one walker about all their rounds of one day.
 */
final readonly class Reminder
{
    /**
     * @param list<Round> $rounds
     */
    public function __construct(
        public Uuid $memberId,
        public array $rounds,
    ) {
    }
}
