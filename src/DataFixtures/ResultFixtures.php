<?php

namespace App\DataFixtures;

use App\Entity\Result;
use Doctrine\Common\Persistence\ObjectManager;
use Exception;

/**
 * ResultFixtures
 */
class ResultFixtures extends AbstractDataFixtures
{
    /** @var string */
    public const NO_REMARKS_REFERENCE = 'result-no-remarks';

    /** @var string */
    public const REMARKS_REFERENCE = 'result-remarks';

    /** @var string */
    public const INCIDENT_REFERENCE = 'result-incident';

    /**
     * @param  ObjectManager $manager
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {
        $noremarks = new Result();
        $noremarks->setDescription($this->translator->trans('walk.result.no-remarks'));
        $noremarks->setRemarks(false);
        $noremarks->setIncident(false);
        $this->addFixture($noremarks, self::NO_REMARKS_REFERENCE);

        $remarks = new Result();
        $remarks->setDescription($this->translator->trans('walk.result.remarks'));
        $remarks->setRemarks(true);
        $remarks->setIncident(false);
        $this->addFixture($remarks, self::REMARKS_REFERENCE);

        $incident = new Result();
        $incident->setDescription('Incident melding');
        $incident->setDescription($this->translator->trans('walk.result.incident'));
        $incident->setRemarks(true);
        $incident->setIncident(true);
        $this->addFixture($incident, self::INCIDENT_REFERENCE);
    }
}
