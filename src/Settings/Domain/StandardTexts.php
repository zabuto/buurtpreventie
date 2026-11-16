<?php

declare(strict_types=1);

namespace App\Settings\Domain;

/**
 * The text the app shows for a text setting the association has not written itself.
 */
interface StandardTexts
{
    /**
     * @param SettingName $name a text setting, such as SettingName::WelcomeTextNl; its name ends in the language
     */
    public function of(SettingName $name): string;
}
