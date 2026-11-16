<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ExportOwnData;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ExportOwnDataController
{
    public function __construct(
        private ExportOwnDataHandler $handler,
    ) {
    }

    #[Route('/me/export', name: 'membership_export_own_data', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'All own data as a JSON file to save (Content-Disposition: attachment).',
        content: new OA\JsonContent(ref: new Model(type: ExportOwnDataOutput::class)),
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(
            ($this->handler)(new ExportOwnData()),
            headers: ['Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'buurtpreventie-export.json')],
        );
    }
}
