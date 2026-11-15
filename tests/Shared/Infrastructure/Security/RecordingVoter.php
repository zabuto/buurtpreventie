<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Security;

use Override;
use SensitiveParameter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Votes one fixed way and records every attribute and subject it is asked about.
 */
final class RecordingVoter implements VoterInterface
{
    /** @var list<array{mixed, mixed}> */
    public array $votes = [];

    /**
     * @param VoterInterface::ACCESS_* $decision
     */
    public function __construct(
        private readonly int $decision,
    ) {
    }

    #[Override]
    public function vote(#[SensitiveParameter] TokenInterface $token, mixed $subject, array $attributes): int
    {
        foreach ($attributes as $attribute) {
            $this->votes[] = [$attribute, $subject];
        }

        return $this->decision;
    }
}
