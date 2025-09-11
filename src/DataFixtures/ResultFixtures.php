<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Result;
use Doctrine\Persistence\ObjectManager;

class ResultFixtures extends AbstractDataFixtures
{
    public const string NO_REMARKS_REFERENCE = 'result-no-remarks';
    public const string REMARKS_REFERENCE = 'result-remarks';
    public const string INCIDENT_REFERENCE = 'result-incident';

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $this->setManager($manager);

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
        $incident->setDescription($this->translator->trans('walk.result.incident'));
        $incident->setRemarks(true);
        $incident->setIncident(true);
        $this->addFixture($incident, self::INCIDENT_REFERENCE);
    }
}
