<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mail;

use App\Shared\Domain\Branding;
use App\Shared\Domain\Notification;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The mail of one type of message. Each module writes the mails of its own messages, as only it can read what the
 * ids in a message point at.
 */
#[AutoconfigureTag]
interface MailComposer
{
    /**
     * @return string such as `round.reminder`
     */
    public function type(): string;

    public function compose(Notification $notification, Branding $branding): ComposedMail;
}
