<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Mail;

use App\Patrol\Domain\Round\RoundRepository;
use App\Shared\Domain\AppUrl;
use App\Shared\Domain\Branding;
use App\Shared\Domain\Notification;
use App\Shared\Infrastructure\Mail\ComposedMail;
use App\Shared\Infrastructure\Mail\MailComposer;
use App\Shared\Infrastructure\Mail\MailRenderer;
use Override;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class WalkerWithdrawnMail implements MailComposer
{
    public function __construct(
        private RoundRepository $rounds,
        private RoundFacts $facts,
        private MailRenderer $renderer,
        private TranslatorInterface $translator,
        private AppUrl $appUrl,
    ) {
    }

    #[Override]
    public function type(): string
    {
        return 'round.walker_withdrawn';
    }

    #[Override]
    public function compose(Notification $notification, Branding $branding): ComposedMail
    {
        $locale = $branding->language();
        $round = $this->facts->of(
            $this->rounds->get(Uuid::fromString((string) ($notification->parameters['roundId'] ?? ''))),
            $notification->recipientId,
            $locale,
        );

        return $this->renderer->render(
            $branding,
            $this->translator->trans('round.walker_withdrawn.subject', ['date' => $round['date']], 'mail', $locale),
            'round/walker_withdrawn',
            ['round' => $round, 'accountLink' => $this->appUrl->to('app/#/account')],
        );
    }
}
