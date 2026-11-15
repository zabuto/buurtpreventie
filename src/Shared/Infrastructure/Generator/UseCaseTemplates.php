<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Generator;

/**
 * Plain PHP templates: Twig is reserved for e-mails and the installer (docs/architecture.md#regel-5-twig).
 */
final readonly class UseCaseTemplates
{
    /**
     * @return array<string, string> file name => contents
     */
    public function render(string $module, string $name): array
    {
        $namespace = \sprintf('App\%s\UseCase\%s', $module, $name);
        $path = '/'.implode('-', $this->words($name));
        $routeName = strtolower($module).'_'.implode('_', $this->words($name));

        return [
            $name.'.php' => <<<PHP
                <?php

                declare(strict_types=1);

                namespace {$namespace};

                final readonly class {$name}
                {
                }

                PHP,
            $name.'Handler.php' => <<<PHP
                <?php

                declare(strict_types=1);

                namespace {$namespace};

                final readonly class {$name}Handler
                {
                    public function __invoke({$name} \$input): {$name}Output
                    {
                        return new {$name}Output();
                    }
                }

                PHP,
            $name.'Output.php' => <<<PHP
                <?php

                declare(strict_types=1);

                namespace {$namespace};

                final readonly class {$name}Output
                {
                }

                PHP,
            $name.'Controller.php' => <<<PHP
                <?php

                declare(strict_types=1);

                namespace {$namespace};

                use Symfony\\Component\\HttpFoundation\\JsonResponse;
                use Symfony\\Component\\HttpKernel\\Attribute\\AsController;
                use Symfony\\Component\\HttpKernel\\Attribute\\MapRequestPayload;
                use Symfony\\Component\\Routing\\Attribute\\Route;

                #[AsController]
                final readonly class {$name}Controller
                {
                    public function __construct(private {$name}Handler \$handler)
                    {
                    }

                    #[Route('{$path}', name: '{$routeName}', methods: ['POST'])]
                    public function __invoke(#[MapRequestPayload] {$name} \$input): JsonResponse
                    {
                        return new JsonResponse((\$this->handler)(\$input));
                    }
                }

                PHP,
            $name.'Tool.php' => <<<PHP
                <?php

                declare(strict_types=1);

                namespace {$namespace};

                final readonly class {$name}Tool
                {
                    public function __construct(private {$name}Handler \$handler)
                    {
                    }

                    public function __invoke({$name} \$input): {$name}Output
                    {
                        return (\$this->handler)(\$input);
                    }
                }

                PHP,
        ];
    }

    /**
     * @return list<string> lower-case words, keeping acronyms together ("ExportCSVFile" gives export, csv, file)
     */
    private function words(string $name): array
    {
        $words = preg_split('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', $name);

        return array_map(strtolower(...), false === $words ? [$name] : $words);
    }
}
