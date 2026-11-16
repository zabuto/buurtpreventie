<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\WithdrawFromRound;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class WithdrawFromRoundController
{
    public function __construct(
        private WithdrawFromRoundHandler $handler,
    ) {
    }

    /**
     * Withdrawing someone who is not signed up answers the same, so a repeat does no harm.
     */
    #[Route('/rounds/{roundId}/walkers/me', name: 'patrol_withdraw_from_round', requirements: ['roundId' => Requirement::UUID], methods: ['DELETE'])]
    #[Route(
        '/rounds/{roundId}/walkers/{memberId}',
        name: 'patrol_withdraw_member_from_round',
        requirements: ['roundId' => Requirement::UUID, 'memberId' => Requirement::UUID],
        methods: ['DELETE'],
    )]
    #[OA\Response(response: 204, description: 'Withdrawn, also when the member was not signed up.')]
    #[OA\Response(
        response: 404,
        description: 'No round under this id. A repeated withdrawal gets this when the last walker left: the round went with them.',
        content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/Problem')),
    )]
    public function __invoke(string $roundId, ?string $memberId = null): Response
    {
        ($this->handler)(new WithdrawFromRound($roundId, $memberId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
