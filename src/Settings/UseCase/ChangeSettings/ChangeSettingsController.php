<?php

declare(strict_types=1);

namespace App\Settings\UseCase\ChangeSettings;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ChangeSettingsController
{
    public function __construct(
        private ChangeSettingsHandler $handler,
    ) {
    }

    #[Route('/admin/settings', name: 'settings_change', methods: ['PATCH'])]
    #[OA\Response(response: 204, description: 'Changed; a refused value leaves every setting as it was.')]
    public function __invoke(#[MapRequestPayload] ChangeSettings $input): Response
    {
        ($this->handler)($input);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
