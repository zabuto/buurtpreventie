<?php

namespace App\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Exception;
use Symfony\Component\Security\Core\Security;

/**
 * SoftDeleteSubscriber
 */
class SoftDeleteSubscriber implements EventSubscriber
{
    /**
     * @var Security
     */
    private $security;

    /**
     * Constructors
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
            Events::preUpdate,
            Events::onFlush,
        ];
    }

    /**
     * @param  LifecycleEventArgs $args
     * @throws Exception
     */
    public function preUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if (!method_exists($entity, 'getDeletedAt')
            || !method_exists($entity, 'getDeletedBy')
            || !method_exists($entity, 'setDeletedBy')
        ) {
            return;
        }

        if (null === $entity->getDeletedAt()) {
            $entity->setDeletedBy(null);

            return;
        }

        if (null === $entity->getDeletedBy()) {
            $entity->setDeletedBy($this->security->getUser());
        }
    }

    /**
     * @param  OnFlushEventArgs $args
     * @throws Exception
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $unitOfWork = $em->getUnitOfWork();
        $eventManager = $em->getEventManager();

        #-- remove event, if we call $this->em->flush() now there is no infinite recursion loop!
        $eventManager->removeEventListener('onFlush', $this);

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if (!method_exists($entity, 'getDeletedAt') || !method_exists($entity, 'delete')) {
                continue; // hard-delete
            }

            $oldDeletedAtValue = $entity->getDeletedAt();

            $entity->delete();
            $em->persist($entity);

            $unitOfWork->propertyChanged($entity, 'deletedAt', $oldDeletedAtValue, $entity->getDeletedAt());
            $update = [
                'deletedAt' => [$oldDeletedAtValue, $entity->getDeletedAt()],
            ];

            $unitOfWork->scheduleExtraUpdate($entity, $update);
        }

        $eventManager->addEventListener('onFlush', $this);
    }
}
