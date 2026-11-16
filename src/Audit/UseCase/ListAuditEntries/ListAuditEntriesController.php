<?php

declare(strict_types=1);

namespace App\Audit\UseCase\ListAuditEntries;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ListAuditEntriesController
{
    private const string CSV = 'text/csv';

    /**
     * The media types each format answers to, as a client lists them in Accept.
     */
    private const array FORMATS = [
        'application/json' => 'json',
        'application/*' => 'json',
        '*/*' => 'json',
        self::CSV => 'csv',
        'text/*' => 'csv',
    ];

    public function __construct(
        private ListAuditEntriesHandler $handler,
    ) {
    }

    /**
     * JSON a page at a time, or with Accept: text/csv every matching entry at once as a file, streamed.
     */
    #[Route('/admin/audit', name: 'audit_list_audit_entries', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'A page of entries as JSON; with Accept: text/csv every matching entry as a CSV file.',
        content: [
            new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(ref: new Model(type: ListAuditEntriesOutput::class))),
            new OA\MediaType(mediaType: self::CSV, schema: new OA\Schema(type: 'string')),
        ],
    )]
    #[OA\Response(
        response: 406,
        description: 'Accept allows neither application/json nor text/csv (not_acceptable).',
        content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/Problem')),
    )]
    #[OA\Response(
        response: 422,
        description: 'The query is invalid (violations), or more than 10,000 entries match for a CSV file (too_many_entries, with count).',
        content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/Problem')),
    )]
    public function __invoke(
        Request $request,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListAuditEntries $input = new ListAuditEntries(),
    ): Response {
        if ('csv' !== self::format($request)) {
            return new JsonResponse(($this->handler)($input));
        }

        $output = ($this->handler)(new ListAuditEntries($input->member, $input->action, $input->from, $input->to, allEntries: true));

        return new StreamedResponse(
            static function () use ($output): void {
                $file = \fopen('php://output', 'w');
                if (false === $file) {
                    return;
                }

                $output->writeCsv($file);
                \fclose($file);
            },
            headers: [
                'Content-Type' => self::CSV . '; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="audit.csv"',
            ],
        );
    }

    /**
     * The format of the media type the client prefers; without an Accept header, JSON.
     *
     * @throws NotAcceptableHttpException for a client that accepts neither format
     */
    private static function format(Request $request): string
    {
        $accept = \trim((string) $request->headers->get('Accept'));
        if ('' === $accept) {
            return 'json';
        }

        foreach (AcceptHeader::fromString($accept)->all() as $item) {
            $format = self::FORMATS[\strtolower($item->getValue())] ?? null;
            if (null !== $format && $item->getQuality() > 0) {
                return $format;
            }
        }

        throw new NotAcceptableHttpException('The audit log comes as application/json or text/csv.');
    }
}
