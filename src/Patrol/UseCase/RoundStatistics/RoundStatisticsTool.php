<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\RoundStatistics;

use App\Shared\Infrastructure\Mcp\ToolInput;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(
    name: 'round_statistics',
    description: 'How the rounds of a period of at most a year went, per month, weekday or daypart: planned, walked, not walked for want of a buddy, cancelled, still to come, and incidents reported, with percentages and the same counts for the period before. Never per walker. For analysts and coordinators.',
    annotations: new ToolAnnotations(title: 'Round statistics', readOnlyHint: true, openWorldHint: false),
)]
final readonly class RoundStatisticsTool
{
    public function __construct(
        private RoundStatisticsHandler $handler,
        private ToolInput $input,
    ) {
    }

    /**
     * @param string $from start of the period, ISO 8601 with offset, such as 2026-10-01T00:00:00+02:00
     * @param string $to end of the period (not included), ISO 8601 with offset, at most 366 days after from
     * @param string $groupBy month, weekday or daypart
     */
    public function __invoke(string $from, string $to, string $groupBy = 'month'): RoundStatisticsOutput
    {
        return ($this->handler)($this->input->validated(new RoundStatistics($from, $to, $groupBy)));
    }
}
