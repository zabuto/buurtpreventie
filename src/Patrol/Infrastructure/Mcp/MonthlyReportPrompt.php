<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Mcp;

use App\Settings\UseCase\GetPublicConfig\GetPublicConfig;
use App\Settings\UseCase\GetPublicConfig\GetPublicConfigHandler;
use App\Shared\Domain\LocalTime;
use DateTimeImmutable;
use DateTimeZone;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Exception\PromptGetException;

/**
 * Asks the client for a monthly overview for the police and the municipality. The prompt holds no data: the client
 * fetches the counts with the tools, as the member who owns the token, so the prompt never shows more than they may
 * see.
 */
#[McpPrompt(
    name: 'monthly_report',
    title: 'Monthly overview',
    description: 'A short overview of one month of rounds and reports, for the community police officer and the municipality, from counts only.',
)]
final readonly class MonthlyReportPrompt
{
    public function __construct(
        private GetPublicConfigHandler $config,
    ) {
    }

    /**
     * @param string $month the month, as YYYY-MM
     *
     * @throws PromptGetException for a month in another format
     *
     * @return list<array{role: string, content: string}>
     */
    public function __invoke(string $month): array
    {
        $start = 1 === \preg_match('/^\d{4}-(0[1-9]|1[0-2])\z/', $month)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01', new DateTimeZone(LocalTime::ZONE))
            : false;
        if (false === $start) {
            throw new PromptGetException(\sprintf('The month "%s" is not in the format YYYY-MM.', $month));
        }

        $config = ($this->config)(new GetPublicConfig());
        $from = $start->format(\DATE_ATOM);
        $to = $start->modify('first day of next month')->format(\DATE_ATOM);

        return [[
            'role' => 'user',
            'content' => \implode("\n\n", [
                \sprintf(
                    'Write a short overview of the neighbourhood watch of %s for %s, for the community police officer and the municipality.',
                    $config->associationName,
                    $start->format('F Y'),
                ),
                \sprintf('First call report_summary and round_statistics (groupBy: month) with from %s and to %s.', $from, $to),
                'Give the rounds planned and walked, the rounds not walked for want of a buddy, the reports per outcome and the incidents, and how the month compares with the month before.',
                'Use counts only. Leave out names, display names, notes, comments and any other text members typed.',
                \sprintf('Write in %s.', LanguageName::of($config->language)),
            ]),
        ]];
    }
}
