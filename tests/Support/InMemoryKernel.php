<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Kernel;
use App\Tests\Support\Probe\IdentityProbeController;
use App\Tests\Support\Probe\ProblemProbeController;
use Override;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RouteCollection;

/**
 * The app with every repository, the transaction, the audit trail and the notifier replaced by the in-memory fakes
 * the handler tests use, so endpoint tests run without a database (see InMemoryServices). Everything else, security
 * and voters included, is the real thing. Its container lives in a cache folder of its own, apart from the regular
 * test kernel.
 */
final class InMemoryKernel extends Kernel
{
    public const string NOW = InMemoryServices::NOW;

    #[Override]
    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment . '_in_memory';
    }

    #[Override]
    public function loadRoutes(LoaderInterface $loader): RouteCollection
    {
        $routes = parent::loadRoutes($loader);
        foreach ([...IdentityProbeController::routes(), ...ProblemProbeController::routes()] as $name => $route) {
            $routes->add($name, $route);
        }

        return $routes;
    }

    /**
     * The swap runs as a compiler pass: config/services.yaml loads after anything registered next to it and would
     * overwrite the fakes.
     */
    #[Override]
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new InMemoryServices(), PassConfig::TYPE_BEFORE_OPTIMIZATION, priority: 1000);
    }
}
