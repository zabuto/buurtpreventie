<?php

declare(strict_types=1);

namespace App\Audit\UseCase\ListAuditEntries;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ListAuditEntriesController
{
    public function __construct(
        private ListAuditEntriesHandler $handler,
    ) {
    }

    #[Route('/admin/audit', name: 'audit_list_audit_entries', methods: ['GET'])]
    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListAuditEntries $input = new ListAuditEntries(),
    ): Response {
        $output = ($this->handler)($input);
        if ('csv' !== $input->format) {
            return new JsonResponse($output);
        }

        return new Response($output->csv(), headers: [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="audit.csv"',
        ]);
    }
}
