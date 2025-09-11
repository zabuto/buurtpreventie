<?php declare(strict_types=1);

namespace App\EventListener;

use DateTimeImmutable;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class TimestampSubscriber implements EventSubscriber
{
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

        if (!method_exists($entity, 'getCreatedAt') || !method_exists($entity, 'setCreatedAt')) {
            return;
        }

        if (null === $entity->getCreatedAt()) {
            $entity->setCreatedAt(new DateTimeImmutable());
        }
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!method_exists($entity, 'setUpdatedAt')) {
            return;
        }

        $entity->setUpdatedAt(new DateTimeImmutable());
    }
}
