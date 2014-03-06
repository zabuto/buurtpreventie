<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopresultaat;

class LoadLoopresultaatData extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager)
    {
        $resultaatOk = new Loopresultaat();
        $resultaatOk->setOmschrijving('Geen bijzonderheden');
        $resultaatOk->setBijzonderheid(false);
        $manager->persist($resultaatOk);
        $manager->flush();
        $this->addReference('loopresultaat-ok', $resultaatOk);

        $resultaatNok = new Loopresultaat();
        $resultaatNok->setOmschrijving('Bijzonderheden');
        $resultaatNok->setBijzonderheid(true);
        $manager->persist($resultaatNok);
        $manager->flush();
        $this->addReference('loopresultaat-nok', $resultaatNok);
    }

    /**
     * {@inheritDoc}
     */
    public function getOrder()
    {
        return 100;
    }
}
