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

class LoadLoopDataToekomst extends AbstractFixture implements OrderedFixtureInterface, ContainerAwareInterface
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

        $schema1 = new Loopschema();
        $schema1->setLoper($user1);
        $schema1->setActueel(true);
        $schema1->setAangemaaktOp(new DateTime());
        $schema1->setGewijzigdOp(new DateTime());
        $schema1->setDatum($date);
        $manager->persist($schema1);
        $manager->flush();
        $this->addReference('loopschematoekomst-1', $schema1);

        $memo1 = new Looptoelichting();
        $memo1->setLoopschema($schema1);
        $memo1->setMemo('Ik loop een grote route');
        $memo1->setAangemaaktOp(new DateTime());
        $manager->persist($memo1);
        $manager->flush();
        $this->addReference('looptoelichtingtoekomst-1-a', $memo1);

        $schema2 = new Loopschema();
        $schema2->setLoper($user3);
        $schema2->setActueel(true);
        $schema2->setAangemaaktOp(new DateTime());
        $schema2->setGewijzigdOp(new DateTime());
        $schema2->setDatum($date);
        $manager->persist($schema2);
        $manager->flush();
        $this->addReference('loopschematoekomst-2', $schema2);

        $memo2 = new Looptoelichting();
        $memo2->setLoopschema($schema2);
        $memo2->setMemo('Ik kan vanaf 19:30');
        $memo2->setAangemaaktOp(new DateTime());
        $manager->persist($memo2);
        $manager->flush();
        $this->addReference('looptoelichtingtoekomst-2-a', $memo2);

        $date->modify('+1 day');

        $schema3 = new Loopschema();
        $schema3->setLoper($user1);
        $schema3->setActueel(true);
        $schema3->setAangemaaktOp(new DateTime());
        $schema3->setGewijzigdOp(new DateTime());
        $schema3->setDatum($date);
        $manager->persist($schema3);
        $manager->flush();
        $this->addReference('loopschematoekomst-3', $schema3);

        $memo3 = new Looptoelichting();
        $memo3->setLoopschema($schema3);
        $memo3->setMemo('Ik wil met de hond lopen');
        $memo3->setAangemaaktOp(new DateTime());
        $manager->persist($memo3);
        $manager->flush();
        $this->addReference('looptoelichtingtoekomst-3-a', $memo3);

        $date->modify('+1 day');

        $schema4 = new Loopschema();
        $schema4->setLoper($user1);
        $schema4->setActueel(true);
        $schema4->setAangemaaktOp(new DateTime());
        $schema4->setGewijzigdOp(new DateTime());
        $schema4->setDatum($date);
        $manager->persist($schema4);
        $manager->flush();
        $this->addReference('loopschematoekomst-4', $schema4);

        $dateAangemaakt = new DateTime();
        $dateAangemaakt->modify('-3 day');

        $memo4a = new Looptoelichting();
        $memo4a->setLoopschema($schema4);
        $memo4a->setMemo('Ik wil met mijn hond lopen');
        $memo4a->setAangemaaktOp($dateAangemaakt);
        $manager->persist($memo4a);
        $manager->flush();
        $this->addReference('looptoelichtingtoekomst-4-a', $memo4a);

        $schema5 = new Loopschema();
        $schema5->setLoper($user2);
        $schema5->setActueel(true);
        $schema5->setAangemaaktOp(new DateTime());
        $schema5->setGewijzigdOp(new DateTime());
        $schema5->setDatum($date);
        $manager->persist($schema5);
        $manager->flush();
        $this->addReference('loopschematoekomst-5', $schema5);

        $dateAangemaakt->modify('+1 day');

        $memo5 = new Looptoelichting();
        $memo5->setLoopschema($schema5);
        $memo5->setMemo('Ik kan tot 21:00, hond is geen probleem!');
        $memo5->setAangemaaktOp($dateAangemaakt);
        $manager->persist($memo5);
        $manager->flush();
        $this->addReference('looptoelichtingtoekomst-5-a', $memo5);

        $dateAangemaakt = new DateTime();
        $dateAangemaakt->modify('-3 hours');

        $memo4b = new Looptoelichting();
        $memo4b->setLoopschema($schema4);
        $memo4b->setMemo('Zullen we dan om 19:30 afspreken?');
        $memo4b->setAangemaaktOp($dateAangemaakt);
        $manager->persist($memo4b);
        $manager->flush();
        $this->addReference('looptoelichtingtoekomst-4-b', $memo4b);

        $date->modify('+2 day');

        $schema6 = new Loopschema();
        $schema6->setLoper($user3);
        $schema6->setActueel(true);
        $schema6->setAangemaaktOp(new DateTime());
        $schema6->setGewijzigdOp(new DateTime());
        $schema6->setDatum($date);
        $manager->persist($schema6);
        $manager->flush();
        $this->addReference('loopschematoekomst-6', $schema6);

        $memo6 = new Looptoelichting();
        $memo6->setLoopschema($schema6);
        $memo6->setMemo('Ik kan maar een kort rondje lopen');
        $memo6->setAangemaaktOp(new DateTime());
        $manager->persist($memo6);
        $manager->flush();
        $this->addReference('looptoelichtingtoekomst-6-a', $memo6);

        $date->modify('+3 day');

        $schema7 = new Loopschema();
        $schema7->setLoper($user2);
        $schema7->setActueel(true);
        $schema7->setAangemaaktOp(new DateTime());
        $schema7->setGewijzigdOp(new DateTime());
        $schema7->setDatum($date);
        $manager->persist($schema7);
        $manager->flush();
        $this->addReference('loopschematoekomst-7', $schema7);

        $memo7 = new Looptoelichting();
        $memo7->setLoopschema($schema7);
        $memo7->setMemo('Vanaf 20:00 ben ik beschikbaar');
        $memo7->setAangemaaktOp(new DateTime());
        $manager->persist($memo7);
        $manager->flush();
        $this->addReference('looptoelichtingtoekomst-7-a', $memo7);

        $schema8 = new Loopschema();
        $schema8->setLoper($user3);
        $schema8->setActueel(true);
        $schema8->setAangemaaktOp(new DateTime());
        $schema8->setGewijzigdOp(new DateTime());
        $schema8->setDatum($date);
        $manager->persist($schema8);
        $manager->flush();
        $this->addReference('loopschematoekomst-8', $schema8);

        $memo8 = new Looptoelichting();
        $memo8->setLoopschema($schema8);
        $memo8->setMemo('Ik loop met mijn hond');
        $memo8->setAangemaaktOp(new DateTime());
        $manager->persist($memo8);
        $manager->flush();
        $this->addReference('looptoelichtingtoekomst-8-a', $memo8);

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
