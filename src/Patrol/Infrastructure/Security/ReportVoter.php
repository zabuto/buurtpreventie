<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Security;

use App\Patrol\Domain\Round\ReportReadingPolicy;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundPolicy;
use App\Patrol\Domain\Round\RoundReport;
use App\Shared\Infrastructure\Security\TokenActor;
use Override;
use SensitiveParameter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * The one report of a walked round, and the reports of all rounds for analysis.
 */
final readonly class ReportVoter implements CacheableVoterInterface
{
    private const array ATTRIBUTES = ['report.submit', 'report.delete', 'report.list'];

    public function __construct(
        private RoundPolicy $policy,
        private ReportReadingPolicy $readingPolicy,
    ) {
    }

    #[Override]
    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, self::ATTRIBUTES, strict: true);
    }

    #[Override]
    public function supportsType(string $subjectType): bool
    {
        return true;
    }

    #[Override]
    public function vote(#[SensitiveParameter] TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        foreach ($attributes as $attribute) {
            if (\is_string($attribute) && $this->supportsAttribute($attribute)) {
                return $this->decide($attribute, $subject, $token) ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
            }
        }

        return self::ACCESS_ABSTAIN;
    }

    private function decide(string $attribute, mixed $subject, #[SensitiveParameter] TokenInterface $token): bool
    {
        $actor = TokenActor::of($token);
        if (null === $actor) {
            return false;
        }

        return match ($attribute) {
            'report.submit' => $subject instanceof Round && $this->policy->mayReport($actor, $subject),
            'report.list' => $this->readingPolicy->mayListReports($actor),
            default => $subject instanceof RoundReport && $this->policy->mayDeleteReport($actor, $subject),
        };
    }
}
