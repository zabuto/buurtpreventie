<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\MeetingPoint;
use App\Entity\Round;
use App\Entity\User;
use DateTime;
use DateTimeImmutable;
use Doctrine\Persistence\ObjectManager;

class RoundFixtures extends AbstractDataFixtures
{
    public const string PAST_1_REFERENCE = 'round-past-1';
    public const string PAST_2_REFERENCE = 'round-past-2';
    public const string PAST_3_REFERENCE = 'round-past-3';
    public const string PAST_4_REFERENCE = 'round-past-4';
    public const string PAST_5_REFERENCE = 'round-past-5';
    public const string FUTURE_1_REFERENCE = 'round-future-1';
    public const string FUTURE_2_REFERENCE = 'round-future-2';
    public const string FUTURE_3_REFERENCE = 'round-future-3';
    public const string FUTURE_4_REFERENCE = 'round-future-4';

    public function getDependencies(): array
    {
        return [
            MeetingPointFixtures::class,
            UserFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $this->setManager($manager);

        $meetingpoint_busstop = $this->getReference(MeetingPointFixtures::BUSSTOP_REFERENCE, MeetingPoint::class);
        $meetingpoint_corner = $this->getReference(MeetingPointFixtures::CORNER_REFERENCE, MeetingPoint::class);

        $user_walker_1 = $this->getReference(UserFixtures::WALKER1_REFERENCE, User::class);
        $user_walker_2 = $this->getReference(UserFixtures::WALKER2_REFERENCE, User::class);
        $user_walker_3 = $this->getReference(UserFixtures::WALKER3_REFERENCE, User::class);

        $date_past_1 = new DateTime();
        $date_past_1->modify('-7 day')->setTime(10, 15);
        $past_1 = new Round();
        $past_1->setCreatedBy($user_walker_1);
        $past_1->setDatetime(DateTimeImmutable::createFromMutable($date_past_1));
        $past_1->setMeetingPoint($meetingpoint_busstop);
        $this->addFixture($past_1, self::PAST_1_REFERENCE);

        $date_past_2 = new DateTime();
        $date_past_2->modify('-7 day')->setTime(15, 30);
        $past_2 = new Round();
        $past_2->setCreatedBy($user_walker_2);
        $past_2->setDatetime(DateTimeImmutable::createFromMutable($date_past_2));
        $this->addFixture($past_2, self::PAST_2_REFERENCE);

        $date_past_3 = new DateTime();
        $date_past_3->modify('-5 day')->setTime(12, 00);
        $past_3 = new Round();
        $past_3->setCreatedBy($user_walker_3);
        $past_3->setDatetime(DateTimeImmutable::createFromMutable($date_past_3));
        $past_3->setMeetingPoint($meetingpoint_corner);
        $this->addFixture($past_3, self::PAST_3_REFERENCE);

        $date_past_4 = new DateTime();
        $date_past_4->modify('-3 day')->setTime(19, 30);
        $past_4 = new Round();
        $past_4->setCreatedBy($user_walker_1);
        $past_4->setDatetime(DateTimeImmutable::createFromMutable($date_past_4));
        $past_4->setMeetingPoint($meetingpoint_corner);
        $this->addFixture($past_4, self::PAST_4_REFERENCE);

        $date_past_5 = new DateTime();
        $date_past_5->modify('-2 day')->setTime(19, 30);
        $past_5 = new Round();
        $past_5->setCreatedBy($user_walker_1);
        $past_5->setDatetime(DateTimeImmutable::createFromMutable($date_past_5));
        $this->addFixture($past_5, self::PAST_5_REFERENCE);

        $date_future_1 = new DateTime();
        $date_future_1->modify('+1 day')->setTime(10, 30);
        $future_1 = new Round();
        $future_1->setCreatedBy($user_walker_1);
        $future_1->setDatetime(DateTimeImmutable::createFromMutable($date_future_1));
        $future_1->setMeetingPoint($meetingpoint_busstop);
        $this->addFixture($future_1, self::FUTURE_1_REFERENCE);

        $date_future_2 = new DateTime();
        $date_future_2->modify('+2 day')->setTime(12, 30);
        $future_2 = new Round();
        $future_2->setCreatedBy($user_walker_1);
        $future_2->setDatetime(DateTimeImmutable::createFromMutable($date_future_2));
        $future_2->setMeetingPoint($meetingpoint_corner);
        $this->addFixture($future_2, self::FUTURE_2_REFERENCE);

        $date_future_3 = new DateTime();
        $date_future_3->modify('+2 day')->setTime(18, 00);
        $future_3 = new Round();
        $future_3->setCreatedBy($user_walker_2);
        $future_3->setDatetime(DateTimeImmutable::createFromMutable($date_future_3));
        $future_3->setMeetingPoint($meetingpoint_busstop);
        $this->addFixture($future_3, self::FUTURE_3_REFERENCE);

        $date_future_4 = new DateTime();
        $date_future_4->modify('+5 day')->setTime(14, 45);
        $future_4 = new Round();
        $future_4->setCreatedBy($user_walker_3);
        $future_4->setDatetime(DateTimeImmutable::createFromMutable($date_future_4));
        $future_4->setMeetingPoint($meetingpoint_busstop);
        $this->addFixture($future_4, self::FUTURE_4_REFERENCE);
    }
}
