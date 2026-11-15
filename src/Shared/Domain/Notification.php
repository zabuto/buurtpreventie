<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * A message for one member. The type and parameters say what to tell; the notifier decides how and in which
 * language, so the domain holds no message texts.
 */
final readonly class Notification
{
    /**
     * @param string $type such as round.reminder
     * @param array<string, string|int> $parameters
     */
    public function __construct(
        public Uuid $recipientId,
        public string $type,
        public array $parameters = [],
    ) {
    }
}
