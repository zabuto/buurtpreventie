<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\SignUpForRound;

use App\Shared\Infrastructure\Mcp\ToolInput;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(
    name: 'sign_up_for_round',
    description: 'Signs the member who owns the token up as a walker of a round that has not started. A full round refuses; signing up again changes nothing.',
    annotations: new ToolAnnotations(
        title: 'Sign up for a round',
        readOnlyHint: false,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: false,
    ),
)]
final readonly class SignUpForRoundTool
{
    public function __construct(
        private SignUpForRoundHandler $handler,
        private ToolInput $input,
    ) {
    }

    /**
     * @param string $roundId the id of the round
     *
     * @return array{roundId: string, signedUp: true}
     */
    public function __invoke(string $roundId): array
    {
        ($this->handler)($this->input->validated(new SignUpForRound($roundId)));

        return ['roundId' => $roundId, 'signedUp' => true];
    }
}
