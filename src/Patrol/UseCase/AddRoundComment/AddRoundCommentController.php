<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\AddRoundComment;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class AddRoundCommentController
{
    public function __construct(
        private AddRoundCommentHandler $handler,
    ) {
    }

    /**
     * The round comes from the path: the body needs no round id, and one it carries counts for nothing.
     */
    #[Route('/rounds/{roundId}/comments', name: 'patrol_add_round_comment', requirements: ['roundId' => Requirement::UUID], methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'The id of the new comment.',
        content: new OA\JsonContent(ref: new Model(type: AddRoundCommentOutput::class)),
    )]
    public function __invoke(
        string $roundId,
        #[MapRequestPayload(serializationContext: ['default_constructor_arguments' => [AddRoundComment::class => ['roundId' => '']]])]
        AddRoundComment $input,
    ): JsonResponse {
        return new JsonResponse(($this->handler)(new AddRoundComment($roundId, $input->text)), Response::HTTP_CREATED);
    }
}
