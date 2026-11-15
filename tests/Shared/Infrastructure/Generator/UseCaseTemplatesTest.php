<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Generator;

use App\Shared\Infrastructure\Generator\UseCaseTemplates;
use PHPUnit\Framework\TestCase;

final class UseCaseTemplatesTest extends TestCase
{
    public function test_it_renders_input_handler_output_controller_and_tool(): void
    {
        $templates = new UseCaseTemplates();

        $files = $templates->render('Patrol', 'SignUpForRound');

        self::assertSame(
            ['SignUpForRound.php', 'SignUpForRoundHandler.php', 'SignUpForRoundOutput.php', 'SignUpForRoundController.php', 'SignUpForRoundTool.php'],
            array_keys($files),
        );
    }

    public function test_every_file_declares_a_final_readonly_class_in_the_use_case_namespace(): void
    {
        $templates = new UseCaseTemplates();

        $files = $templates->render('Patrol', 'SignUpForRound');

        foreach ($files as $file => $code) {
            self::assertStringContainsString("namespace App\\Patrol\\UseCase\\SignUpForRound;\n", $code, $file);
            self::assertStringContainsString('final readonly class '.basename($file, '.php')."\n", $code, $file);
        }
    }

    public function test_handler_takes_the_input_and_returns_the_output(): void
    {
        $templates = new UseCaseTemplates();

        $handler = $templates->render('Patrol', 'SignUpForRound')['SignUpForRoundHandler.php'];

        self::assertStringContainsString('public function __invoke(SignUpForRound $input): SignUpForRoundOutput', $handler);
    }

    public function test_controller_starts_as_post_on_the_kebab_cased_name(): void
    {
        $templates = new UseCaseTemplates();

        $controller = $templates->render('Patrol', 'SignUpForRound')['SignUpForRoundController.php'];

        self::assertStringContainsString(
            "#[Route('/sign-up-for-round', name: 'patrol_sign_up_for_round', methods: ['POST'])]",
            $controller,
        );
    }

    public function test_route_keeps_an_acronym_together(): void
    {
        $templates = new UseCaseTemplates();

        $controller = $templates->render('Membership', 'ExportCSVFile')['ExportCSVFileController.php'];

        self::assertStringContainsString(
            "#[Route('/export-csv-file', name: 'membership_export_csv_file', methods: ['POST'])]",
            $controller,
        );
    }
}
