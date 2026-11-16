<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\InvalidValue;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\ReauthenticationRequired;
use App\Shared\Domain\RuleViolation;
use App\Shared\Domain\RuleViolationDetails;
use App\Shared\Domain\StateConflict;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

/**
 * Turns every failure under /api/ into application/problem+json (RFC 9457). The title is translated and the body
 * never carries the exception message: that holds ids and internals, and goes to the log only. Runs after the
 * security listener, which answers missing authentication itself, and before Symfony's error page.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -32)]
final readonly class ProblemResponder
{
    private const string API_PREFIX = '/api/';

    /** @var array<int, string> */
    private const array HTTP_CODES = [
        400 => 'bad_request',
        401 => 'unauthorized',
        403 => 'access_denied',
        404 => 'not_found',
        405 => 'method_not_allowed',
        406 => 'not_acceptable',
        415 => 'unsupported_media_type',
        422 => 'invalid_input',
        429 => 'too_many_requests',
    ];

    public function __construct(
        private ProblemResponses $problems,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!\str_starts_with($event->getRequest()->getPathInfo(), self::API_PREFIX)) {
            return;
        }

        $throwable = $event->getThrowable();
        [$status, $code, $headers] = self::classify($throwable);

        $event->setResponse($this->problems->create(
            $status,
            $code,
            $headers,
            self::violations($throwable),
            $throwable instanceof RuleViolationDetails ? $throwable->details() : [],
        ));
    }

    /**
     * @return array{int, string, array<string, string>}
     */
    private static function classify(Throwable $throwable): array
    {
        return match (true) {
            $throwable instanceof StateConflict => [409, ProblemCode::of($throwable), []],
            $throwable instanceof InvalidValue => [422, ProblemCode::of($throwable), []],
            $throwable instanceof RuleViolation => [422, ProblemCode::of($throwable), []],
            $throwable instanceof NotFound => [404, 'not_found', []],
            $throwable instanceof AccessDenied => [403, 'access_denied', []],
            $throwable instanceof ReauthenticationRequired => [403, 'reauthentication_required', []],
            $throwable instanceof NamedProblem => self::fromHttp($throwable, $throwable->problemCode()),
            $throwable instanceof HttpExceptionInterface => self::fromHttp($throwable),
            default => [500, 'internal_error', []],
        };
    }

    /**
     * @return array{int, string, array<string, string>}
     */
    private static function fromHttp(HttpExceptionInterface $exception, ?string $code = null): array
    {
        $status = $exception->getStatusCode();
        if ($status >= 500) {
            return [500, 'internal_error', []];
        }

        $headers = [];
        foreach ($exception->getHeaders() as $name => $value) {
            if (\is_string($value) || \is_int($value)) {
                $headers[(string) $name] = (string) $value;
            }
        }

        return [$status, $code ?? self::HTTP_CODES[$status] ?? 'request_failed', $headers];
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private static function violations(Throwable $throwable): array
    {
        $failure = $throwable->getPrevious();
        if (!$failure instanceof ValidationFailedException) {
            return [];
        }

        $violations = [];
        foreach ($failure->getViolations() as $violation) {
            $violations[] = ['field' => $violation->getPropertyPath(), 'message' => (string) $violation->getMessage()];
        }

        return $violations;
    }
}
