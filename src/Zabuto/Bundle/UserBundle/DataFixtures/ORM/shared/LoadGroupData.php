<?php

namespace Zabuto\Bundle\UserBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Zabuto\Bundle\UserBundle\Entity\Group;

class LoadGroupData extends AbstractFixture implements OrderedFixtureInterface
{

    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager)
    {
        $groupBeheer = new Group('Beheer');
        $groupBeheer->setRoles(array('ROLE_USER', 'ROLE_ADMIN', 'ROLE_SUPERUSER'));
        $manager->persist($groupBeheer);
        $manager->flush();
        $this->addReference('group-beheer', $groupBeheer);

        $groupCoordinator = new Group('Coördinator');
        $groupCoordinator->setRoles(array('ROLE_USER', 'ROLE_ADMIN'));
        $manager->persist($groupCoordinator);
        $manager->flush();
        $this->addReference('group-coordinator', $groupCoordinator);

        $groupLoper = new Group('Loper');
        $groupLoper->setRoles(array('ROLE_USER'));
        $manager->persist($groupLoper);
        $manager->flush();
        $this->addReference('group-loper', $groupLoper);
    }

    /**
     * {@inheritDoc}
     */
    public function getOrder()
    {
        return 1;
    }

}
