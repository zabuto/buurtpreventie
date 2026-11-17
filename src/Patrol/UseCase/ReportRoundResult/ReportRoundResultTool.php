<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ReportRoundResult;

use App\Shared\Infrastructure\Mcp\ToolInput;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(
    name: 'report_round_result',
    description: 'Reports the outcome of a walked round, with an optional note, by a walker of that round or a coordinator. A round has one report.',
    annotations: new ToolAnnotations(
        title: 'Report back after a round',
        readOnlyHint: false,
        destructiveHint: false,
        idempotentHint: false,
        openWorldHint: false,
    ),
)]
final readonly class ReportRoundResultTool
{
    public function __construct(
        private ReportRoundResultHandler $handler,
        private ToolInput $input,
    ) {
    }

    /**
     * @param string $roundId the id of the round
     * @param string $outcomeId the id of an outcome from list_outcomes
     * @param string|null $note what happened, at most 5,000 characters; never names or contact details of others
     */
    public function __invoke(string $roundId, string $outcomeId, ?string $note = null): ReportRoundResultOutput
    {
        return ($this->handler)($this->input->validated(new ReportRoundResult($roundId, $outcomeId, $note)));
    }
}
