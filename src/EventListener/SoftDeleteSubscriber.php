<?php declare(strict_types=1);

namespace App\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Bundle\SecurityBundle\Security;

readonly class SoftDeleteSubscriber implements EventSubscriber
{
    public function __construct(private Security $security)
    {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate,
            Events::onFlush,
        ];
    }

    public function preUpdate(LifecycleEventArgs $args): void
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

    public function onFlush(OnFlushEventArgs $args): void
    {
        $om = $args->getObjectManager();
        $unitOfWork = $om->getUnitOfWork();
        $eventManager = $om->getEventManager();

        #-- remove event, if we call $this->em->flush() now there is no infinite recursion loop!
        $eventManager->removeEventListener('onFlush', $this);

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if (!method_exists($entity, 'getDeletedAt') || !method_exists($entity, 'delete')) {
                continue; // hard-delete
            }

            if (method_exists($entity, 'isHardDelete') && true === $entity->isHardDelete()) {
                continue; // hard-delete
            }

            $oldDeletedAtValue = $entity->getDeletedAt();

            $entity->delete();
            $om->persist($entity);

            $unitOfWork->propertyChanged($entity, 'deletedAt', $oldDeletedAtValue, $entity->getDeletedAt());
            $update = [
                'deletedAt' => [$oldDeletedAtValue, $entity->getDeletedAt()],
            ];

            $unitOfWork->scheduleExtraUpdate($entity, $update);
        }

        $eventManager->addEventListener('onFlush', $this);
    }
}
