<?php

namespace Zabuto\Bundle\UserBundle\DataFixtures\ORM;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Zabuto\Bundle\UserBundle\Entity\User;

class LoadGebruikerData extends AbstractFixture implements OrderedFixtureInterface, ContainerAwareInterface
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
        $userBeheer->setEmail('beheer@test.nl');
        $userBeheer->setPlainPassword('test');
        $userBeheer->setRealName('Bert Beheer');
        $userBeheer->setAddress('Plein 3');
        $userBeheer->setPhone('06-11111111');
        $userBeheer->setEnabled(true);
        $groups = new ArrayCollection;
        $groups->add($manager->merge($this->getReference('group-beheer')));
        $userBeheer->setGroups($groups);
        $userManager->updateCanonicalFields($userBeheer);
        $userManager->updatePassword($userBeheer);
        $manager->persist($userBeheer);
        $manager->flush();
        $this->addReference('user-beheer', $userBeheer);

        $userCoordinator = new User();
        $userCoordinator->setEmail('coordinator@test.nl');
        $userCoordinator->setPlainPassword('test');
        $userCoordinator->setRealName('Cees Coördinator');
        $userCoordinator->setAddress('Straat 2a');
        $userCoordinator->setPhone('040-1111111');
        $userCoordinator->setEnabled(true);
        $groups = new ArrayCollection;
        $groups->add($manager->merge($this->getReference('group-coordinator')));
        $userCoordinator->setGroups($groups);
        $userManager->updateCanonicalFields($userCoordinator);
        $userManager->updatePassword($userCoordinator);
        $manager->persist($userCoordinator);
        $manager->flush();
        $this->addReference('user-coordinator', $userCoordinator);

        $userLoper1 = new User();
        $userLoper1->setEmail('een@test.nl');
        $userLoper1->setPlainPassword('test');
        $userLoper1->setRealName('Leopold Loper');
        $userLoper1->setAddress('Laan 11');
        $userLoper1->setPhone('040-2222222');
        $userLoper1->setCredit(true);
        $userLoper1->setEnabled(true);
        $groups = new ArrayCollection;
        $groups->add($manager->merge($this->getReference('group-loper')));
        $userLoper1->setGroups($groups);
        $userManager->updateCanonicalFields($userLoper1);
        $userManager->updatePassword($userLoper1);
        $manager->persist($userLoper1);
        $manager->flush();
        $this->addReference('user-loper-1', $userLoper1);

        $userLoper2 = new User();
        $userLoper2->setEmail('twee@test.nl');
        $userLoper2->setPlainPassword('test');
        $userLoper2->setRealName('Sonja Struin');
        $userLoper2->setAddress('Hoofdbaan 123b');
        $userLoper2->setEnabled(true);
        $groups = new ArrayCollection;
        $groups->add($manager->merge($this->getReference('group-loper')));
        $userLoper2->setGroups($groups);
        $userManager->updateCanonicalFields($userLoper2);
        $userManager->updatePassword($userLoper2);
        $manager->persist($userLoper2);
        $manager->flush();
        $this->addReference('user-loper-2', $userLoper2);

        $userLoper3 = new User();
        $userLoper3->setEmail('drie@test.nl');
        $userLoper3->setPlainPassword('test');
        $userLoper3->setRealName('Willie Wandel');
        $userLoper3->setPhone('040-3333333');
        $userLoper3->setCredit(true);
        $userLoper3->setEnabled(true);
        $groups = new ArrayCollection;
        $groups->add($manager->merge($this->getReference('group-loper')));
        $userLoper3->setGroups($groups);
        $userManager->updateCanonicalFields($userLoper3);
        $userManager->updatePassword($userLoper3);
        $manager->persist($userLoper3);
        $manager->flush();
        $this->addReference('user-loper-3', $userLoper3);
    }

    /**
     * {@inheritDoc}
     */
    public function getOrder()
    {
        return 2;
    }
}
