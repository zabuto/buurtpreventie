<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\WithdrawFromRound;

use App\Shared\Infrastructure\Mcp\ToolInput;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(
    name: 'withdraw_from_round',
    description: 'Withdraws the member who owns the token from a round that has not started. When the last walker withdraws, the round lapses and is gone. When the round drops below its minimum, the walker who stays gets an e-mail. Withdrawing again changes nothing.',
    annotations: new ToolAnnotations(
        title: 'Withdraw from a round',
        readOnlyHint: false,
        destructiveHint: true,
        idempotentHint: true,
        openWorldHint: false,
    ),
)]
final readonly class WithdrawFromRoundTool
{
    public function __construct(
        private WithdrawFromRoundHandler $handler,
        private ToolInput $input,
    ) {
    }

    /**
     * @param string $roundId the id of the round
     *
     * @return array{roundId: string, withdrawn: true}
     */
    public function __invoke(string $roundId): array
    {
        ($this->handler)($this->input->validated(new WithdrawFromRound($roundId)));

        return ['roundId' => $roundId, 'withdrawn' => true];
    }
}
