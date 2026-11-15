<?php

declare(strict_types=1);

namespace App\Settings\Domain;

interface SettingRepository
{
    public function find(string $name): ?Setting;

    /**
     * Stores the setting when the running transaction commits.
     */
    public function add(Setting $setting): void;
}
