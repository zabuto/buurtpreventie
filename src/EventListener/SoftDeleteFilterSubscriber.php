<?php declare(strict_types=1);

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class SoftDeleteFilterSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security               $security,
    )
    {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            if ($this->entityManager->getFilters()->isEnabled('soft_delete')) {
                $this->entityManager->getFilters()->disable('soft_delete');
            }
        } else {
            $this->entityManager->getFilters()->enable('soft_delete');
        }
    }
}
