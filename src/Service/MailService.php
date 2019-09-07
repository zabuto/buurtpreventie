<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\MailException;
use Swift_Mailer;
use Swift_Message;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * MailService
 */
class MailService
{
    /**
     * @var string
     */
    private $siteName;

    /**
     * @var array
     */
    private $from;

    /**
     * @var Swift_Mailer
     */
    private $mailer;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @param  string          $siteName
     * @param  string          $fromName
     * @param  string          $fromEmail
     * @param  Swift_Mailer    $mailer
     * @param  RouterInterface $router
     */
    public function __construct($siteName, $fromName, $fromEmail, Swift_Mailer $mailer, RouterInterface $router)
    {
        $this->siteName = $siteName;
        $this->from = [$fromEmail => $fromName];
        $this->mailer = $mailer;
        $this->router = $router;
    }

    /**
     * @param  User $user
     * @throws MailException
     */
    public function activateNewUser(User $user)
    {
        $message = new Swift_Message();
        $message->setSubject(sprintf('Welkom bij %s', $this->siteName));
        $message->setFrom($this->from);
        $message->setTo([$user->getEmail() => $user->getName()]);

        if (null === $user->getToken()) {
            $url = $this->router->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $message->setBody(sprintf('Beste %s,\n\nWelkom als gebruiker van de actieve buurtpreventie.\n\nGa om in te loggen naar:\n%s', $user, $url));
            $message->addPart(sprintf('<p>Beste %s,</p><p>Welkom als gebruiker van de actieve buurtpreventie</p><p>Ga om in te loggen naar:<br><a href="%s">%s</a></p>', $user, $url, $url), 'text/html');
        } else {
            $url = $this->router->generate('token', ['token' => $user->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);

            $message->setBody(sprintf('Beste %s,\n\nWelkom als gebruiker van de actieve buurtpreventie.\n\nJe kunt je wachtwoord instellen via de onderstaande link:\n%s', $user, $url));
            $message->addPart(sprintf('<p>Beste %s,</p><p>Welkom als gebruiker van de actieve buurtpreventie</p><p>Je kunt je wachtwoord instellen via de onderstaande link:<br><a href="%s">%s</a></p>', $user, $url, $url), 'text/html');
        }

        $recipients = $this->mailer->send($message);
        if ($recipients === 0) {
            throw new MailException('Verzenden van activatie e-mail naar nieuwe gebruiker is mislukt.');
        }
    }

    /**
     * @param  User $user
     * @throws MailException
     */
    public function resetTokenUser(User $user)
    {
        if (null === $user->getToken()) {
            throw new MailException('E-mail voor herstellen van wachtwoord kan niet worden verstuurd. Token is onbekend.');
        }

        $url = $this->router->generate('token', ['token' => $user->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);

        $message = new Swift_Message();
        $message->setSubject(sprintf('Herstel je wachtwoord voor %s', $this->siteName));
        $message->setFrom($this->from);
        $message->setTo([$user->getEmail() => $user->getName()]);
        $message->setBody(sprintf('Beste %s,\n\nJe kunt je wachtwoord instellen via de onderstaande link:\n%s', $user, $url));
        $message->addPart(sprintf('<p>Beste %s,</p><p>Je kunt je wachtwoord instellen via de onderstaande link:<br><a href="%s">%s</a></p>', $user, $url, $url), 'text/html');

        $recipients = $this->mailer->send($message);
        if ($recipients === 0) {
            throw new MailException('Verzenden van e-mail voor herstellen van wachtwoord is mislukt.');
        }
    }
}
