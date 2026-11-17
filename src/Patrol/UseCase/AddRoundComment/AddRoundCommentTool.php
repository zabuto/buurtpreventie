<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\AddRoundComment;

use App\Shared\Infrastructure\Mcp\ToolInput;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(
    name: 'add_round_comment',
    description: 'Adds a comment to a round, by a walker of that round or a coordinator.',
    annotations: new ToolAnnotations(
        title: 'Comment on a round',
        readOnlyHint: false,
        destructiveHint: false,
        idempotentHint: false,
        openWorldHint: false,
    ),
)]
final readonly class AddRoundCommentTool
{
    public function __construct(
        private AddRoundCommentHandler $handler,
        private ToolInput $input,
    ) {
    }

    /**
     * @param string $roundId the id of the round
     * @param string $text the comment, at most 2,000 characters
     */
    public function __invoke(string $roundId, string $text): AddRoundCommentOutput
    {
        return ($this->handler)($this->input->validated(new AddRoundComment($roundId, $text)));
    }
}
