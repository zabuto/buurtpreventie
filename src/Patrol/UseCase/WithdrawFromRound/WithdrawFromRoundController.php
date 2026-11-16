<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\WithdrawFromRound;

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
    public function __invoke(string $roundId, ?string $memberId = null): Response
    {
        ($this->handler)(new WithdrawFromRound($roundId, $memberId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
