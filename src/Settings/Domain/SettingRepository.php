<?php

declare(strict_types=1);

namespace App\Settings\Domain;

interface SettingRepository
{
    public function find(string $name): ?Setting;

    /**
     * @return list<Setting> every stored setting
     */
    public function all(): array;

    /**
     * Stores the setting when the running transaction commits.
     */
    public function add(Setting $setting): void;

    /**
     * Removes the setting when the running transaction commits, so it falls back to its default.
     */
    public function remove(Setting $setting): void;
}
