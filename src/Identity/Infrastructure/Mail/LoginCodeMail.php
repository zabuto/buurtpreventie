<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mail;

use App\Shared\Domain\Branding;
use App\Shared\Domain\Notification;
use App\Shared\Infrastructure\Mail\ComposedMail;
use App\Shared\Infrastructure\Mail\MailComposer;
use App\Shared\Infrastructure\Mail\MailRenderer;
use Override;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The code large and on top, so a member copies it without reading the rest.
 */
final readonly class LoginCodeMail implements MailComposer
{
    public function __construct(
        private MailRenderer $renderer,
        private TranslatorInterface $translator,
    ) {
    }

    #[Override]
    public function type(): string
    {
        return 'account.login_code';
    }

    #[Override]
    public function compose(Notification $notification, Branding $branding): ComposedMail
    {
        return $this->renderer->render(
            $branding,
            $this->translator->trans('account.login_code.subject', [], 'mail', $branding->language()),
            'account/login_code',
            ['code' => (string) ($notification->parameters['code'] ?? ''), 'minutes' => (int) ($notification->parameters['minutes'] ?? 0)],
        );
    }
}
