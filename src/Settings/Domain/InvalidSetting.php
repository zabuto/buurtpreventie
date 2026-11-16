<?php

declare(strict_types=1);

namespace App\Settings\Domain;

use App\Shared\Domain\InvalidValue;
use App\Shared\Domain\RuleViolationDetails;
use InvalidArgumentException;
use Override;

/**
 * A value outside what its setting takes, or a setting that does not exist; the details name the setting.
 */
final class InvalidSetting extends InvalidArgumentException implements InvalidValue, RuleViolationDetails
{
    public function __construct(
        private readonly string $setting,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * @return array{setting: string}
     */
    #[Override]
    public function details(): array
    {
        return ['setting' => $this->setting];
    }
}
