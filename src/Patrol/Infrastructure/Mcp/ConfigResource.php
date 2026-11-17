<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Mcp;

use App\Patrol\UseCase\ListMeetingPoints\ListMeetingPoints;
use App\Patrol\UseCase\ListMeetingPoints\ListMeetingPointsHandler;
use App\Patrol\UseCase\ListOutcomes\ListOutcomes;
use App\Patrol\UseCase\ListOutcomes\ListOutcomesHandler;
use App\Settings\UseCase\GetPublicConfig\GetPublicConfig;
use App\Settings\UseCase\GetPublicConfig\GetPublicConfigHandler;
use Mcp\Capability\Attribute\McpResource;

/**
 * What a client needs to plan and report: the association, the staffing of a new round and the choices for a
 * meeting point and an outcome. The contact of the coordinator stays out, like every contact detail over MCP.
 */
#[McpResource(
    uri: 'buurtpreventie://config',
    name: 'config',
    title: 'Association and choices',
    description: 'The name of the association, the minimum and maximum walkers of a new round, and the meeting points and outcomes to choose from.',
    mimeType: 'application/json',
)]
final readonly class ConfigResource
{
    public function __construct(
        private GetPublicConfigHandler $config,
        private ListMeetingPointsHandler $meetingPoints,
        private ListOutcomesHandler $outcomes,
    ) {
    }

    /**
     * @return array{
     *     associationName: string,
     *     language: string,
     *     minimumWalkers: int,
     *     maximumWalkers: int,
     *     meetingPoints: list<array{id: string, name: string, latitude: float|null, longitude: float|null, archived: bool}>,
     *     outcomes: list<array{id: string, name: string, kind: string, archived: bool}>,
     * }
     */
    public function __invoke(): array
    {
        $config = ($this->config)(new GetPublicConfig());

        return [
            'associationName' => $config->associationName,
            'language' => $config->language,
            'minimumWalkers' => $config->minimumWalkers,
            'maximumWalkers' => $config->maximumWalkers,
            'meetingPoints' => ($this->meetingPoints)(new ListMeetingPoints())->meetingPoints,
            'outcomes' => ($this->outcomes)(new ListOutcomes())->outcomes,
        ];
    }
}
