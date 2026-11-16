<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\SignUpForRound;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class SignUpForRoundController
{
    public function __construct(
        private SignUpForRoundHandler $handler,
    ) {
    }

    /**
     * A PUT, so a repeat signs up nobody twice and answers the same.
     */
    #[Route('/rounds/{roundId}/walkers/me', name: 'patrol_sign_up_for_round', requirements: ['roundId' => Requirement::UUID], methods: ['PUT'])]
    #[Route(
        '/rounds/{roundId}/walkers/{memberId}',
        name: 'patrol_sign_up_member_for_round',
        requirements: ['roundId' => Requirement::UUID, 'memberId' => Requirement::UUID],
        methods: ['PUT'],
    )]
    public function __invoke(string $roundId, ?string $memberId = null): Response
    {
        ($this->handler)(new SignUpForRound($roundId, $memberId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
