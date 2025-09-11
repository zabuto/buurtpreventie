<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\MeetingPoint;
use App\Geo\ValueObject\Point;
use Doctrine\Persistence\ObjectManager;

class MeetingPointFixtures extends AbstractDataFixtures
{
    public const string BUSSTOP_REFERENCE = 'meeting-point-busstop';
    public const string CORNER_REFERENCE = 'meeting-point-corner';

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $this->setManager($manager);

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
