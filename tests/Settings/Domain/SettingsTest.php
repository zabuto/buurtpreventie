<?php

declare(strict_types=1);

namespace App\Tests\Settings\Domain;

use App\Settings\Domain\InsufficientContrast;
use App\Settings\Domain\InvalidColor;
use App\Settings\Domain\InvalidGroupLink;
use App\Settings\Domain\InvalidSetting;
use App\Settings\Domain\InvalidStaffingDefaults;
use App\Settings\Domain\SettingName;
use App\Settings\Domain\Settings;
use App\Shared\Domain\InvalidEmailAddress;
use App\Shared\Domain\InvalidPhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    /**
     * Texts without a stored value fall back to the standard text in the language files, so they have no default
     * here; neither has anything an association fills in itself.
     */
    #[Test]
    public function it_gives_the_default_of_every_setting_without_a_stored_value(): void
    {
        $settings = Settings::fromStored([]);

        $values = [];
        foreach (SettingName::cases() as $name) {
            $values[$name->value] = $settings->value($name);
        }

        $this->assertSame(
            [
                'association.name' => 'Buurtpreventie',
                'association.short_name' => 'Buurtpreventie',
                'brand.primary' => '#1f5f8b',
                'brand.accent' => '#c2410c',
                'brand.primary_dark' => '#8cc4e8',
                'brand.accent_dark' => '#fb923c',
                'contact.name' => null,
                'contact.email' => null,
                'contact.phone' => null,
                'community.group_url' => null,
                'mail.sender_name' => null,
                'text.welcome.nl' => null,
                'text.welcome.en' => null,
                'text.emergency.nl' => null,
                'text.emergency.en' => null,
                'text.privacy_url' => null,
                'text.accessibility_url' => null,
                'patrol.minimum_walkers' => '2',
                'patrol.maximum_walkers' => '7',
                'patrol.reminder_days_ahead' => '1',
                'app.language' => 'nl',
                'integrations.api_access' => '1',
            ],
            $values,
        );
    }

    /**
     * @return iterable<string, array{SettingName, string|int|bool, string}>
     */
    public static function tidied(): iterable
    {
        yield 'name without outer whitespace' => [SettingName::AssociationName, "  Buurtpreventie Oost \n", 'Buurtpreventie Oost'];
        yield 'colour in lower case' => [SettingName::PrimaryColor, '#0B4F6C', '#0b4f6c'];
        yield 'e-mail address in lower case' => [SettingName::ContactEmail, 'Coordinator@Example.org', 'coordinator@example.org'];
        yield 'phone number with single spaces' => [SettingName::ContactPhone, ' 06  1234   5678 ', '06 1234 5678'];
        yield 'text with Unix line ends' => [SettingName::WelcomeTextNl, "Welkom.\r\n\r\nLoop mee. ", "Welkom.\n\nLoop mee."];
        yield 'group link' => [SettingName::GroupUrl, ' https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrStUv ', 'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrStUv'];
        yield 'web address' => [SettingName::PrivacyUrl, 'https://example.org/privacy', 'https://example.org/privacy'];
        yield 'number' => [SettingName::ReminderDaysAhead, 3, '3'];
        yield 'switch off' => [SettingName::ApiAccess, false, '0'];
        yield 'switch on' => [SettingName::ApiAccess, true, '1'];
        yield 'language' => [SettingName::Language, 'en', 'en'];
    }

    #[Test]
    #[DataProvider('tidied')]
    public function it_stores_a_changed_value_in_one_form(SettingName $name, string|int|bool $value, string $stored): void
    {
        $settings = Settings::fromStored([])->with([$name->value => $value]);

        $this->assertSame([$stored, [$name->value => $stored]], [$settings->value($name), $settings->stored()]);
    }

    /**
     * @return iterable<string, array{SettingName, string|int|bool, class-string<\Throwable>}>
     */
    public static function refused(): iterable
    {
        yield 'empty name' => [SettingName::AssociationName, '  ', InvalidSetting::class];
        yield 'empty text' => [SettingName::EmergencyTextEn, "\n", InvalidSetting::class];
        yield 'colour in another notation' => [SettingName::AccentColor, 'orange', InvalidColor::class];
        yield 'colour without enough contrast' => [SettingName::PrimaryColor, '#777777', InsufficientContrast::class];
        yield 'e-mail address without a domain' => [SettingName::ContactEmail, 'coordinator', InvalidEmailAddress::class];
        yield 'phone number with letters' => [SettingName::ContactPhone, '06-BEL-MIJ', InvalidPhoneNumber::class];
        yield 'link to another site as group' => [SettingName::GroupUrl, 'https://example.org/group', InvalidGroupLink::class];
        yield 'web address over http' => [SettingName::PrivacyUrl, 'http://example.org/privacy', InvalidSetting::class];
        yield 'web address with a user name' => [SettingName::AccessibilityUrl, 'https://user@example.org/', InvalidSetting::class];
        yield 'web address without a host' => [SettingName::PrivacyUrl, 'https:///privacy', InvalidSetting::class];
        yield 'reminder on the day itself' => [SettingName::ReminderDaysAhead, 0, InvalidSetting::class];
        yield 'reminder more than a week ahead' => [SettingName::ReminderDaysAhead, 8, InvalidSetting::class];
        yield 'language without texts' => [SettingName::Language, 'de', InvalidSetting::class];
        yield 'text for a switch' => [SettingName::ApiAccess, 'yes', InvalidSetting::class];
        yield 'text for a number' => [SettingName::MinimumWalkers, 'two', InvalidSetting::class];
        yield 'switch for a text' => [SettingName::ContactName, true, InvalidSetting::class];
        yield 'minimum above the maximum' => [SettingName::MinimumWalkers, 8, InvalidStaffingDefaults::class];
    }

    /**
     * @param class-string<\Throwable> $refusal
     */
    #[Test]
    #[DataProvider('refused')]
    public function it_refuses_a_value_that_breaks_the_rule_of_its_setting(SettingName $name, string|int|bool $value, string $refusal): void
    {
        $this->expectException($refusal);

        Settings::fromStored([])->with([$name->value => $value]);
    }

    /**
     * @return iterable<string, array{SettingName, int}>
     */
    public static function lastAllowed(): iterable
    {
        yield 'reminder the day before' => [SettingName::ReminderDaysAhead, 1];
        yield 'reminder a week ahead' => [SettingName::ReminderDaysAhead, 7];
        yield 'minimum equal to the maximum' => [SettingName::MinimumWalkers, 7];
    }

    #[Test]
    #[DataProvider('lastAllowed')]
    public function it_accepts_the_last_value_its_rule_allows(SettingName $name, int $value): void
    {
        $this->assertSame((string) $value, Settings::fromStored([])->with([$name->value => $value])->value($name));
    }

    /**
     * The pair is checked over the whole set: a maximum below a minimum stored earlier fails as much as both at once.
     */
    #[Test]
    public function it_refuses_a_maximum_below_the_minimum_already_stored(): void
    {
        $this->expectException(InvalidStaffingDefaults::class);

        Settings::fromStored(['patrol.minimum_walkers' => '4'])->with(['patrol.maximum_walkers' => 3]);
    }

    #[Test]
    public function it_checks_a_colour_against_the_other_colours_already_stored(): void
    {
        $this->expectException(InsufficientContrast::class);

        Settings::fromStored(['brand.accent_dark' => '#606060'])->with(['brand.primary' => '#0b4f6c']);
    }

    #[Test]
    public function it_goes_back_to_the_default_for_null(): void
    {
        $settings = Settings::fromStored(['association.name' => 'Buurtpreventie Oost', 'contact.name' => 'Anna'])
            ->with(['association.name' => null, 'contact.name' => null])
        ;

        $this->assertSame(
            ['Buurtpreventie', null, []],
            [$settings->value(SettingName::AssociationName), $settings->value(SettingName::ContactName), $settings->stored()],
        );
    }

    #[Test]
    public function it_ignores_a_stored_name_outside_the_catalogue(): void
    {
        $this->assertSame([], Settings::fromStored(['brand.secondary' => '#000000'])->stored());
    }

    #[Test]
    public function it_refuses_a_name_outside_the_catalogue(): void
    {
        $this->expectException(InvalidSetting::class);

        Settings::fromStored([])->with(['brand.secondary' => '#000000']);
    }

    #[Test]
    public function it_names_the_settings_whose_value_differs_from_another_set(): void
    {
        $before = Settings::fromStored(['association.name' => 'Buurtpreventie Oost', 'patrol.minimum_walkers' => '3']);

        $after = $before->with(['association.name' => 'Buurtpreventie West', 'patrol.minimum_walkers' => 3, 'app.language' => 'nl']);

        $this->assertSame([SettingName::AssociationName], $after->differencesFrom($before));
    }
}
