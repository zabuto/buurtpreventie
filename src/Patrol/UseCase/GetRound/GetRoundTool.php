<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\GetRound;

use App\Shared\Infrastructure\Mcp\ToolInput;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(
    name: 'get_round',
    description: 'One round: start, status, staffing, meeting point, the walkers by display name, its report and its comments. The note of a report and the comments show only for its walkers, coordinators and analysts.',
    annotations: new ToolAnnotations(title: 'One round', readOnlyHint: true, openWorldHint: false),
)]
final readonly class GetRoundTool
{
    public function __construct(
        private GetRoundHandler $handler,
        private ToolInput $input,
    ) {
    }

    /**
     * The walkers lose their full name and phone number: an AI client gets no contact details, not even with consent.
     *
     * @param string $roundId the id of the round
     *
     * @return array<string, mixed>
     */
    public function __invoke(string $roundId): array
    {
        $round = ($this->handler)($this->input->validated(new GetRound($roundId)));

        return [
            'id' => $round->id,
            'startsAt' => $round->startsAt,
            'daypart' => $round->daypart,
            'status' => $round->status,
            'walkerCount' => $round->walkerCount,
            'minimumWalkers' => $round->minimumWalkers,
            'maximumWalkers' => $round->maximumWalkers,
            'openSpots' => $round->openSpots,
            'meetingPoint' => $round->meetingPoint,
            'startNote' => $round->startNote,
            'cancelReason' => $round->cancelReason,
            'ownerId' => $round->ownerId,
            'walkers' => \array_map(
                static fn(array $walker): array => ['id' => $walker['id'], 'displayName' => $walker['displayName']],
                $round->walkers,
            ),
            'inactiveWalkerCount' => $round->inactiveWalkerCount,
            'report' => $round->report,
            'comments' => $round->comments,
        ];
    }
}
