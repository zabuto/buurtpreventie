<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Notification;

use App\Shared\Domain\Branding;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\Notification;
use App\Shared\Domain\Notifier;
use App\Shared\Infrastructure\Mail\MailComposers;
use LogicException;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Mails a member every message, in HTML and text, in the language and branding of the association. The address it
 * comes from is the one the mail server may send from; replies go to the contact of the association, if any. The
 * logo travels inside the mail, so no mail program fetches anything from the server.
 */
final readonly class MailNotifier implements Notifier
{
    private const string LOGO = 'logo';

    public function __construct(
        private MemberDirectory $members,
        private MailerInterface $mailer,
        private MailComposers $composers,
        private Branding $branding,
        private LoggerInterface $logger,
        #[Autowire(env: 'MAILER_FROM')]
        private string $from,
    ) {
    }

    /**
     * @throws LogicException for a type of message without a mail
     */
    #[Override]
    public function send(Notification $notification): void
    {
        $composer = $this->composers->for($notification->type);

        $email = $this->members->emailOf($notification->recipientId);
        if (null === $email) {
            $this->logger->notice('No address for notification {type} for member {recipient}', [
                'type' => $notification->type,
                'recipient' => $notification->recipientId->toRfc4122(),
            ]);

            return;
        }

        $mail = $composer->compose($notification, $this->branding);
        $message = new Email()
            ->from(new Address($this->from, $this->branding->senderName()))
            ->to(new Address($email->value))
            ->subject($mail->subject)
            ->text($mail->text)
            ->html($mail->html)
        ;

        $contact = $this->branding->contactEmail();
        if (null !== $contact) {
            $message->replyTo(new Address($contact));
        }

        $logo = $this->branding->mailLogo();
        if (null !== $logo) {
            $message->embed($logo, self::LOGO, 'image/png');
        }

        $this->mailer->send($message);
    }
}
