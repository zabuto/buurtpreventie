<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * When and why a started round was not walked after all. The first cancellation stands.
 */
#[ORM\Embeddable]
final class RoundCancellation
{
    #[ORM\Column(name: 'cancelled_at', type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $at = null;

    #[ORM\Column(name: 'cancel_reason', type: 'text', nullable: true)]
    private ?string $reason = null;

    /**
     * @return bool false when cancelled already
     */
    public function record(?string $reason, DateTimeImmutable $now): bool
    {
        if (null !== $this->at) {
            return false;
        }

        $reason = \trim($reason ?? '');
        $this->at = $now;
        $this->reason = '' === $reason ? null : $reason;

        return true;
    }

    public function isCancelled(): bool
    {
        return null !== $this->at;
    }

    public function at(): ?DateTimeImmutable
    {
        return $this->at;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
