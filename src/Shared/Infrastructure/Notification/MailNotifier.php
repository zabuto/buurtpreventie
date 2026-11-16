<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Notification;

use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\Notification;
use App\Shared\Domain\Notifier;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Mails what a member needs to log in, a login code or an invitation, as plain Dutch text from
 * translations/mail.nl.yaml; the language a member picks and the styled templates follow with the settings. Any
 * other message goes to the LoggingNotifier until it has a mail of its own.
 */
final readonly class MailNotifier implements Notifier
{
    private const string LOCALE = 'nl';
    private const array MAILED = ['account.login_code', 'account.invitation'];

    public function __construct(
        private MemberDirectory $members,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private LoggingNotifier $logOnly,
        private LoggerInterface $logger,
        #[Autowire(env: 'MAILER_FROM')]
        private string $from,
        #[Autowire(env: 'DEFAULT_URI')]
        private string $baseUri,
    ) {
    }

    #[Override]
    public function send(Notification $notification): void
    {
        if (!\in_array($notification->type, self::MAILED, strict: true)) {
            $this->logOnly->send($notification);

            return;
        }

        $email = $this->members->emailOf($notification->recipientId);
        if (null === $email) {
            $this->logger->notice('No address for notification {type} for member {recipient}', [
                'type' => $notification->type,
                'recipient' => $notification->recipientId->toRfc4122(),
            ]);

            return;
        }

        $this->mailer->send(
            new Email()
                ->from(new Address($this->from))
                ->to(new Address($email->value))
                ->subject($this->text($notification->type . '.subject', []))
                ->text($this->text($notification->type . '.body', $this->placeholders($notification))),
        );
    }

    /**
     * @return array<string, string|int>
     */
    private function placeholders(Notification $notification): array
    {
        $placeholders = [];
        foreach ($notification->parameters as $name => $value) {
            $placeholders['%' . $name . '%'] = $value;
        }

        $secret = $notification->parameters['secret'] ?? null;
        if (\is_string($secret)) {
            $placeholders['%link%'] = \rtrim($this->baseUri, '/') . '/app/#/welcome/' . $secret;
        }

        return $placeholders;
    }

    /**
     * @param array<string, string|int> $placeholders
     */
    private function text(string $key, array $placeholders): string
    {
        return $this->translator->trans($key, $placeholders, 'mail', self::LOCALE);
    }
}
