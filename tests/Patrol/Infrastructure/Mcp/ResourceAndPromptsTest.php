<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Infrastructure\Mcp;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Settings\Domain\Setting;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Patrol\Fake\InMemoryOutcomeRepository;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Support\McpTestCase;
use Mcp\Client;
use Mcp\Exception\RequestException;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Content\TextResourceContents;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class ResourceAndPromptsTest extends McpTestCase
{
    #[Test]
    public function it_gives_the_association_staffing_and_choices_without_the_contact_of_the_coordinator(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());
        self::service(InMemorySettingRepository::class)->add(Setting::of('association.name', 'Buurtpreventie Voorbeeldwijk'));
        self::service(InMemorySettingRepository::class)->add(Setting::of('contact.phone', '06 9876 5432'));
        self::service(InMemoryMeetingPointRepository::class)->add(MeetingPoint::create('Speeltuin'));
        self::service(InMemoryOutcomeRepository::class)->add(Outcome::create('Inbraak', OutcomeKind::Incident));

        $contents = self::connect($browser)->readResource('buurtpreventie://config')->contents;

        self::assertCount(1, $contents);
        self::assertInstanceOf(TextResourceContents::class, $contents[0]);
        self::assertSame('application/json', $contents[0]->mimeType);
        $config = \json_decode($contents[0]->text, associative: true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($config);
        self::assertSame(
            ['associationName', 'language', 'minimumWalkers', 'maximumWalkers', 'meetingPoints', 'outcomes'],
            \array_keys($config),
        );
        self::assertSame(['Buurtpreventie Voorbeeldwijk', 'nl', 2, 7], [
            $config['associationName'],
            $config['language'],
            $config['minimumWalkers'],
            $config['maximumWalkers'],
        ]);
        self::assertStringNotContainsString('9876', $contents[0]->text);
    }

    #[Test]
    public function it_asks_for_a_monthly_overview_of_the_whole_local_month_from_counts_only(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());

        $text = self::prompt(self::connect($browser), 'monthly_report', ['month' => '2026-10']);

        self::assertStringContainsString('from 2026-10-01T00:00:00+02:00 and to 2026-11-01T00:00:00+01:00', $text);
        self::assertStringContainsString('report_summary', $text);
        self::assertStringContainsString('Write in Dutch.', $text);
    }

    #[Test]
    public function it_refuses_a_month_in_another_format(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());

        $this->expectException(RequestException::class);

        self::connect($browser)->getPrompt('monthly_report', ['month' => 'oktober']);
    }

    #[Test]
    public function it_plans_the_week_from_the_monday_after_today_in_english_for_an_english_association(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());
        self::service(InMemorySettingRepository::class)->add(Setting::of('app.language', 'en'));

        $text = self::prompt(self::connect($browser), 'plan_week');

        self::assertStringContainsString('from 2026-11-23T00:00:00+01:00 to 2026-11-30T00:00:00+01:00', $text);
        self::assertStringContainsString('Write in English.', $text);
    }

    #[Test]
    public function it_plans_the_week_from_the_day_asked(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());

        $text = self::prompt(self::connect($browser), 'plan_week', ['weekStart' => '2027-03-25']);

        self::assertStringContainsString('from 2027-03-25T00:00:00+01:00 to 2027-04-01T00:00:00+02:00', $text);
    }

    /**
     * @param array<string, string> $arguments
     */
    private static function prompt(Client $client, string $name, array $arguments = []): string
    {
        $messages = $client->getPrompt($name, $arguments)->messages;
        self::assertCount(1, $messages);
        self::assertInstanceOf(TextContent::class, $messages[0]->content);
        self::assertIsString($messages[0]->content->text);

        return $messages[0]->content->text;
    }
}
