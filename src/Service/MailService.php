<?php declare(strict_types=1);

namespace App\Service;

use App\Dto\UserDto;
use App\Exception\MailException;
use App\Model\WalkerSingleDayModel;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

readonly class MailService
{
    public function __construct(
        private MailerInterface $mailer,
        private RouterInterface $router,
        #[Autowire(env: 'APPLICATION_NAME')]
        private string          $name,
    )
    {
    }

    /**
     * @throws MailException
     */
    public function welcomeNewUser(UserDto $dto): void
    {
        try {
            $email = new TemplatedEmail();
            $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
            $email->to(new Address($dto->email, $dto->name));
            $email->subject(sprintf('Welkom bij %s', $this->name));
            $email->htmlTemplate('emails/welcome.html.twig');
            $email->textTemplate('emails/welcome.txt.twig');
            $email->context([
                'user_name' => $dto->name,
                'app_name' => $this->name,
                'app_url' => $this->router->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'token_valid' => $dto->valid_until,
                'token_url' => (null !== $dto->token) ? $this->router->generate('token', ['token' => $dto->token], UrlGeneratorInterface::ABSOLUTE_URL) : null,
            ]);

            $this->mailer->send($email);
        } catch (Throwable $e) {
            throw new MailException('Verzenden van activatie e-mail naar nieuwe gebruiker is mislukt.', 0, $e);
        }
    }

    /**
     * @throws MailException
     */
    public function resetTokenUser(UserDto $dto): void
    {
        if (null === $dto->token) {
            throw new MailException('E-mail voor herstellen van wachtwoord kan niet worden verstuurd. Token is onbekend.');
        }

        try {
            $email = new TemplatedEmail();
            $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
            $email->to(new Address($dto->email, $dto->name));
            $email->subject(sprintf('Herstel je wachtwoord voor %s', $this->name));
            $email->htmlTemplate('emails/reset.html.twig');
            $email->textTemplate('emails/reset.txt.twig');
            $email->context([
                'user_name' => $dto->name,
                'app_name' => $this->name,
                'token_valid' => $dto->valid_until,
                'token_url' => $this->router->generate('token', ['token' => $dto->token], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

            $this->mailer->send($email);
        } catch (Throwable $e) {
            throw new MailException('Verzenden van e-mail voor herstellen van wachtwoord is mislukt.', 0, $e);
        }
    }

    /**
     * @throws MailException
     */
    public function sendReminder(WalkerSingleDayModel $model): void
    {
        try {
            $email = new TemplatedEmail();
            $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
            $email->to(new Address($model->walker->email, $model->walker->name));
            $email->subject(sprintf('Herinnering deelname loopronde %s', $model->datetime->format('d-m-Y')));
            $email->htmlTemplate('emails/reminder.html.twig');
            $email->textTemplate('emails/reminder.txt.twig');
            $email->context(
                $model->toArray() + [
                    'app_name' => $this->name,
                    'app_url' => $this->router->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL),
                ]
            );

            $this->mailer->send($email);
        } catch (Throwable $e) {
            throw new MailException('Verzenden van e-mail voor herinnering deelname loopronde is mislukt.', 0, $e);
        }
    }
}
