<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListOutcomes;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(
    name: 'list_outcomes',
    description: 'The outcomes a report after a round can have, with their id for report_round_result and their kind: nothing_to_report, remarks or incident.',
    annotations: new ToolAnnotations(title: 'Outcomes', readOnlyHint: true, openWorldHint: false),
)]
final readonly class ListOutcomesTool
{
    public function __construct(
        private ListOutcomesHandler $handler,
    ) {
    }

    public function __invoke(): ListOutcomesOutput
    {
        return ($this->handler)(new ListOutcomes());
    }
}
