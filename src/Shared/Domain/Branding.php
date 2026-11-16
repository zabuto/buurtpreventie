<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * How the association presents itself in what the app sends, such as a mail.
 */
interface Branding
{
    public function associationName(): string;

    /**
     * The name a mail comes from; the address stays the one the mail server may send from.
     */
    public function senderName(): string;

    /**
     * A colour such as `#1f5f8b`, with enough contrast against white for text and buttons.
     */
    public function primaryColor(): string;

    /**
     * The primary colour of dark mode, with enough contrast against `#121212` for text and links.
     */
    public function primaryDarkColor(): string;

    /**
     * `nl` or `en`: members have no language of their own.
     */
    public function language(): string;

    /**
     * @return ?string the logo as a PNG within 512 × 128 pixels, or null without one
     */
    public function mailLogo(): ?string;

    /**
     * @return ?string the address a member reaches the coordinator at, or null without one
     */
    public function contactEmail(): ?string;
}
