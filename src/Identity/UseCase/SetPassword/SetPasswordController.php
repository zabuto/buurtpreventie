<?php

declare(strict_types=1);

namespace App\Identity\UseCase\SetPassword;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class SetPasswordController
{
    public function __construct(
        private SetPasswordHandler $handler,
    ) {
    }

    /**
     * Always for the member acting: a member id in the body counts for nothing.
     */
    #[Route('/me/password', name: 'identity_set_password', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'The new password is set; every remembered device has to log in again.')]
    public function __invoke(#[MapRequestPayload] SetPassword $input): Response
    {
        ($this->handler)(new SetPassword($input->password));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
