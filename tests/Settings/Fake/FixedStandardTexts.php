<?php

declare(strict_types=1);

namespace App\Tests\Settings\Fake;

use App\Settings\Domain\SettingName;
use App\Settings\Domain\StandardTexts;
use Override;

/**
 * Gives "Standard <setting name>" for every text, so a test sees which setting a standard text stands in for.
 */
final readonly class FixedStandardTexts implements StandardTexts
{
    #[Override]
    public function of(SettingName $name): string
    {
        return 'Standard ' . $name->value;
    }
}
