<?php

namespace App\DataFixtures;

use App\Entity\MeetingPoint;
use App\Geo\ValueObject\Point;
use Doctrine\Common\Persistence\ObjectManager;
use Exception;

/**
 * MeetingPointFixtures
 */
class MeetingPointFixtures extends AbstractDataFixtures
{
    /** @var string */
    public const BUSSTOP_REFERENCE = 'meeting-point-busstop';

    /** @var string */
    public const CORNER_REFERENCE = 'meeting-point-corner';

    /**
     * @param  ObjectManager $manager
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {
        $busstop = new MeetingPoint();
        $busstop->setDescription($this->translator->trans('walk.meeting-point.busstop'));
        $busstop->setLocation(Point::latlng('51.422976,5.510360'));
        $this->addFixture($busstop, self::BUSSTOP_REFERENCE);

        $corner = new MeetingPoint();
        $corner->setDescription($this->translator->trans('walk.meeting-point.corner'));
        $corner->setLocation(Point::latlng('51.418693,5.515972'));
        $this->addFixture($corner, self::CORNER_REFERENCE);
    }
}
