<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\BuildStep;
use PHPat\Test\PHPat;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

/**
 * PHPat rules for the module and use-case structure. Each rule names the section of docs/architecture.md that
 * explains it. Modules and use cases are read from the source root, so a new module or use case is covered as soon
 * as its folder exists.
 */
final readonly class ArchitectureRules
{
    private const string DOCS = 'See docs/architecture.md#';

    public function __construct(
        private string $sourceRoot,
    ) {
    }

    /**
     * @return iterable<string, BuildStep>
     */
    #[TestRule]
    public function domainDependsOnlyOnItsOwnDomainAndSharedDomain(): iterable
    {
        foreach ([...$this->modules(), 'Shared'] as $module) {
            yield $module => PHPat::rule()
                ->classes(Selector::inNamespace(\sprintf('App\%s\Domain', $module)))
                ->canOnly()
                ->dependOn()
                ->classes(
                    Selector::inNamespace(\sprintf('App\%s\Domain', $module)),
                    Selector::inNamespace('App\Shared\Domain'),
                    Selector::inNamespace('Doctrine\ORM\Mapping'),
                    Selector::inNamespace('Doctrine\Common\Collections'),
                    Selector::inNamespace('Symfony\Component\Uid'),
                )
                ->because(
                    'Domain code knows nothing about HTTP, MCP, storage or other modules, so domain rules can be tested'
                        . ' in isolation. '
                        . self::DOCS
                        . 'rule-1-domain',
                );
        }
    }

    #[TestRule]
    public function inputAndHandlerStayFreeOfTransportAndInfrastructure(): BuildStep
    {
        return PHPat::rule()
            ->classes(Selector::classname('/^App\\\\\w+\\\\UseCase\\\\(\w+)\\\\\1(Handler)?$/', regex: true))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Symfony\Component\HttpFoundation'),
                Selector::inNamespace('Symfony\Component\HttpKernel'),
                Selector::inNamespace('Symfony\Component\Routing'),
                Selector::inNamespace('Mcp'),
                Selector::inNamespace('Symfony\AI\McpBundle'),
                Selector::inNamespace('Doctrine\ORM'),
                Selector::inNamespace('Doctrine\DBAL'),
                Selector::classname('/^App\\\\\w+\\\\Infrastructure\\\\/', regex: true),
            )
            ->because(
                'REST and MCP call the same handler with the same input, so neither may know which transport called'
                    . ' it; storage stays behind repository interfaces and Shared\Domain\Transaction. '
                    . self::DOCS
                    . 'rule-2-input-and-handler',
            )
        ;
    }

    /**
     * @return iterable<string, BuildStep>
     */
    #[TestRule]
    public function controllerUsesOnlyItsOwnUseCaseAndHttp(): iterable
    {
        foreach ($this->useCases() as $namespace => $useCase) {
            yield $namespace => PHPat::rule()
                ->classes(Selector::classname(\sprintf('%s\%sController', $namespace, $useCase)))
                ->canOnly()
                ->dependOn()
                ->classes(
                    Selector::inNamespace($namespace),
                    Selector::inNamespace('Symfony\Component\HttpFoundation'),
                    Selector::inNamespace('Symfony\Component\HttpKernel\Attribute'),
                    Selector::inNamespace('Symfony\Component\Routing\Attribute'),
                    Selector::inNamespace('Symfony\Component\Routing\Requirement'),
                    Selector::classname(NotAcceptableHttpException::class),
                    Selector::inNamespace('OpenApi\Attributes'),
                    Selector::inNamespace('Nelmio\ApiDocBundle\Attribute'),
                )
                ->because(
                    'A controller is a thin adapter: it maps the request onto the input and calls the handler in its'
                        . ' own folder. '
                        . self::DOCS
                        . 'rule-3-controller-tool-and-output',
                );
        }
    }

    /**
     * @return iterable<string, BuildStep>
     */
    #[TestRule]
    public function toolUsesOnlyItsOwnUseCaseAndMcp(): iterable
    {
        foreach ($this->useCases() as $namespace => $useCase) {
            yield $namespace => PHPat::rule()
                ->classes(Selector::classname(\sprintf('%s\%sTool', $namespace, $useCase)))
                ->canOnly()
                ->dependOn()
                ->classes(
                    Selector::inNamespace($namespace),
                    Selector::inNamespace('Mcp'),
                    Selector::inNamespace('Symfony\AI\McpBundle'),
                )
                ->because(
                    'An MCP tool is a thin adapter: it maps the tool call onto the input and calls the handler in its'
                        . ' own folder. '
                        . self::DOCS
                        . 'rule-3-controller-tool-and-output',
                );
        }
    }

    #[TestRule]
    public function outputExposesNoDomainObjects(): BuildStep
    {
        return PHPat::rule()
            ->classes(Selector::classname('/^App\\\\\w+\\\\UseCase\\\\(\w+)\\\\\1Output$/', regex: true))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::classname('/^App\\\\\w+\\\\Domain\\\\/', regex: true))
            ->because(
                'Output is the public contract of REST and MCP; exposing entities would leak fields such as contact'
                    . ' details that need consent. '
                    . self::DOCS
                    . 'rule-3-controller-tool-and-output',
            )
        ;
    }

    /**
     * @return iterable<string, BuildStep>
     */
    #[TestRule]
    public function modulesMeetOnlyThroughUseCasesAndSharedDomain(): iterable
    {
        foreach ($this->modules() as $module) {
            yield $module => PHPat::rule()
                ->classes(Selector::inNamespace(\sprintf('App\%s', $module)))
                ->shouldNot()
                ->dependOn()
                ->classes(Selector::classname(\sprintf('/^App\\\\(?!%s\\\\|Shared\\\\)\w+\\\\/', $module), regex: true))
                ->excluding(Selector::classname('/^App\\\\\w+\\\\UseCase\\\\(\w+)\\\\\1(Handler|Output)?$/', regex: true))
                ->because(
                    'Modules meet only through use cases or interfaces in Shared\Domain, so each module can change'
                        . ' its internals freely. '
                        . self::DOCS
                        . 'rule-4-modules-and-shared',
                );
        }
    }

    #[TestRule]
    public function sharedDependsOnNoModule(): BuildStep
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Shared'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::classname('/^App\\\\(?!Shared\\\\)\w+\\\\/', regex: true))
            ->because(
                'Shared holds building blocks every module may use; depending on a module would create a cycle.'
                    . ' '
                    . self::DOCS
                    . 'rule-4-modules-and-shared',
            )
        ;
    }

    #[TestRule]
    public function twigOnlyRendersEmailsAndTheInstaller(): BuildStep
    {
        return PHPat::rule()
            ->classes(Selector::classname('/^App\\\\(?!Tests\\\\)/', regex: true))
            ->excluding(
                Selector::classname('/^App\\\\\w+\\\\Infrastructure\\\\Mail\\\\/', regex: true),
                Selector::inNamespace('App\Maintenance\Infrastructure\Installer'),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Twig'),
                Selector::inNamespace('Symfony\Bridge\Twig'),
                Selector::inNamespace('Symfony\Bundle\TwigBundle'),
            )
            ->because(
                'Screens live in frontend/ and talk to /api/v1 only; the backend renders e-mails and the installer.'
                    . ' '
                    . self::DOCS
                    . 'rule-5-twig',
            )
        ;
    }

    /**
     * @return list<string>
     */
    private function modules(): array
    {
        return \array_values(\array_filter(
            $this->folders($this->sourceRoot),
            static fn(string $folder): bool => 'Shared' !== $folder,
        ));
    }

    /**
     * @return array<non-empty-string, string> use-case namespace => use-case name
     */
    private function useCases(): array
    {
        $useCases = [];
        foreach ($this->modules() as $module) {
            foreach ($this->folders($this->sourceRoot . '/' . $module . '/UseCase') as $useCase) {
                $useCases[\sprintf('App\%s\UseCase\%s', $module, $useCase)] = $useCase;
            }
        }

        return $useCases;
    }

    /**
     * @return list<string>
     */
    private function folders(string $path): array
    {
        $entries = \is_dir($path) ? \scandir($path) : false;
        if (false === $entries) {
            return [];
        }

        return \array_values(\array_filter(
            $entries,
            static fn(string $entry): bool => !\str_starts_with($entry, '.') && \is_dir($path . '/' . $entry),
        ));
    }
}
