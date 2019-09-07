<?php

namespace App\EventListener;

use DateTime;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Exception;

/**
 * TimestampSubscriber
 */
class TimestampSubscriber implements EventSubscriber
{
    /**
     * @return array|string[]
     */
    public function getSubscribedEvents()
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }

    /**
     * @param  LifecycleEventArgs $args
     * @throws Exception
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if (!method_exists($entity, 'getCreatedAt') || !method_exists($entity, 'setCreatedAt')) {
            return;
        }

        if (null === $entity->getCreatedAt()) {
            $entity->setCreatedAt(new DateTime());
        }
    }

    /**
     * @param  LifecycleEventArgs $args
     * @throws Exception
     */
    public function preUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if (!method_exists($entity, 'setUpdatedAt')) {
            return;
        }

        $entity->setUpdatedAt(new DateTime());
    }
}
