<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\DeleteRound;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class DeleteRoundController
{
    public function __construct(
        private DeleteRoundHandler $handler,
    ) {
    }

    #[Route('/rounds/{roundId}', name: 'patrol_delete_round', requirements: ['roundId' => Requirement::UUID], methods: ['DELETE'])]
    public function __invoke(string $roundId): Response
    {
        ($this->handler)(new DeleteRound($roundId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
