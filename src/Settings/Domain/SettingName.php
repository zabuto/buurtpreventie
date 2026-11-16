<?php

declare(strict_types=1);

namespace App\Settings\Domain;

/**
 * Every setting an association can change, by its stored name. A name outside this list does not exist, so a new
 * setting is a new case here and needs no migration. The API names each one in camelCase after its case.
 *
 * @mago-expect lint:too-many-enum-cases - the catalogue is one list on purpose: split up, a setting could exist outside it
 */
enum SettingName: string
{
    case AssociationName = 'association.name';
    case AssociationShortName = 'association.short_name';
    case PrimaryColor = 'brand.primary';
    case AccentColor = 'brand.accent';
    case PrimaryDarkColor = 'brand.primary_dark';
    case AccentDarkColor = 'brand.accent_dark';
    case Logo = 'brand.logo';
    case Icon = 'brand.icon';
    case ContactName = 'contact.name';
    case ContactEmail = 'contact.email';
    case ContactPhone = 'contact.phone';
    case GroupUrl = 'community.group_url';
    case MailSenderName = 'mail.sender_name';
    case WelcomeTextNl = 'text.welcome.nl';
    case WelcomeTextEn = 'text.welcome.en';
    case EmergencyTextNl = 'text.emergency.nl';
    case EmergencyTextEn = 'text.emergency.en';
    case PrivacyUrl = 'text.privacy_url';
    case AccessibilityUrl = 'text.accessibility_url';
    case MinimumWalkers = 'patrol.minimum_walkers';
    case MaximumWalkers = 'patrol.maximum_walkers';
    case ReminderDaysAhead = 'patrol.reminder_days_ahead';
    case Language = 'app.language';
    case ApiAccess = 'integrations.api_access';

    /**
     * The id every audit entry about the settings has as subject: there is one set per installation.
     */
    public const string SUBJECT_ID = '00000000-0000-0000-0000-000000000000';

    public static function fromField(string $field): ?self
    {
        return \array_find(self::cases(), static fn(self $name): bool => $name->field() === $field);
    }

    public function field(): string
    {
        return \lcfirst($this->name);
    }

    /**
     * Whether an upload of its image sets it, rather than a change of the settings.
     */
    public function isUploaded(): bool
    {
        return self::Logo === $this || self::Icon === $this;
    }

    /**
     * Whether the audit log may hold the value. Texts would fill it; the contact holds the personal data of the
     * coordinator, and the group link lets anyone join the group.
     */
    public function isAuditedWithValue(): bool
    {
        return !\in_array(
            $this,
            [
                self::ContactName,
                self::ContactEmail,
                self::ContactPhone,
                self::GroupUrl,
                self::WelcomeTextNl,
                self::WelcomeTextEn,
                self::EmergencyTextNl,
                self::EmergencyTextEn,
            ],
            strict: true,
        );
    }
}
