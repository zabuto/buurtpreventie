<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Audit\Domain\AuditEntryRepository;
use App\Identity\Domain\AccountRepository;
use App\Identity\Domain\ApiTokenRepository;
use App\Kernel;
use App\Membership\Domain\MemberRepository;
use App\Patrol\Domain\Catalogue\MeetingPointRepository;
use App\Patrol\Domain\Catalogue\OutcomeRepository;
use App\Patrol\Domain\Round\RoundCommentRepository;
use App\Patrol\Domain\Round\RoundReportRepository;
use App\Patrol\Domain\Round\RoundRepository;
use App\Settings\Domain\SettingRepository;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Notifier;
use App\Shared\Domain\Transaction;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Identity\Fake\InMemoryAccountRepository;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Patrol\Fake\InMemoryOutcomeRepository;
use App\Tests\Patrol\Fake\InMemoryRoundCommentRepository;
use App\Tests\Patrol\Fake\InMemoryRoundReportRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Shared\Fake\RecordingNotifier;
use App\Tests\Support\Probe\ProblemProbeController;
use Override;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RouteCollection;

/**
 * The app with every repository, the transaction, the audit trail and the notifier replaced by the in-memory fakes
 * the handler tests use, so endpoint tests run without a database. Everything else, security and voters included,
 * is the real thing. Its container lives in a cache folder of its own, apart from the regular test kernel.
 */
final class InMemoryKernel extends Kernel
{
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
        SettingRepository::class => InMemorySettingRepository::class,
        AuditEntryRepository::class => RecordingAuditEntryRepository::class,
        Transaction::class => ImmediateTransaction::class,
        AuditTrail::class => RecordingAuditTrail::class,
        Notifier::class => RecordingNotifier::class,
    ];

    #[Override]
    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment . '_in_memory';
    }

    #[Override]
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $loader->load(static function (ContainerBuilder $container): void {
            foreach (self::FAKES as $interface => $fake) {
                $container->register($fake)->setAutowired(true)->setPublic(true);
                $container->setAlias($interface, $fake)->setPublic(true);
            }

            $container
                ->register(ProblemProbeController::class)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->setPublic(true)
                ->addTag('controller.service_arguments')
            ;
        });
    }

    #[Override]
    public function loadRoutes(LoaderInterface $loader): RouteCollection
    {
        $routes = parent::loadRoutes($loader);
        foreach (ProblemProbeController::routes() as $name => $route) {
            $routes->add($name, $route);
        }

        return $routes;
    }
}
