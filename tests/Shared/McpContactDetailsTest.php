<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Settings\Domain\Setting;
use App\Shared\Domain\Role;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Support\McpTestCase;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Content\TextResourceContents;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Every answer of the MCP server against contact details (review focus 3).
 */
#[Group('functional')]
final class McpContactDetailsTest extends McpTestCase
{
    /**
     * A coordinator who also analyses and walks sees the most; even then no tool, resource or prompt gives a phone
     * number, address or e-mail address: of a walker who consented to sharing them, of the association, or typed into
     * a note or comment.
     */
    #[Test]
    public function it_gives_no_contact_details_in_any_answer(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member(Role::Walker, Role::Coordinator, Role::Analyst));
        self::service(InMemorySettingRepository::class)->add(Setting::of('contact.phone', '06 9876 5432'));
        self::service(InMemorySettingRepository::class)->add(Setting::of('contact.email', 'coordinator@example.org'));
        $arguments = self::toolArguments();
        $client = self::connect($browser);

        $answers = ['get_round' => self::text($client->callTool('get_round', $arguments))];
        self::assertStringContainsString('Piet K.', $answers['get_round']);
        foreach ([...McpMatrixTest::MEMBER_TOOLS, ...McpMatrixTest::ANALYSIS_TOOLS, ...McpMatrixTest::WRITE_TOOLS] as $tool) {
            $answers[$tool] = self::text($client->callTool($tool, $arguments));
        }

        foreach ($client->readResource('buurtpreventie://config')->contents as $content) {
            $answers['resource'] = $content instanceof TextResourceContents ? $content->text : '';
        }

        foreach (['monthly_report' => ['month' => '2026-11'], 'plan_week' => []] as $prompt => $arguments) {
            foreach ($client->getPrompt($prompt, $arguments)->messages as $message) {
                $answers[$prompt] = $message->content instanceof TextContent && \is_string($message->content->text) ? $message->content->text : '';
            }
        }

        foreach ($answers as $source => $answer) {
            foreach (['06 1234 5678', '0612345678', '06 9876 5432', 'Dorpsstraat', '@example.org'] as $contact) {
                self::assertStringNotContainsString($contact, $answer, \sprintf('%s gives "%s".', $source, $contact));
            }
        }
    }
}
