<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds an application/problem+json answer (RFC 9457) with the translated title of its code, for the failure
 * listener and for the firewall, which answers a refused login itself.
 */
final readonly class ProblemResponses
{
    private const string TYPE_PREFIX = 'urn:buurtpreventie:problem:';

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, string> $headers
     * @param list<array{field: string, message: string}> $violations
     */
    public function create(int $status, string $code, array $headers = [], array $violations = []): JsonResponse
    {
        $body = [
            'type' => self::TYPE_PREFIX . $code,
            'title' => $this->translator->trans($code, domain: 'problems'),
            'status' => $status,
        ];
        if ([] !== $violations) {
            $body['violations'] = $violations;
        }

        return new JsonResponse($body, $status, [...$headers, 'Content-Type' => 'application/problem+json']);
    }
}
