<?php

declare(strict_types=1);

namespace App\Tests\Settings\Fake;

use App\Settings\Domain\Setting;
use App\Settings\Domain\SettingRepository;
use Override;

final class InMemorySettingRepository implements SettingRepository
{
    /** @var array<string, Setting> */
    private array $settings = [];

    #[Override]
    public function find(string $name): ?Setting
    {
        return $this->settings[$name] ?? null;
    }

    #[Override]
    public function all(): array
    {
        return \array_values($this->settings);
    }

    #[Override]
    public function add(Setting $setting): void
    {
        $this->settings[$setting->name()] = $setting;
    }

    #[Override]
    public function remove(Setting $setting): void
    {
        unset($this->settings[$setting->name()]);
    }
}
