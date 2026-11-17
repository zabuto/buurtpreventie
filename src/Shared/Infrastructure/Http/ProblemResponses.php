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
     * @param array<string, scalar> $extensions members a client reads besides the standard ones, such as attemptsLeft
     */
    public function create(int $status, string $code, array $headers = [], array $violations = [], array $extensions = []): JsonResponse
    {
        return new JsonResponse(
            $this->body($status, $code, $violations, $extensions),
            $status,
            [...$headers, 'Content-Type' => 'application/problem+json'],
        );
    }

    /**
     * The members of the problem, also for an MCP tool that reports the failure as its result.
     *
     * @param list<array{field: string, message: string}> $violations
     * @param array<string, scalar> $extensions
     *
     * @return array<string, mixed>
     */
    public function body(int $status, string $code, array $violations = [], array $extensions = []): array
    {
        $body = [
            'type' => self::TYPE_PREFIX . $code,
            'title' => $this->translator->trans($code, domain: 'problems'),
            'status' => $status,
        ];
        if ([] !== $violations) {
            $body['violations'] = $violations;
        }

        return [...$body, ...$extensions];
    }
}
