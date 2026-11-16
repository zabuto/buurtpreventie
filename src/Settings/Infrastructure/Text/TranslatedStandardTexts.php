<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Text;

use App\Settings\Domain\SettingName;
use App\Settings\Domain\StandardTexts;
use Override;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Reads the standard texts from translations/settings.<locale>.yaml, keyed by setting name without the language,
 * in the language the setting name ends in.
 */
final readonly class TranslatedStandardTexts implements StandardTexts
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    #[Override]
    public function of(SettingName $name): string
    {
        $locale = \substr($name->value, \strrpos($name->value, '.') + 1);
        $key = \substr($name->value, 0, -\strlen($locale) - 1);

        return \rtrim($this->translator->trans($key, [], 'settings', $locale), "\n");
    }
}
