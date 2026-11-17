<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListMeetingPoints;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(
    name: 'list_meeting_points',
    description: 'The meeting points a new round can start from, with their id for create_round.',
    annotations: new ToolAnnotations(title: 'Meeting points', readOnlyHint: true, openWorldHint: false),
)]
final readonly class ListMeetingPointsTool
{
    public function __construct(
        private ListMeetingPointsHandler $handler,
    ) {
    }

    public function __invoke(): ListMeetingPointsOutput
    {
        return ($this->handler)(new ListMeetingPoints());
    }
}
