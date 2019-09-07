<?php

namespace App\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Exception;
use Symfony\Component\Security\Core\Security;

/**
 * BlameSubscriber
 */
class BlameSubscriber implements EventSubscriber
{
    /**
     * @var Security
     */
    private $security;

    /**
     * Constructor
     *
     * @param  Security $security
     */
    public function __construct(Security $security)
    {
        $this->security = $security;
    }

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

        if (!method_exists($entity, 'getCreatedBy') || !method_exists($entity, 'setCreatedBy')) {
            return;
        }

        if (null === $entity->getCreatedBy()) {
            $entity->setCreatedBy($this->security->getUser());
        }
    }

    /**
     * @param  LifecycleEventArgs $args
     * @throws Exception
     */
    public function preUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if (!method_exists($entity, 'setUpdatedBy')) {
            return;
        }

        $entity->setUpdatedBy($this->security->getUser());
    }
}
