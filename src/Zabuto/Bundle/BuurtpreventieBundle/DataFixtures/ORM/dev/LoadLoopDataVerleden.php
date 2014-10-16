<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema;
use Zabuto\Bundle\BuurtpreventieBundle\Entity\Looptoelichting;
use DateTime;

class LoadLoopData extends AbstractFixture implements OrderedFixtureInterface, ContainerAwareInterface
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
        $user1 = $this->container->get('fos_user.user_manager')->findUserBy(array('id' => $this->getReference('user-loper-1')));
        $user2 = $this->container->get('fos_user.user_manager')->findUserBy(array('id' => $this->getReference('user-loper-2')));
        $user3 = $this->container->get('fos_user.user_manager')->findUserBy(array('id' => $this->getReference('user-loper-3')));

        $date = new DateTime();

        $date->modify('-5 day');

        $schema1 = new Loopschema();
        $schema1->setLoper($user2);
        $schema1->setActueel(true);
        $schema1->setResultaat($manager->merge($this->getReference('loopresultaat-nvt')));
        $schema1->setAangemaaktOp(new DateTime());
        $schema1->setGewijzigdOp(new DateTime());
        $schema1->setDatum($date);
        $manager->persist($schema1);
        $manager->flush();
        $this->addReference('loopschemaverleden-1', $schema1);

        $schema2 = new Loopschema();
        $schema2->setLoper($user3);
        $schema2->setActueel(true);
        $schema2->setResultaat($manager->merge($this->getReference('loopresultaat-ok')));
        $schema2->setBijzonderheden('Aangesproken door diverse buurtgenoten: fijn dat we lopen!');
        $schema2->setAangemaaktOp(new DateTime());
        $schema2->setGewijzigdOp(new DateTime());
        $schema2->setDatum($date);
        $manager->persist($schema2);
        $manager->flush();
        $this->addReference('loopschemaverleden-2', $schema2);

        $date->modify('+2 day');

        $schema3 = new Loopschema();
        $schema3->setLoper($user1);
        $schema3->setActueel(true);
        $schema3->setResultaat($manager->merge($this->getReference('loopresultaat-nok')));
        $schema3->setBijzonderheden('We hebben verdachte personen gezien bij het hofje. Deze gingen snel weg toen ze ons zagen lopen.');
        $schema3->setAangemaaktOp(new DateTime());
        $schema3->setGewijzigdOp(new DateTime());
        $schema3->setDatum($date);
        $manager->persist($schema3);
        $manager->flush();
        $this->addReference('loopschemaverleden-3', $schema3);

        $schema4 = new Loopschema();
        $schema4->setLoper($user3);
        $schema4->setActueel(true);
        $schema4->setAangemaaktOp(new DateTime());
        $schema4->setGewijzigdOp(new DateTime());
        $schema4->setDatum($date);
        $manager->persist($schema4);
        $manager->flush();
        $this->addReference('loopschemaverleden-4', $schema4);

        $date->modify('+1 day');

        $schema5 = new Loopschema();
        $schema5->setLoper($user1);
        $schema5->setActueel(true);
        $schema5->setAangemaaktOp(new DateTime());
        $schema5->setGewijzigdOp(new DateTime());
        $schema5->setDatum($date);
        $manager->persist($schema5);
        $manager->flush();
        $this->addReference('loopschemaverleden-5', $schema5);

        $schema6 = new Loopschema();
        $schema6->setLoper($user2);
        $schema6->setActueel(true);
        $schema6->setAangemaaktOp(new DateTime());
        $schema6->setGewijzigdOp(new DateTime());
        $schema6->setDatum($date);
        $manager->persist($schema6);
        $manager->flush();
        $this->addReference('loopschemaverleden-6', $schema6);

        $this->container->get('session')->getFlashBag()->clear();
    }

    /**
     * {@inheritDoc}
     */
    public function getOrder()
    {
        return 101;
    }
}
