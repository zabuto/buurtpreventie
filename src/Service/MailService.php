<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\MailException;
use App\Model\WalkerDayModel;
use Swift_Mailer;
use Swift_Message;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as TwigTemplating;
use Twig\Error\Error as TwigError;

/**
 * MailService
 */
class MailService
{
    /**
     * @var string
     */
    private $site;

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
     * @var TwigTemplating
     */
    private $templating;

    /**
     * @param  string          $fromName
     * @param  string          $fromEmail
     * @param  Swift_Mailer    $mailer
     * @param  TwigTemplating  $templating
     * @param  RouterInterface $router
     */
    public function __construct($fromName, $fromEmail, Swift_Mailer $mailer, TwigTemplating $templating, RouterInterface $router)
    {
        $this->site = $fromName;
        $this->from = [$fromEmail => $fromName];
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->router = $router;
    }

    /**
     * @param  User $user
     * @throws MailException
     */
    public function activateNewUser(User $user)
    {
        $message = new Swift_Message();
        $message->setSubject(sprintf('Welkom bij %s', $this->site));
        $message->setFrom($this->from);
        $message->setTo($user->getEmail(), $user->getName());

        if (null === $user->getToken()) {
            $url = $this->router->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $message->setBody(sprintf('Beste %s,\n\nWelkom als gebruiker van de actieve buurtpreventie.\n\nGa om in te loggen naar:\n%s', $user, $url));
            $message->addPart(sprintf('<p>Beste %s,</p><p>Welkom als gebruiker van de actieve buurtpreventie</p><p>Ga om in te loggen naar:<br><a href="%s">%s</a></p>', $user, $url, $url), 'text/html');
        } else {
            $url = $this->router->generate('token', ['token' => $user->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
            $valid = (null !== $user->getTokenValidUntil()) ? sprintf('Let op: de link is geldig tot %s.', $user->getTokenValidUntil()->format('d-m-Y H:i')) : '';

            $message->setBody(sprintf('Beste %s,\n\nWelkom als gebruiker van de actieve buurtpreventie.\n\nJe kunt je wachtwoord instellen via de onderstaande link:\n%s\n\n%s.', $user, $url, $valid));
            $message->addPart(sprintf('<p>Beste %s,</p><p>Welkom als gebruiker van de actieve buurtpreventie</p><p>Je kunt je wachtwoord instellen via de onderstaande link:<br><a href="%s">%s</a></p><p>&nbsp;</p><p>%s</p>', $user, $url, $url, $valid), 'text/html');
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
        $message->setSubject(sprintf('Herstel je wachtwoord voor %s', $this->site));
        $message->setFrom($this->from);
        $message->setTo($user->getEmail(), $user->getName());
        $message->setBody(sprintf('Beste %s,\n\nJe kunt je wachtwoord instellen via de onderstaande link:\n%s', $user, $url));
        $message->addPart(sprintf('<p>Beste %s,</p><p>Je kunt je wachtwoord instellen via de onderstaande link:<br><a href="%s">%s</a></p>', $user, $url, $url), 'text/html');

        $recipients = $this->mailer->send($message);
        if ($recipients === 0) {
            throw new MailException('Verzenden van e-mail voor herstellen van wachtwoord is mislukt.');
        }
    }

    /**
     * @param  WalkerDayModel $model
     * @throws MailException
     * @throws TwigError
     */
    public function sendReminder(WalkerDayModel $model)
    {
        $message = new Swift_Message();
        $message->setSubject(sprintf('Herinnering deelname loopronde %s', $model->getDate()->format('d-m-Y')));
        $message->setFrom($this->from);
        $message->setTo($model->getWalker()->getEmail(), $model->getWalker()->getName());

        $url = $this->router->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $txt = $this->templating->render('mail/reminder.txt.twig', ['model' => $model, 'siteName' => $this->site, 'url' => $url]);
        $message->setBody($txt);

        $recipients = $this->mailer->send($message);
        if ($recipients === 0) {
            throw new MailException('Verzenden van e-mail voor loop-herinnering is mislukt.');
        }
    }
}
