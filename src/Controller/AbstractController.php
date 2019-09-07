<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Component\Security\Core\Security;

/**
 * AbstractController
 */
abstract class AbstractController extends SymfonyAbstractController
{
    /**
     * Constructor
     *
     * @param  Security               $security
     * @param  EntityManagerInterface $entityManager
     */
    public function __construct(Security $security, EntityManagerInterface $entityManager)
    {
        if ($security->isGranted('ROLE_SUPER_ADMIN')) {
            if ($entityManager->getFilters()->isEnabled('soft_delete')) {
                $entityManager->getFilters()->disable('soft_delete');
            }
        } else {
            $entityManager->getFilters()->enable('soft_delete');
        }
    }
}
