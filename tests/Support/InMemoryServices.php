<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Audit\Domain\AuditEntryRepository;
use App\Identity\Domain\AccountRepository;
use App\Identity\Domain\ApiTokenRepository;
use App\Identity\Domain\InvitationRepository;
use App\Identity\Domain\LoginChallengeRepository;
use App\Identity\Domain\LoginCodeGenerator;
use App\Identity\Domain\SecretGenerator;
use App\Membership\Domain\MemberRepository;
use App\Patrol\Domain\Catalogue\MeetingPointRepository;
use App\Patrol\Domain\Catalogue\OutcomeRepository;
use App\Patrol\Domain\Round\RoundCommentRepository;
use App\Patrol\Domain\Round\RoundReportRepository;
use App\Patrol\Domain\Round\RoundRepository;
use App\Settings\Domain\BrandImages;
use App\Settings\Domain\SettingRepository;
use App\Shared\Domain\AppUrl;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Notifier;
use App\Shared\Domain\Transaction;
use App\Shared\Infrastructure\Mail\MailComposers;
use App\Shared\Infrastructure\Notification\MailNotifier;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Identity\Fake\FixedLoginCodeGenerator;
use App\Tests\Identity\Fake\FixedSecretGenerator;
use App\Tests\Identity\Fake\InMemoryAccountRepository;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Identity\Fake\InMemoryInvitationRepository;
use App\Tests\Identity\Fake\InMemoryLoginChallengeRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Patrol\Fake\InMemoryOutcomeRepository;
use App\Tests\Patrol\Fake\InMemoryRoundCommentRepository;
use App\Tests\Patrol\Fake\InMemoryRoundReportRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Settings\Fake\InMemoryBrandImages;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Shared\Fake\RecordingNotifier;
use App\Tests\Support\Probe\IdentityProbeController;
use App\Tests\Support\Probe\ProblemProbeController;
use Override;
use Psr\Clock\ClockInterface as PsrClock;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Replaces storage, transaction, audit trail, notifier and the secret generators with the fakes of the handler tests, stops the clock at
 * NOW and lets the logger keep what it gets. Login attempts are counted in memory per kernel, so the attempts of one
 * test never throttle another; the pool is a plain ArrayAdapter, because a cache pool is emptied between two requests
 * of the same client. Each fake is public, so a test arranges and checks its data through
 * the container.
 */
final readonly class InMemoryServices implements CompilerPassInterface
{
    public const string NOW = '2026-11-16T10:00:00+00:00';

    /** @var array<class-string, class-string> */
    private const array FAKES = [
        RoundRepository::class => InMemoryRoundRepository::class,
        RoundReportRepository::class => InMemoryRoundReportRepository::class,
        RoundCommentRepository::class => InMemoryRoundCommentRepository::class,
        MeetingPointRepository::class => InMemoryMeetingPointRepository::class,
        OutcomeRepository::class => InMemoryOutcomeRepository::class,
        MemberRepository::class => InMemoryMemberRepository::class,
        AccountRepository::class => InMemoryAccountRepository::class,
        ApiTokenRepository::class => InMemoryApiTokenRepository::class,
        LoginChallengeRepository::class => InMemoryLoginChallengeRepository::class,
        InvitationRepository::class => InMemoryInvitationRepository::class,
        LoginCodeGenerator::class => FixedLoginCodeGenerator::class,
        SecretGenerator::class => FixedSecretGenerator::class,
        SettingRepository::class => InMemorySettingRepository::class,
        BrandImages::class => InMemoryBrandImages::class,
        AuditEntryRepository::class => RecordingAuditEntryRepository::class,
        Transaction::class => ImmediateTransaction::class,
        AuditTrail::class => RecordingAuditTrail::class,
        Notifier::class => RecordingNotifier::class,
    ];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        foreach (self::FAKES as $interface => $fake) {
            $container->register($fake)->setAutowired(true)->setPublic(true);
            $container->setAlias($interface, $fake)->setPublic(true);
        }

        $container->register(MockClock::class)->setArguments([self::NOW])->setPublic(true);
        foreach ([ClockInterface::class, PsrClock::class, 'clock'] as $clock) {
            $container->setAlias($clock, MockClock::class)->setPublic(true);
        }

        // The notifier of the app is replaced, so its own test reaches it here, with a sender and base URL that do
        // not depend on the environment: the Symfony CLI sets DEFAULT_URI to its local domain. The base URL has a path
        // and a trailing slash, so every link proves it keeps the path and adds no slash of its own.
        $container
            ->getDefinition(MailNotifier::class)
            ->setPublic(true)
            ->setArgument('$from', 'noreply@example.org')
        ;
        $container->getDefinition(AppUrl::class)->setArguments(['https://example.org/buurtpreventie/']);
        $container->getDefinition(MailComposers::class)->setPublic(true);

        $container->setDefinition('cache.login_attempts', new Definition(ArrayAdapter::class))->setPublic(true);

        $container->register('logger', RecordingLogger::class)->setPublic(true);
        $container->setAlias(RecordingLogger::class, 'logger')->setPublic(true);

        foreach ([ProblemProbeController::class, IdentityProbeController::class] as $probe) {
            $container
                ->register($probe)
                ->setAutowired(true)
                ->setPublic(true)
                ->addTag('controller.service_arguments')
            ;
        }
    }
}
