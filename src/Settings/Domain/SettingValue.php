<?php

declare(strict_types=1);

namespace App\Settings\Domain;

use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\InvalidEmailAddress;
use App\Shared\Domain\InvalidPhoneNumber;
use App\Shared\Domain\PhoneNumber;

/**
 * Turns a value for one setting into the one form it is stored in, or refuses it. A setting is stored as text, so a
 * number becomes its digits and a switch `1` or `0`.
 */
final readonly class SettingValue
{
    private const array LANGUAGES = ['nl', 'en'];
    private const int MOST_DAYS_AHEAD = 7;

    /**
     * @throws InvalidSetting for a value of the wrong type or outside the rule of its setting
     * @throws InvalidColor
     * @throws InvalidEmailAddress
     * @throws InvalidPhoneNumber
     * @throws InvalidGroupLink
     */
    public static function tidy(SettingName $name, string|int|bool $value): string
    {
        return match ($name) {
            SettingName::MinimumWalkers, SettingName::MaximumWalkers => (string) self::number($name, $value),
            SettingName::ReminderDaysAhead => self::daysAhead($value),
            SettingName::ApiAccess => self::toggle($name, $value),
            default => self::text($name, \is_string($value) ? $value : throw self::wrongType($name)),
        };
    }

    /**
     * @throws InvalidSetting
     */
    private static function text(SettingName $name, string $value): string
    {
        $value = \trim(\str_replace("\r\n", "\n", $value));
        if ('' === $value) {
            throw new InvalidSetting($name->value, \sprintf('%s cannot be empty; null restores its default.', $name->value));
        }

        return match ($name) {
            SettingName::PrimaryColor, SettingName::AccentColor, SettingName::PrimaryDarkColor, SettingName::AccentDarkColor => Color::fromHex($value)->hex,
            SettingName::ContactEmail => EmailAddress::fromString($value)->value,
            SettingName::ContactPhone => PhoneNumber::fromString($value)->value,
            SettingName::GroupUrl => GroupLink::fromString($value)->url,
            SettingName::PrivacyUrl, SettingName::AccessibilityUrl => self::webAddress($name, $value),
            SettingName::Language => self::language($value),
            default => $value,
        };
    }

    /**
     * An https address with a host and no user name: a user name in front of the host is a known way to make a link
     * look like it leads somewhere else.
     *
     * @throws InvalidSetting
     */
    private static function webAddress(SettingName $name, string $value): string
    {
        $parts = \parse_url($value);
        if (
            false === \filter_var($value, \FILTER_VALIDATE_URL)
            || !\is_array($parts)
            || 'https' !== ($parts['scheme'] ?? null)
            || '' === ($parts['host'] ?? '')
            || \array_key_exists('user', $parts)
        ) {
            throw new InvalidSetting($name->value, \sprintf('%s is an https address with a host and no user name.', $name->value));
        }

        return $value;
    }

    /**
     * @throws InvalidSetting
     */
    private static function language(string $value): string
    {
        if (!\in_array($value, self::LANGUAGES, strict: true)) {
            throw new InvalidSetting(SettingName::Language->value, 'The app speaks Dutch and English only.');
        }

        return $value;
    }

    /**
     * @throws InvalidSetting
     */
    private static function number(SettingName $name, string|int|bool $value): int
    {
        return \is_int($value) ? $value : throw self::wrongType($name);
    }

    /**
     * @throws InvalidSetting
     */
    private static function daysAhead(string|int|bool $value): string
    {
        $days = self::number(SettingName::ReminderDaysAhead, $value);
        if ($days < 1 || $days > self::MOST_DAYS_AHEAD) {
            throw new InvalidSetting(SettingName::ReminderDaysAhead->value, 'A reminder goes out one to seven days ahead.');
        }

        return (string) $days;
    }

    /**
     * @throws InvalidSetting
     */
    private static function toggle(SettingName $name, string|int|bool $value): string
    {
        if (!\is_bool($value)) {
            throw self::wrongType($name);
        }

        return $value ? '1' : '0';
    }

    private static function wrongType(SettingName $name): InvalidSetting
    {
        return new InvalidSetting($name->value, \sprintf('%s does not take a value of this type.', $name->value));
    }
}
