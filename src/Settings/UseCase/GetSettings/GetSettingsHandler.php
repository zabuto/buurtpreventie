<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetSettings;

use App\Settings\Domain\RoleList;
use App\Settings\Domain\SettingName;
use App\Settings\Domain\SettingRepository;
use App\Settings\Domain\Settings;
use App\Settings\Domain\StandardTexts;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ReadAction;

/**
 * Every setting with the value that counts, for the admin who changes them; a text the association has not written
 * shows the standard text, so the admin edits from what members see.
 */
#[ReadAction]
final readonly class GetSettingsHandler
{
    private const array TEXTS = [SettingName::WelcomeTextNl, SettingName::WelcomeTextEn, SettingName::EmergencyTextNl, SettingName::EmergencyTextEn];

    public function __construct(
        private SettingRepository $settings,
        private StandardTexts $standardTexts,
        private Authorization $authorization,
    ) {
    }

    public function __invoke(GetSettings $input): GetSettingsOutput
    {
        $this->authorization->denyUnlessGranted('settings.view');

        $settings = Settings::of($this->settings->all());
        $value = fn(SettingName $name): ?string => (
            $settings->value($name)
            ?? (\in_array($name, self::TEXTS, strict: true) ? $this->standardTexts->of($name) : null)
        );

        return new GetSettingsOutput(
            associationName: (string) $value(SettingName::AssociationName),
            associationShortName: (string) $value(SettingName::AssociationShortName),
            primaryColor: (string) $value(SettingName::PrimaryColor),
            accentColor: (string) $value(SettingName::AccentColor),
            primaryDarkColor: (string) $value(SettingName::PrimaryDarkColor),
            accentDarkColor: (string) $value(SettingName::AccentDarkColor),
            contactName: $value(SettingName::ContactName),
            contactEmail: $value(SettingName::ContactEmail),
            contactPhone: $value(SettingName::ContactPhone),
            groupUrl: $value(SettingName::GroupUrl),
            mailSenderName: $value(SettingName::MailSenderName),
            welcomeTextNl: $value(SettingName::WelcomeTextNl),
            welcomeTextEn: $value(SettingName::WelcomeTextEn),
            emergencyTextNl: $value(SettingName::EmergencyTextNl),
            emergencyTextEn: $value(SettingName::EmergencyTextEn),
            privacyUrl: $value(SettingName::PrivacyUrl),
            accessibilityUrl: $value(SettingName::AccessibilityUrl),
            minimumWalkers: (int) $value(SettingName::MinimumWalkers),
            maximumWalkers: (int) $value(SettingName::MaximumWalkers),
            reminderDaysAhead: (int) $value(SettingName::ReminderDaysAhead),
            language: (string) $value(SettingName::Language),
            apiAccess: '1' === $value(SettingName::ApiAccess),
            mcpAccess: '1' === $value(SettingName::McpAccess),
            mcpRoles: RoleList::of((string) $value(SettingName::McpRoles)),
            defaults: self::defaults($settings),
        );
    }

    /**
     * @return list<string> the fields that hold their default; an uploaded image is set by its upload only
     */
    private static function defaults(Settings $settings): array
    {
        return \array_values(\array_map(
            static fn(SettingName $name): string => $name->field(),
            \array_filter(SettingName::cases(), static fn(SettingName $name): bool => !$name->isUploaded() && $settings->isDefault($name)),
        ));
    }
}
