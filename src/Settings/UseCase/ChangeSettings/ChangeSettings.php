<?php

declare(strict_types=1);

namespace App\Settings\UseCase\ChangeSettings;

use App\Settings\Domain\SettingName;
use App\Shared\Domain\Role;
use App\Shared\Domain\Unchanged;
use OpenApi\Attributes as OA;
use Override;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\GroupSequenceProviderInterface;

/**
 * Every setting as a JSON Merge Patch: a setting left out stays, null restores its default, a value replaces it.
 * Each field is named after its setting (SettingName::field()); the domain checks the values against each other.
 */
#[Assert\GroupSequenceProvider]
final readonly class ChangeSettings implements GroupSequenceProviderInterface
{
    private const string COLOR = '/^#[0-9a-fA-F]{6}$/D';

    public function __construct(
        #[Assert\NotBlank(allowNull: true, groups: ['associationName'])]
        #[Assert\Length(max: 100, groups: ['associationName'])]
        public string|Unchanged|null $associationName = Unchanged::Field,
        /** Shown under the icon on a home screen, which cuts off a longer name. */
        #[Assert\NotBlank(allowNull: true, groups: ['associationShortName'])]
        #[Assert\Length(max: 30, groups: ['associationShortName'])]
        public string|Unchanged|null $associationShortName = Unchanged::Field,
        /** Text, links and buttons on white: at least 4.5:1 against #ffffff. */
        #[Assert\Regex(self::COLOR, message: 'This value should be a colour such as #1f5f8b.', groups: ['primaryColor'])]
        public string|Unchanged|null $primaryColor = Unchanged::Field,
        /** Borders and focus on white: at least 3:1 against #ffffff. */
        #[Assert\Regex(self::COLOR, message: 'This value should be a colour such as #1f5f8b.', groups: ['accentColor'])]
        public string|Unchanged|null $accentColor = Unchanged::Field,
        /** Text, links and buttons in dark mode: at least 4.5:1 against #121212. */
        #[Assert\Regex(self::COLOR, message: 'This value should be a colour such as #1f5f8b.', groups: ['primaryDarkColor'])]
        public string|Unchanged|null $primaryDarkColor = Unchanged::Field,
        /** Borders and focus in dark mode: at least 3:1 against #121212. */
        #[Assert\Regex(self::COLOR, message: 'This value should be a colour such as #1f5f8b.', groups: ['accentDarkColor'])]
        public string|Unchanged|null $accentDarkColor = Unchanged::Field,
        #[Assert\NotBlank(allowNull: true, groups: ['contactName'])]
        #[Assert\Length(max: 100, groups: ['contactName'])]
        public string|Unchanged|null $contactName = Unchanged::Field,
        #[Assert\NotBlank(allowNull: true, groups: ['contactEmail'])]
        #[Assert\Email(groups: ['contactEmail'])]
        #[Assert\Length(max: 180, groups: ['contactEmail'])]
        public string|Unchanged|null $contactEmail = Unchanged::Field,
        #[Assert\NotBlank(allowNull: true, groups: ['contactPhone'])]
        #[Assert\Length(max: 30, groups: ['contactPhone'])]
        public string|Unchanged|null $contactPhone = Unchanged::Field,
        /** An invitation to a group in WhatsApp (https://chat.whatsapp.com/…) or Signal (https://signal.group/#…). */
        #[Assert\NotBlank(allowNull: true, groups: ['groupUrl'])]
        #[Assert\Length(max: 255, groups: ['groupUrl'])]
        public string|Unchanged|null $groupUrl = Unchanged::Field,
        /** The name mails come from; without it, the name of the association. */
        #[Assert\NotBlank(allowNull: true, groups: ['mailSenderName'])]
        #[Assert\Length(max: 100, groups: ['mailSenderName'])]
        public string|Unchanged|null $mailSenderName = Unchanged::Field,
        /** Plain text; an empty line starts a paragraph. Null restores the standard text. */
        #[Assert\NotBlank(allowNull: true, groups: ['welcomeTextNl'])]
        #[Assert\Length(max: 2000, groups: ['welcomeTextNl'])]
        public string|Unchanged|null $welcomeTextNl = Unchanged::Field,
        #[Assert\NotBlank(allowNull: true, groups: ['welcomeTextEn'])]
        #[Assert\Length(max: 2000, groups: ['welcomeTextEn'])]
        public string|Unchanged|null $welcomeTextEn = Unchanged::Field,
        #[Assert\NotBlank(allowNull: true, groups: ['emergencyTextNl'])]
        #[Assert\Length(max: 2000, groups: ['emergencyTextNl'])]
        public string|Unchanged|null $emergencyTextNl = Unchanged::Field,
        #[Assert\NotBlank(allowNull: true, groups: ['emergencyTextEn'])]
        #[Assert\Length(max: 2000, groups: ['emergencyTextEn'])]
        public string|Unchanged|null $emergencyTextEn = Unchanged::Field,
        #[Assert\NotBlank(allowNull: true, groups: ['privacyUrl'])]
        #[Assert\Url(protocols: ['https'], requireTld: true, groups: ['privacyUrl'])]
        #[Assert\Length(max: 255, groups: ['privacyUrl'])]
        public string|Unchanged|null $privacyUrl = Unchanged::Field,
        #[Assert\NotBlank(allowNull: true, groups: ['accessibilityUrl'])]
        #[Assert\Url(protocols: ['https'], requireTld: true, groups: ['accessibilityUrl'])]
        #[Assert\Length(max: 255, groups: ['accessibilityUrl'])]
        public string|Unchanged|null $accessibilityUrl = Unchanged::Field,
        /** For new rounds only; a round keeps the minimum it was created with. */
        #[Assert\Range(min: 1, max: 20, groups: ['minimumWalkers'])]
        public int|Unchanged|null $minimumWalkers = Unchanged::Field,
        #[Assert\Range(min: 1, max: 50, groups: ['maximumWalkers'])]
        public int|Unchanged|null $maximumWalkers = Unchanged::Field,
        #[Assert\Range(min: 1, max: 7, groups: ['reminderDaysAhead'])]
        public int|Unchanged|null $reminderDaysAhead = Unchanged::Field,
        #[Assert\Choice(choices: ['nl', 'en'], groups: ['language'])]
        public string|Unchanged|null $language = Unchanged::Field,
        /** Off refuses every API token at once, as after a data breach; tokens can still be viewed and revoked. */
        public bool|Unchanged|null $apiAccess = Unchanged::Field,
        /** Off answers every request to /mcp with 404; API tokens keep working on REST. */
        public bool|Unchanged|null $mcpAccess = Unchanged::Field,
        /**
         * The roles a member keeps on /mcp; REST and the app keep every role. Empty: only the members the admin picks
         * (mcpAllowed) use MCP.
         *
         * @var list<string>|Unchanged|null
         */
        #[OA\Property(items: new OA\Items(type: 'string', enum: ['admin', 'analyst', 'coordinator', 'walker']))]
        #[Assert\All([new Assert\Choice(callback: [Role::class, 'values'])], groups: ['mcpRoles'])]
        public array|Unchanged|null $mcpRoles = Unchanged::Field,
    ) {
    }

    /**
     * @return array<string, string|int|bool|list<string>|null> setting name => new value, or null for the default; only
     *                                                          what the patch holds
     */
    public function changes(): array
    {
        $changes = [];
        foreach ($this->fields() as $field => $value) {
            if ($value instanceof Unchanged) {
                continue;
            }

            $name = SettingName::fromField($field);
            \assert(null !== $name, 'Every field of the input is named after a setting.');
            $changes[$name->value] = $value;
        }

        return $changes;
    }

    /**
     * @return list<list<string>>
     */
    #[Override]
    #[Ignore]
    public function getGroupSequence(): array
    {
        return [[self::class, ...Unchanged::fieldsSetIn($this)]];
    }

    /**
     * @return array<string, string|int|bool|list<string>|null|Unchanged>
     */
    private function fields(): array
    {
        return [
            'associationName' => $this->associationName,
            'associationShortName' => $this->associationShortName,
            'primaryColor' => $this->primaryColor,
            'accentColor' => $this->accentColor,
            'primaryDarkColor' => $this->primaryDarkColor,
            'accentDarkColor' => $this->accentDarkColor,
            'contactName' => $this->contactName,
            'contactEmail' => $this->contactEmail,
            'contactPhone' => $this->contactPhone,
            'groupUrl' => $this->groupUrl,
            'mailSenderName' => $this->mailSenderName,
            'welcomeTextNl' => $this->welcomeTextNl,
            'welcomeTextEn' => $this->welcomeTextEn,
            'emergencyTextNl' => $this->emergencyTextNl,
            'emergencyTextEn' => $this->emergencyTextEn,
            'privacyUrl' => $this->privacyUrl,
            'accessibilityUrl' => $this->accessibilityUrl,
            'minimumWalkers' => $this->minimumWalkers,
            'maximumWalkers' => $this->maximumWalkers,
            'reminderDaysAhead' => $this->reminderDaysAhead,
            'language' => $this->language,
            'apiAccess' => $this->apiAccess,
            'mcpAccess' => $this->mcpAccess,
            'mcpRoles' => $this->mcpRoles,
        ];
    }
}
