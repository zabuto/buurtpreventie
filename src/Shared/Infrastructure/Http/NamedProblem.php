<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * An HTTP failure of the app's own that a client should tell apart from the general one for its status, such as a
 * token without the scope a request needs; the code needs a title in translations/problems.*.yaml.
 */
interface NamedProblem extends HttpExceptionInterface
{
    public function problemCode(): string;
}
