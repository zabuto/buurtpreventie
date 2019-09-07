<?php

namespace App\EventListener;

use App\Interfaces\LastLoginInterface;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * LoginListener
 */
class LoginListener
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @param  EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @param  InteractiveLoginEvent $event
     * @throws Exception
     */
    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event)
    {
        $user = $event->getAuthenticationToken()->getUser();
        if ($user instanceof LastLoginInterface) {
            $user->setLastLogin(new DateTime());

            $this->em->persist($user);
            $this->em->flush();
        }
    }
}
