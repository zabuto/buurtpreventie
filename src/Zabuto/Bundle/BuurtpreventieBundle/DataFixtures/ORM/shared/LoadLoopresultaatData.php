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
        $resultaatNvt = new Loopresultaat();
        $resultaatNvt->setOmschrijving('Geen opmerkingen');
        $resultaatNvt->setBijzonderheid(false);
        $resultaatNvt->setIncident(false);
        $manager->persist($resultaatNvt);
        $manager->flush();
        $this->addReference('loopresultaat-nvt', $resultaatNvt);

        $resultaatOk = new Loopresultaat();
        $resultaatOk->setOmschrijving('Bijzonderheden');
        $resultaatOk->setBijzonderheid(true);
        $resultaatOk->setIncident(false);
        $manager->persist($resultaatOk);
        $manager->flush();
        $this->addReference('loopresultaat-ok', $resultaatOk);

        $resultaatNok = new Loopresultaat();
        $resultaatNok->setOmschrijving('Incident melding');
        $resultaatNok->setBijzonderheid(true);
        $resultaatNok->setIncident(true);
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
