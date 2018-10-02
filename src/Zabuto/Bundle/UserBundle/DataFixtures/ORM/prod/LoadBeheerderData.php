<?php

namespace Zabuto\Bundle\UserBundle\DataFixtures\ORM;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Zabuto\Bundle\UserBundle\Entity\User;

class LoadBeheerderData extends AbstractFixture implements OrderedFixtureInterface, ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager)
    {
        $userManager = $this->container->get('fos_user.user_manager');

        $userBeheer = new User();
        $userBeheer->setEmail('anke@zabuto.com');
        $userBeheer->setPlainPassword('buurtpreventie');
        $userBeheer->setRealName('Beheerder');
        $userBeheer->setEnabled(true);
        $groups = new ArrayCollection;
        $groups->add($manager->merge($this->getReference('group-beheer')));
        $userBeheer->setGroups($groups);
        $userManager->updateCanonicalFields($userBeheer);
        $userManager->updatePassword($userBeheer);
        $manager->persist($userBeheer);
        $manager->flush();
        $this->addReference('user-beheer', $userBeheer);
    }

    /**
     * {@inheritDoc}
     */
    public function getOrder()
    {
        return 2;
    }
}
