<?php

declare(strict_types=1);

namespace App\Settings\Domain;

/**
 * The invitation to the group of the association in WhatsApp or Signal, for the neighbourhood alerts the app leaves
 * to that group. Whoever holds the link can join, and then sees the phone number of every member, so it is shown to
 * logged-in members only.
 *
 * The whole link is matched, not parsed: a parser accepts a user name or a longer host in front of the real one,
 * such as https://chat.whatsapp.com@example.org/, which would send members elsewhere.
 */
final readonly class GroupLink
{
    private const array PATTERNS = [
        'whatsapp' => '~^https://chat\.whatsapp\.com/[A-Za-z0-9]{20,24}$~D',
        'signal' => '~^https://signal\.group/#[A-Za-z0-9_-]{20,}$~D',
    ];

    private function __construct(
        public string $url,
        public GroupService $service,
    ) {
    }

    /**
     * @throws InvalidGroupLink for anything but an https invitation to a group in WhatsApp or Signal
     */
    public static function fromString(string $url): self
    {
        $url = \trim($url);
        foreach (self::PATTERNS as $service => $pattern) {
            if (1 === \preg_match($pattern, $url)) {
                return new self($url, GroupService::from($service));
            }
        }

        throw new InvalidGroupLink('A group link is an invitation to a group in WhatsApp or Signal.');
    }
}
