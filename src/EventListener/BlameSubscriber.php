<?php declare(strict_types=1);

namespace App\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Bundle\SecurityBundle\Security;

readonly class BlameSubscriber implements EventSubscriber
{
    public function __construct(private Security $security)
    {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!method_exists($entity, 'getCreatedBy') || !method_exists($entity, 'setCreatedBy')) {
            return;
        }

        if (null === $entity->getCreatedBy()) {
            $entity->setCreatedBy($this->security->getUser());
        }
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!method_exists($entity, 'setUpdatedBy')) {
            return;
        }

        $entity->setUpdatedBy($this->security->getUser());
    }
}
