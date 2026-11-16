<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CancelRound;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class CancelRoundController
{
    public function __construct(
        private CancelRoundHandler $handler,
    ) {
    }

    /**
     * The round comes from the path; the body, with an optional reason, may be left out.
     */
    #[Route('/rounds/{roundId}/cancellation', name: 'patrol_cancel_round', requirements: ['roundId' => Requirement::UUID], methods: ['POST'])]
    #[OA\Response(response: 204, description: 'Recorded as not walked.')]
    public function __invoke(
        string $roundId,
        #[MapRequestPayload(serializationContext: ['default_constructor_arguments' => [CancelRound::class => ['roundId' => '']]])]
        CancelRound $input = new CancelRound(''),
    ): Response {
        ($this->handler)(new CancelRound($roundId, $input->reason));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
