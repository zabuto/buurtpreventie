<?php

declare(strict_types=1);

namespace App\Tests\Settings\UseCase\ChangeSettings;

use App\Settings\Domain\InsufficientContrast;
use App\Settings\Domain\Setting;
use App\Settings\Domain\SettingName;
use App\Settings\UseCase\ChangeSettings\ChangeSettings;
use App\Settings\UseCase\ChangeSettings\ChangeSettingsHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Authorization;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ChangeSettingsHandlerTest extends TestCase
{
    private InMemorySettingRepository $settings;
    private RecordingAuditTrail $auditTrail;

    #[Override]
    protected function setUp(): void
    {
        $this->settings = new InMemorySettingRepository();
        $this->auditTrail = new RecordingAuditTrail();
    }

    #[Test]
    public function it_stores_the_changed_settings_in_their_tidy_form(): void
    {
        $this->handler()(new ChangeSettings(associationName: ' Buurtpreventie Oost ', primaryColor: '#0B4F6C', reminderDaysAhead: 3, apiAccess: false));

        $this->assertSame(
            ['association.name' => 'Buurtpreventie Oost', 'brand.primary' => '#0b4f6c', 'patrol.reminder_days_ahead' => '3', 'integrations.api_access' => '0'],
            $this->stored(),
        );
    }

    #[Test]
    public function it_leaves_a_setting_the_patch_leaves_out(): void
    {
        $this->settings->add(Setting::of('association.name', 'Buurtpreventie Oost'));

        $this->handler()(new ChangeSettings(language: 'en'));

        $this->assertSame(['association.name' => 'Buurtpreventie Oost', 'app.language' => 'en'], $this->stored());
    }

    #[Test]
    public function it_removes_a_setting_set_to_null_so_its_default_counts(): void
    {
        $this->settings->add(Setting::of('association.name', 'Buurtpreventie Oost'));
        $this->settings->add(Setting::of('contact.name', 'Anna de Vries'));

        $this->handler()(new ChangeSettings(associationName: null, contactName: null));

        $this->assertSame([], $this->stored());
    }

    #[Test]
    public function it_stores_nothing_when_one_value_is_refused(): void
    {
        $refusal = null;
        try {
            $this->handler()(new ChangeSettings(associationName: 'Buurtpreventie Oost', primaryColor: '#777777'));
        } catch (InsufficientContrast $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(InsufficientContrast::class, $refusal);
        $this->assertSame([[], []], [$this->stored(), $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_records_old_and_new_value_of_each_setting_that_changed(): void
    {
        $this->settings->add(Setting::of('patrol.minimum_walkers', '3'));

        $this->handler()(new ChangeSettings(associationName: 'Buurtpreventie Oost', minimumWalkers: 2, maximumWalkers: 5));

        $this->assertEquals(
            [new AuditedAction('setting.changed', 'settings', Uuid::fromString('00000000-0000-0000-0000-000000000000'), [
                'association.name' => ['old' => 'Buurtpreventie', 'new' => 'Buurtpreventie Oost'],
                'patrol.minimum_walkers' => ['old' => '3', 'new' => '2'],
                'patrol.maximum_walkers' => ['old' => '7', 'new' => '5'],
            ])],
            $this->auditTrail->recorded,
        );
    }

    /**
     * The contact is the personal data of the coordinator, the group link lets anyone join, and texts would fill
     * the log: the entry says that they changed, not to what.
     */
    #[Test]
    public function it_records_that_a_contact_detail_text_or_group_link_changed_but_not_its_value(): void
    {
        $this->handler()(new ChangeSettings(
            contactName: 'Anna de Vries',
            contactEmail: 'anna@example.org',
            contactPhone: '06 1234 5678',
            groupUrl: 'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrStUv',
            welcomeTextNl: 'Welkom.',
            welcomeTextEn: 'Welcome.',
            emergencyTextNl: 'Bel 112.',
            emergencyTextEn: 'Call 112.',
        ));

        $hidden = ['old' => null, 'new' => null];
        $this->assertSame(
            [
                'contact.name' => $hidden,
                'contact.email' => $hidden,
                'contact.phone' => $hidden,
                'community.group_url' => $hidden,
                'text.welcome.nl' => $hidden,
                'text.welcome.en' => $hidden,
                'text.emergency.nl' => $hidden,
                'text.emergency.en' => $hidden,
            ],
            $this->auditTrail->recorded[0]->changes ?? null,
        );
    }

    #[Test]
    public function it_records_nothing_when_no_value_changes(): void
    {
        $this->settings->add(Setting::of('association.name', 'Buurtpreventie Oost'));

        $this->handler()(new ChangeSettings(associationName: 'Buurtpreventie Oost ', language: 'nl'));

        $this->assertSame([], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new ChangeSettings(associationName: 'Buurtpreventie Oost'));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([[['settings.change', null]], [], []], [$authorization->asked, $this->stored(), $this->auditTrail->recorded]);
    }

    /**
     * The input lists every setting by its API name, so none of them is out of reach of the admin.
     */
    #[Test]
    public function it_takes_every_setting_of_the_catalogue(): void
    {
        foreach (SettingName::cases() as $name) {
            $this->assertArrayHasKey($name->value, new ChangeSettings(...[$name->field() => null])->changes(), $name->field());
        }
    }

    private function handler(?Authorization $authorization = null): ChangeSettingsHandler
    {
        return new ChangeSettingsHandler(
            $this->settings,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }

    /**
     * @return array<string, string>
     */
    private function stored(): array
    {
        $stored = [];
        foreach ($this->settings->all() as $setting) {
            $stored[$setting->name()] = $setting->value();
        }

        return $stored;
    }
}
