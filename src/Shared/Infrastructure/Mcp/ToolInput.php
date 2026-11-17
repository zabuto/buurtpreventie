<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mcp;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Holds the input an MCP tool builds to the same constraints as the REST layer, which validates while it maps the
 * request. A tool calls this before its handler, so a rule on the input holds over both entry points.
 */
final readonly class ToolInput
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @template T of object
     *
     * @param T $input
     *
     * @throws UnprocessableEntityHttpException with the field errors, as the REST layer throws it
     *
     * @return T
     */
    public function validated(object $input): object
    {
        $violations = $this->validator->validate($input);
        if (0 < \count($violations)) {
            throw new UnprocessableEntityHttpException(previous: new ValidationFailedException($input, $violations));
        }

        return $input;
    }
}
