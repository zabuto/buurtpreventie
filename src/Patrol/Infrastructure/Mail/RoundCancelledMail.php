<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Mail;

use App\Shared\Domain\AppUrl;
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
 * The round is deleted by the time this mail is written, so it takes the start from the message.
 */
final readonly class RoundCancelledMail implements MailComposer
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
        return 'round.cancelled';
    }

    #[Override]
    public function compose(Notification $notification, Branding $branding): ComposedMail
    {
        $locale = $branding->language();
        $startsAt = new DateTimeImmutable((string) ($notification->parameters['startsAt'] ?? ''));
        $date = LocalDateText::date($startsAt, $locale);

        return $this->renderer->render(
            $branding,
            $this->translator->trans('round.cancelled.subject', ['date' => $date], 'mail', $locale),
            'round/cancelled',
            ['date' => $date, 'time' => LocalDateText::time($startsAt, $locale), 'link' => $this->appUrl->to('app/')],
        );
    }
}
