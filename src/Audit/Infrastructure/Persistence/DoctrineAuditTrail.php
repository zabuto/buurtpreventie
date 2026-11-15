<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Persistence;

use App\Audit\Domain\AuditEntry;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\MemberNames;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Clock\ClockInterface;

/**
 * Persists the entry without flushing: the running Transaction writes it together with the change it describes.
 */
final readonly class DoctrineAuditTrail implements AuditTrail
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private ExecutionContext $executionContext,
        private MemberNames $memberNames,
    ) {
    }

    #[Override]
    public function record(AuditedAction $action): void
    {
        $actorId = $this->executionContext->actor()?->memberId;

        $this->entityManager->persist(AuditEntry::of(
            $action,
            $actorId,
            null === $actorId ? null : $this->memberNames->displayNameOf($actorId),
            $this->executionContext->channel(),
            $this->clock->now(),
        ));
    }
}
