<?php declare(strict_types=1);

namespace App\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
readonly class BlameSubscriber
{
    public function __construct(private Security $security)
    {
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
