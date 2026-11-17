<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateRound;

use App\Shared\Infrastructure\Mcp\ToolInput;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(
    name: 'create_round',
    description: 'Plans a new round with the member who owns the token as its first walker. Minimum and maximum walkers come from the settings of the association. Members who asked for it get an e-mail that the round seeks a buddy.',
    annotations: new ToolAnnotations(
        title: 'Plan a round',
        readOnlyHint: false,
        destructiveHint: false,
        idempotentHint: false,
        openWorldHint: false,
    ),
)]
final readonly class CreateRoundTool
{
    public function __construct(
        private CreateRoundHandler $handler,
        private ToolInput $input,
    ) {
    }

    /**
     * @param string $startsAt start of the round, in the future, ISO 8601 with offset, such as 2026-11-20T21:00:00+01:00
     * @param string|null $meetingPointId the id of a meeting point from list_meeting_points
     * @param string|null $startNote where or how to meet, at most 500 characters
     * @param string|null $comment a first comment on the round, at most 2,000 characters
     */
    public function __invoke(
        string $startsAt,
        #[Schema(type: 'string')]
        ?string $meetingPointId = null,
        #[Schema(type: 'string')]
        ?string $startNote = null,
        #[Schema(type: 'string')]
        ?string $comment = null,
    ): CreateRoundOutput {
        return ($this->handler)($this->input->validated(new CreateRound(
            startsAt: $startsAt,
            meetingPointId: $meetingPointId,
            startNote: $startNote,
            comment: $comment,
        )));
    }
}
