<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mail;

use App\Shared\Domain\AppUrl;
use App\Shared\Domain\Branding;
use App\Shared\Domain\Notification;
use App\Shared\Infrastructure\Mail\ComposedMail;
use App\Shared\Infrastructure\Mail\MailComposer;
use App\Shared\Infrastructure\Mail\MailRenderer;
use Override;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The secret goes behind the `#` of the link, so it reaches no access log; the frontend reads it from there.
 */
final readonly class InvitationMail implements MailComposer
{
    public function __construct(
        private MailRenderer $renderer,
        private TranslatorInterface $translator,
        private AppUrl $appUrl,
    ) {
    }

    #[Override]
    public function type(): string
    {
        return 'account.invitation';
    }

    #[Override]
    public function compose(Notification $notification, Branding $branding): ComposedMail
    {
        return $this->renderer->render(
            $branding,
            $this->translator->trans('account.invitation.subject', ['name' => $branding->associationName()], 'mail', $branding->language()),
            'account/invitation',
            [
                'link' => $this->appUrl->to('app/#/welcome/' . ($notification->parameters['secret'] ?? '')),
                'hours' => (int) ($notification->parameters['hours'] ?? 0),
            ],
        );
    }
}
