<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListMyRounds;

use App\Shared\Infrastructure\Mcp\ToolInput;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(
    name: 'my_rounds',
    description: 'The rounds of the member who owns the token: upcoming ones, walked ones that still wait for a report, or all in a period. Each round says whether it has a report.',
    annotations: new ToolAnnotations(title: 'My rounds', readOnlyHint: true, openWorldHint: false),
)]
final readonly class ListMyRoundsTool
{
    public function __construct(
        private ListMyRoundsHandler $handler,
        private ToolInput $input,
    ) {
    }

    /**
     * @param string $view upcoming (earliest first), to_report (walked without a report, latest first) or all (needs from and to)
     * @param string|null $from with to, rounds that start from this moment on; ISO 8601 with offset
     * @param string|null $to rounds that start before this moment; ISO 8601 with offset
     * @param string $order asc or desc, for the view all only
     * @param int $limit at most this many rounds, 1 to 200
     */
    public function __invoke(
        string $view = 'upcoming',
        #[Schema(type: 'string')]
        ?string $from = null,
        #[Schema(type: 'string')]
        ?string $to = null,
        string $order = 'asc',
        int $limit = 50,
    ): ListMyRoundsOutput {
        return ($this->handler)($this->input->validated(new ListMyRounds($view, $from, $to, $order, $limit)));
    }
}
