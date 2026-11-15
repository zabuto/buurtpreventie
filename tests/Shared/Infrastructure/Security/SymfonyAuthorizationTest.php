<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Security;

use App\Shared\Domain\AccessDenied;
use App\Shared\Infrastructure\Security\SymfonyAuthorization;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class SymfonyAuthorizationTest extends TestCase
{
    #[Test]
    public function it_hands_the_voters_the_action_and_its_subject(): void
    {
        $voter = new RecordingVoter(VoterInterface::ACCESS_GRANTED);
        $subject = new stdClass();

        $this->authorization($voter)->denyUnlessGranted('round.change', $subject);

        $this->assertSame([['round.change', $subject]], $voter->votes);
    }

    #[Test]
    public function it_refuses_an_action_the_voters_deny(): void
    {
        $voter = new RecordingVoter(VoterInterface::ACCESS_DENIED);

        $this->expectExceptionObject(AccessDenied::forAction('round.change'));

        $this->authorization($voter)->denyUnlessGranted('round.change');
    }

    private function authorization(VoterInterface $voter): SymfonyAuthorization
    {
        return new SymfonyAuthorization(new AuthorizationChecker(new TokenStorage(), new AccessDecisionManager([$voter])));
    }
}
