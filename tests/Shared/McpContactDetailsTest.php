<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Settings\Domain\Setting;
use App\Shared\Domain\Role;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Support\McpFields;
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
        foreach (self::answers() as $source => $answer) {
            self::assertDoesNotMatchRegularExpression(
                '/06 1234 5678|0612345678|06 9876 5432|Dorpsstraat|@example\.org/',
                $answer,
                \sprintf('%s gives a contact detail.', $source),
            );
        }
    }

    /**
     * The values above catch only what the sample data fill in. A field for a phone number, e-mail address, address or
     * full name fails here even when it is empty, and so does a note or comment with text, so a tool added later
     * cannot pass by answering a member whose details are blank.
     */
    #[Test]
    public function it_gives_no_field_for_contact_details_or_typed_text_in_any_answer(): void
    {
        $fields = [];
        foreach (self::answers() as $source => $answer) {
            $fields = [...$fields, ...McpFields::forContactDetails($answer, $source)];
        }

        self::assertSame([], $fields);
    }

    /**
     * The answer of every tool, the resource and both prompts, for a member who sees the most.
     *
     * @return array<string, string> the text of each answer by its source
     */
    private static function answers(): array
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

        foreach (['monthly_report' => ['month' => '2026-11'], 'plan_week' => []] as $prompt => $promptArguments) {
            foreach ($client->getPrompt($prompt, $promptArguments)->messages as $message) {
                $answers[$prompt] = $message->content instanceof TextContent && \is_string($message->content->text) ? $message->content->text : '';
            }
        }

        return $answers;
    }
}
