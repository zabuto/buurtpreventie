<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mail;

/**
 * One mail as a member reads it: what the subject, the HTML part and the text part say. The HTML may name the logo
 * as `cid:logo`; the notifier embeds it.
 */
final readonly class ComposedMail
{
    public function __construct(
        public string $subject,
        public string $html,
        public string $text,
    ) {
    }
}
