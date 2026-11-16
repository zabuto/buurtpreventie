<?php

declare(strict_types=1);

namespace App\Tests\Settings\UseCase\GetSettings;

use App\Settings\Domain\Setting;
use App\Settings\UseCase\GetSettings\GetSettings;
use App\Settings\UseCase\GetSettings\GetSettingsHandler;
use App\Settings\UseCase\GetSettings\GetSettingsOutput;
use App\Shared\Domain\AccessDenied;
use App\Tests\Settings\Fake\FixedStandardTexts;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetSettingsHandlerTest extends TestCase
{
    #[Test]
    public function it_gives_every_setting_with_its_stored_value_or_default_and_names_the_defaults(): void
    {
        $settings = new InMemorySettingRepository();
        $settings->add(Setting::of('association.name', 'Buurtpreventie Oost'));
        $settings->add(Setting::of('contact.phone', '06 1234 5678'));
        $settings->add(Setting::of('text.welcome.en', 'Welcome, walkers.'));
        $settings->add(Setting::of('patrol.reminder_days_ahead', '2'));
        $settings->add(Setting::of('integrations.api_access', '0'));

        $output = new GetSettingsHandler($settings, new FixedStandardTexts(), FakeAuthorization::granting())(new GetSettings());

        $this->assertEquals(
            new GetSettingsOutput(
                associationName: 'Buurtpreventie Oost',
                associationShortName: 'Buurtpreventie',
                primaryColor: '#1f5f8b',
                accentColor: '#c2410c',
                primaryDarkColor: '#8cc4e8',
                accentDarkColor: '#fb923c',
                contactName: null,
                contactEmail: null,
                contactPhone: '06 1234 5678',
                groupUrl: null,
                mailSenderName: null,
                welcomeTextNl: 'Standard text.welcome.nl',
                welcomeTextEn: 'Welcome, walkers.',
                emergencyTextNl: 'Standard text.emergency.nl',
                emergencyTextEn: 'Standard text.emergency.en',
                privacyUrl: null,
                accessibilityUrl: null,
                minimumWalkers: 2,
                maximumWalkers: 7,
                reminderDaysAhead: 2,
                language: 'nl',
                apiAccess: false,
                defaults: [
                    'associationShortName',
                    'primaryColor',
                    'accentColor',
                    'primaryDarkColor',
                    'accentDarkColor',
                    'contactName',
                    'contactEmail',
                    'groupUrl',
                    'mailSenderName',
                    'welcomeTextNl',
                    'emergencyTextNl',
                    'emergencyTextEn',
                    'privacyUrl',
                    'accessibilityUrl',
                    'minimumWalkers',
                    'maximumWalkers',
                    'language',
                ],
            ),
            $output,
        );
    }

    #[Test]
    public function it_shows_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $this->expectException(AccessDenied::class);

        new GetSettingsHandler(new InMemorySettingRepository(), new FixedStandardTexts(), $authorization)(new GetSettings());
    }
}
