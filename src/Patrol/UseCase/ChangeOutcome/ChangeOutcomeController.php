<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ChangeOutcome;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class ChangeOutcomeController
{
    public function __construct(
        private ChangeOutcomeHandler $handler,
    ) {
    }

    /**
     * The outcome comes from the path: the body needs no id, and one it carries counts for nothing.
     */
    #[Route('/admin/outcomes/{outcomeId}', name: 'patrol_change_outcome', requirements: ['outcomeId' => Requirement::UUID], methods: ['PATCH'])]
    public function __invoke(
        string $outcomeId,
        #[MapRequestPayload(serializationContext: ['default_constructor_arguments' => [ChangeOutcome::class => ['outcomeId' => '']]])]
        ChangeOutcome $input,
    ): Response {
        ($this->handler)(new ChangeOutcome($outcomeId, $input->name));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
