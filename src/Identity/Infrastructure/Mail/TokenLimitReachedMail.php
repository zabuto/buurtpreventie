<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mail;

use App\Shared\Domain\Branding;
use App\Shared\Domain\Notification;
use App\Shared\Infrastructure\Mail\ComposedMail;
use App\Shared\Infrastructure\Mail\LocalDateText;
use App\Shared\Infrastructure\Mail\MailComposer;
use App\Shared\Infrastructure\Mail\MailRenderer;
use DateTimeImmutable;
use Override;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Which token reached its daily limit and when, so the member can tell whether it was them. No link: a security
 * warning with one is what a phishing mail looks like, so the member opens the app themselves to revoke the token.
 */
final readonly class TokenLimitReachedMail implements MailComposer
{
    public function __construct(
        private MailRenderer $renderer,
        private TranslatorInterface $translator,
    ) {
    }

    #[Override]
    public function type(): string
    {
        return 'account.token_limit_reached';
    }

    #[Override]
    public function compose(Notification $notification, Branding $branding): ComposedMail
    {
        $locale = $branding->language();
        $tokenName = (string) ($notification->parameters['tokenName'] ?? '');
        $reachedAt = new DateTimeImmutable((string) ($notification->parameters['reachedAt'] ?? 'now'));

        return $this->renderer->render(
            $branding,
            $this->translator->trans('account.token_limit_reached.subject', ['name' => $tokenName], 'mail', $locale),
            'account/token_limit_reached',
            [
                'tokenName' => $tokenName,
                'date' => LocalDateText::date($reachedAt, $locale),
                'time' => LocalDateText::time($reachedAt, $locale),
            ],
        );
    }
}
