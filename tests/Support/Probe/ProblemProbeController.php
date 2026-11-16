<?php

declare(strict_types=1);

namespace App\Tests\Support\Probe;

use App\Patrol\Domain\Round\RoundIsFull;
use App\Patrol\Domain\Round\StartInPast;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\ReauthenticationRequired;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Raises each kind of failure an endpoint can meet, so the problem responses are tested once, apart from any use
 * case. Only the in-memory test kernel routes to it.
 */
final readonly class ProblemProbeController
{
    public const string UNEXPECTED_DETAIL = 'connection to db-host.internal refused';

    /**
     * @return array<string, Route>
     */
    public static function routes(): array
    {
        return [
            'test_probe_fail' => new Route(
                '/api/v1/_probe/{failure}',
                ['_controller' => self::class . '::fail'],
                methods: ['GET'],
            ),
            'test_probe_validate' => new Route(
                '/api/v1/_probe',
                ['_controller' => self::class . '::validate'],
                methods: ['POST'],
            ),
        ];
    }

    public function fail(string $failure): JsonResponse
    {
        throw match ($failure) {
            'conflict' => RoundIsFull::forRound(Uuid::v7()),
            'invalid' => StartInPast::at(new DateTimeImmutable('2026-01-01T20:00:00+01:00')),
            'not-found' => NotFound::forId('round', Uuid::v7()),
            'denied' => AccessDenied::forAction('round.delete'),
            'not-recent' => ReauthenticationRequired::forAction('account.set_password'),
            'throttled' => new TooManyRequestsHttpException(60),
            default => new RuntimeException(self::UNEXPECTED_DETAIL),
        };
    }

    public function validate(#[MapRequestPayload] ProbeInput $input): JsonResponse
    {
        return new JsonResponse(['name' => $input->name]);
    }
}
