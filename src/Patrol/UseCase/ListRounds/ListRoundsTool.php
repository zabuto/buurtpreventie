<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListRounds;

use App\Shared\Infrastructure\Mcp\ToolInput;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(
    name: 'list_rounds',
    description: 'Rounds that start in a period of at most 93 days, earliest first, each with its status, walkers signed up, minimum and maximum, open spots, daypart and meeting point. Times are in Europe/Amsterdam.',
    annotations: new ToolAnnotations(title: 'Rounds in a period', readOnlyHint: true, openWorldHint: false),
)]
final readonly class ListRoundsTool
{
    public function __construct(
        private ListRoundsHandler $handler,
        private ToolInput $input,
    ) {
    }

    /**
     * @param string $from start of the period, ISO 8601 with offset, such as 2026-11-16T00:00:00+01:00
     * @param string $to end of the period (not included), ISO 8601 with offset
     * @param string|null $status only rounds with this status: seeking_buddy, staffed, walked, not_walked or cancelled
     */
    public function __invoke(string $from, string $to, #[Schema(type: 'string')] ?string $status = null): ListRoundsOutput
    {
        return ($this->handler)($this->input->validated(new ListRounds($from, $to, $status)));
    }
}
