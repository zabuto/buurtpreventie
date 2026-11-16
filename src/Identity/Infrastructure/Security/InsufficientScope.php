<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Infrastructure\Http\NamedProblem;
use Override;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class InsufficientScope extends AccessDeniedHttpException implements NamedProblem
{
    #[Override]
    public function problemCode(): string
    {
        return 'insufficient_scope';
    }
}
