<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetSettings;

/**
 * Every setting by its API name, with the value that counts: the stored one or the default. Null where the
 * association has filled in nothing and nothing stands in; a text without a value of its own shows the standard
 * text of the app.
 */
final readonly class GetSettingsOutput
{
    /**
     * @param list<string> $defaults the names of the settings that hold their default
     */
    public function __construct(
        public string $associationName,
        public string $associationShortName,
        public string $primaryColor,
        public string $accentColor,
        public string $primaryDarkColor,
        public string $accentDarkColor,
        public ?string $contactName,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?string $groupUrl,
        public ?string $mailSenderName,
        public ?string $welcomeTextNl,
        public ?string $welcomeTextEn,
        public ?string $emergencyTextNl,
        public ?string $emergencyTextEn,
        public ?string $privacyUrl,
        public ?string $accessibilityUrl,
        public int $minimumWalkers,
        public int $maximumWalkers,
        public int $reminderDaysAhead,
        public string $language,
        public bool $apiAccess,
        public array $defaults,
    ) {
    }
}
