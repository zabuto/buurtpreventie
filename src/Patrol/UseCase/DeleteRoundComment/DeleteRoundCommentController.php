<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\DeleteRoundComment;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class DeleteRoundCommentController
{
    public function __construct(
        private DeleteRoundCommentHandler $handler,
    ) {
    }

    #[Route('/comments/{commentId}', name: 'patrol_delete_round_comment', requirements: ['commentId' => Requirement::UUID], methods: ['DELETE'])]
    public function __invoke(string $commentId): Response
    {
        ($this->handler)(new DeleteRoundComment($commentId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
