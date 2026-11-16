<?php

declare(strict_types=1);

namespace App\Settings\Domain;

use App\Shared\Domain\InvalidValue;
use App\Shared\Domain\RuleViolationDetails;
use InvalidArgumentException;
use Override;

/**
 * A brand colour too close to the background it is painted on. The details name the setting, the ratio it has
 * (rounded down, so 4.499 never shows as 4.5) and the ratio it needs, so a screen can explain the refusal.
 */
final class InsufficientContrast extends InvalidArgumentException implements InvalidValue, RuleViolationDetails
{
    public function __construct(
        private readonly SettingName $setting,
        private readonly float $ratio,
        private readonly float $required,
    ) {
        parent::__construct(\sprintf('%s has a contrast of %.2f:1 where it needs %.1f:1.', $setting->value, $ratio, $required));
    }

    /**
     * @return array{setting: string, ratio: float, required: float}
     */
    #[Override]
    public function details(): array
    {
        return ['setting' => $this->setting->value, 'ratio' => \floor($this->ratio * 100) / 100, 'required' => $this->required];
    }
}
