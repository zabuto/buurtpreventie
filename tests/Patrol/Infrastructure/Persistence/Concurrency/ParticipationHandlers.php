<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Infrastructure\Persistence\Concurrency;

use App\Audit\Infrastructure\Persistence\DoctrineAuditTrail;
use App\Membership\Infrastructure\Persistence\DoctrineMemberRepository;
use App\Membership\Infrastructure\RepositoryMemberDirectory;
use App\Membership\Infrastructure\RepositoryMemberNames;
use App\Patrol\Infrastructure\Persistence\DoctrineRoundRepository;
use App\Patrol\UseCase\SignUpForRound\SignUpForRoundHandler;
use App\Patrol\UseCase\WithdrawFromRound\WithdrawFromRoundHandler;
use App\Shared\Infrastructure\Doctrine\DoctrineTransaction;
use App\Shared\Infrastructure\Notification\LoggingNotifier;
use App\Shared\Infrastructure\Security\RunAs;
use App\Shared\Infrastructure\Security\SymfonyAuthorization;
use App\Shared\Infrastructure\Security\TokenExecutionContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The sign-up and withdrawal handlers on the real database, transaction, voters and clock, for the test and for the
 * process it races. The container drops handlers nothing uses yet, so they are put together here.
 */
final readonly class ParticipationHandlers
{
    private function __construct(
        public SignUpForRoundHandler $signUp,
        public WithdrawFromRoundHandler $withdraw,
        public RunAs $runAs,
    ) {
    }

    public static function on(ManagerRegistry $registry, TokenStorageInterface $tokenStorage, AuthorizationCheckerInterface $checker): self
    {
        $entityManager = $registry->getManager();
        \assert($entityManager instanceof EntityManagerInterface, 'The default manager of the ORM is an entity manager.');

        $clock = new NativeClock();
        $executionContext = new TokenExecutionContext($tokenStorage);
        $authorization = new SymfonyAuthorization($checker);
        $members = new DoctrineMemberRepository($entityManager);
        $rounds = new DoctrineRoundRepository($entityManager);
        $auditTrail = new DoctrineAuditTrail($entityManager, $clock, $executionContext, new RepositoryMemberNames($members));
        $transaction = new DoctrineTransaction($registry);

        return new self(
            new SignUpForRoundHandler($rounds, $authorization, $auditTrail, $transaction, $clock, $executionContext, new RepositoryMemberDirectory($members)),
            new WithdrawFromRoundHandler($rounds, $authorization, $auditTrail, new LoggingNotifier(new NullLogger()), $transaction, $clock, $executionContext),
            new RunAs($tokenStorage),
        );
    }
}
