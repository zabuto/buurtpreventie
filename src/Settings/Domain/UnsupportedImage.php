<?php

declare(strict_types=1);

namespace App\Settings\Domain;

use App\Shared\Domain\InvalidValue;
use App\Shared\Domain\RuleViolationDetails;
use InvalidArgumentException;
use Override;

/**
 * A logo or icon the app does not take. The details name the condition it breaks (`file_size`, `format`, `too_large`,
 * `not_square`, `too_small`), so a screen can say what to change.
 */
final class UnsupportedImage extends InvalidArgumentException implements InvalidValue, RuleViolationDetails
{
    private function __construct(
        private readonly string $condition,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function fileSize(): self
    {
        return new self('file_size', 'An image takes at most 2 MB.');
    }

    public static function format(): self
    {
        return new self('format', 'An image is a PNG or JPEG, by its content.');
    }

    public static function tooLarge(): self
    {
        return new self('too_large', 'An image is at most 2048 pixels wide and high.');
    }

    public static function notSquare(): self
    {
        return new self('not_square', 'An icon is square.');
    }

    public static function tooSmall(): self
    {
        return new self('too_small', 'An icon is at least 512 pixels wide and high.');
    }

    /**
     * @return array{condition: string}
     */
    #[Override]
    public function details(): array
    {
        return ['condition' => $this->condition];
    }
}
