<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Mail;

use App\Patrol\Domain\Round\RoundRepository;
use App\Shared\Domain\Branding;
use App\Shared\Domain\Notification;
use App\Shared\Infrastructure\Mail\ComposedMail;
use App\Shared\Infrastructure\Mail\MailComposer;
use App\Shared\Infrastructure\Mail\MailRenderer;
use Override;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * One mail per walker per day, for every round of that day, each marked when it still needs a buddy.
 */
final readonly class ReminderMail implements MailComposer
{
    public function __construct(
        private RoundRepository $rounds,
        private RoundFacts $facts,
        private MailRenderer $renderer,
        private TranslatorInterface $translator,
    ) {
    }

    #[Override]
    public function type(): string
    {
        return 'round.reminder';
    }

    #[Override]
    public function compose(Notification $notification, Branding $branding): ComposedMail
    {
        $locale = $branding->language();
        $seekingBuddy = self::ids($notification, 'seekingBuddyRoundIds');
        $rounds = [];
        foreach (self::ids($notification, 'roundIds') as $id) {
            $rounds[] = [
                ...$this->facts->of($this->rounds->get(Uuid::fromString($id)), $notification->recipientId, $locale),
                'seekingBuddy' => \in_array($id, $seekingBuddy, strict: true),
            ];
        }

        return $this->renderer->render(
            $branding,
            $this->translator->trans('round.reminder.subject', ['date' => $rounds[0]['date'] ?? ''], 'mail', $locale),
            'round/reminder',
            ['rounds' => $rounds],
        );
    }

    /**
     * @return list<string>
     */
    private static function ids(Notification $notification, string $parameter): array
    {
        return \array_values(\array_filter(\explode(',', (string) ($notification->parameters[$parameter] ?? ''))));
    }
}
