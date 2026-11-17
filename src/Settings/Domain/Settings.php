<?php

declare(strict_types=1);

namespace App\Settings\Domain;

/**
 * The settings of the association as one set: stored values in one tidy form, defaults for the rest. A change is
 * checked against the whole set, as a minimum must fit the maximum already stored, and each colour the background it
 * is painted on.
 */
final readonly class Settings
{
    /**
     * @param array<string, string> $stored setting name => value, only for settings in the catalogue
     */
    private function __construct(
        private array $stored,
    ) {
    }

    /**
     * @param list<Setting> $rows the stored settings
     */
    public static function of(array $rows): self
    {
        $stored = [];
        foreach ($rows as $row) {
            $stored[$row->name()] = $row->value();
        }

        return self::fromStored($stored);
    }

    /**
     * A stored name outside the catalogue, left by a setting that no longer exists, counts for nothing.
     *
     * @param array<string, string> $stored setting name => value
     */
    public static function fromStored(array $stored): self
    {
        return new self(\array_filter($stored, static fn(string $name): bool => null !== SettingName::tryFrom($name), \ARRAY_FILTER_USE_KEY));
    }

    /**
     * The stored value, or the default; null where the association has not filled it in and nothing stands in.
     */
    public function value(SettingName $name): ?string
    {
        return $this->stored[$name->value] ?? self::default($name);
    }

    public function isDefault(SettingName $name): bool
    {
        return !\array_key_exists($name->value, $this->stored);
    }

    /**
     * @return array<string, string> setting name => value, for the settings that hold one of their own
     */
    public function stored(): array
    {
        return $this->stored;
    }

    /**
     * @param array<string, string|int|bool|null> $changes setting name => new value, or null for the default
     *
     * @throws InvalidSetting for a name outside the catalogue or a value its setting does not take
     * @throws InvalidStaffingDefaults when the minimum walkers exceed the maximum
     * @throws InsufficientContrast for a colour too close to its background
     */
    public function with(array $changes): self
    {
        $stored = $this->stored;
        foreach ($changes as $setting => $value) {
            $name = SettingName::tryFrom($setting) ?? throw new InvalidSetting($setting, \sprintf('There is no setting "%s".', $setting));
            if (null === $value) {
                unset($stored[$name->value]);

                continue;
            }

            $stored[$name->value] = SettingValue::tidy($name, $value);
        }

        $settings = new self($stored);
        $settings->staffing();
        $settings->palette();

        return $settings;
    }

    /**
     * @return list<SettingName> the settings whose value, stored or default, differs from the other set
     */
    public function differencesFrom(self $other): array
    {
        return \array_values(\array_filter(SettingName::cases(), fn(SettingName $name): bool => $this->value($name) !== $other->value($name)));
    }

    /**
     * @throws InvalidStaffingDefaults
     */
    public function staffing(): StaffingDefaults
    {
        return new StaffingDefaults((int) $this->value(SettingName::MinimumWalkers), (int) $this->value(SettingName::MaximumWalkers));
    }

    /**
     * @throws InsufficientContrast
     */
    public function palette(): BrandPalette
    {
        return new BrandPalette(
            Color::fromHex((string) $this->value(SettingName::PrimaryColor)),
            Color::fromHex((string) $this->value(SettingName::AccentColor)),
            Color::fromHex((string) $this->value(SettingName::PrimaryDarkColor)),
            Color::fromHex((string) $this->value(SettingName::AccentDarkColor)),
        );
    }

    private static function default(SettingName $name): ?string
    {
        $palette = BrandPalette::default();

        return match ($name) {
            SettingName::AssociationName, SettingName::AssociationShortName => 'Buurtpreventie',
            SettingName::PrimaryColor => $palette->primary->hex,
            SettingName::AccentColor => $palette->accent->hex,
            SettingName::PrimaryDarkColor => $palette->primaryDark->hex,
            SettingName::AccentDarkColor => $palette->accentDark->hex,
            SettingName::MinimumWalkers => (string) StaffingDefaults::MINIMUM,
            SettingName::MaximumWalkers => (string) StaffingDefaults::MAXIMUM,
            SettingName::ReminderDaysAhead, SettingName::ApiAccess => '1',
            SettingName::Language => 'nl',
            SettingName::McpAccess => '0',
            default => null,
        };
    }
}
