<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Comment;
use App\Entity\Round;
use App\Entity\User;
use DateTime;
use DateTimeImmutable;
use Doctrine\Persistence\ObjectManager;

class CommentFixtures extends AbstractDataFixtures
{
    public function getDependencies(): array
    {
        return [
            RoundFixtures::class,
            UserFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $this->setManager($manager);

        $user_walker_1 = $this->getReference(UserFixtures::WALKER1_REFERENCE, User::class);
        $user_walker_2 = $this->getReference(UserFixtures::WALKER2_REFERENCE, User::class);
        $user_walker_3 = $this->getReference(UserFixtures::WALKER3_REFERENCE, User::class);
        $user_walker_4 = $this->getReference(UserFixtures::WALKER4_REFERENCE, User::class);

        $date_past_2a = new DateTime();
        $date_past_2a->modify('-10 day');
        $past2a = new Comment();
        $past2a->setCreatedBy($user_walker_2);
        $past2a->setCreatedAt(DateTimeImmutable::createFromMutable($date_past_2a));
        $past2a->setRound($this->getReference(RoundFixtures::PAST_2_REFERENCE, Round::class));
        $past2a->setMemo('Proin suscipit dignissim diam, nec rhoncus ipsum tempor vitae. Phasellus ullamcorper lobortis nisl quis tempus.');
        $this->addFixture($past2a);

        $date_past_2b = new DateTime();
        $date_past_2b->modify('-9 day');
        $past2b = new Comment();
        $past2b->setCreatedBy($user_walker_3);
        $past2b->setCreatedAt(DateTimeImmutable::createFromMutable($date_past_2b));
        $past2b->setRound($this->getReference(RoundFixtures::PAST_2_REFERENCE, Round::class));
        $past2b->setMemo('Suspendisse massa odio, volutpat a placerat ac, elementum quis nibh.');
        $this->addFixture($past2b);

        $date_past_2c = new DateTime();
        $date_past_2c->modify('-9 day')->modify('+15 minutes');
        $past2c = new Comment();
        $past2c->setCreatedBy($user_walker_4);
        $past2c->setCreatedAt(DateTimeImmutable::createFromMutable($date_past_2c));
        $past2c->setRound($this->getReference(RoundFixtures::PAST_2_REFERENCE, Round::class));
        $past2c->setMemo('Vestibulum finibus velit urna. Nullam libero turpis, consequat id hendrerit ac, tempus sit amet libero. Aenean rhoncus urna nec sem lobortis, et sagittis elit mattis. Suspendisse ullamcorper, quam vel gravida tempus, quam mi porttitor leo, malesuada placerat risus nulla sed quam.');
        $this->addFixture($past2c);

        $date_past_2d = new DateTime();
        $date_past_2d->modify('-8 day')->modify('-1 hour')->modify('+5 minutes')->modify('+5 seconds');
        $past2d = new Comment();
        $past2d->setCreatedBy($user_walker_2);
        $past2d->setCreatedAt(DateTimeImmutable::createFromMutable($date_past_2d));
        $past2d->setRound($this->getReference(RoundFixtures::PAST_2_REFERENCE, Round::class));
        $past2d->setMemo('Proin vitae.');
        $this->addFixture($past2d);

        $date_future_1a = new DateTime();
        $date_future_1a->modify('-2 day');
        $future1a = new Comment();
        $future1a->setCreatedBy($user_walker_1);
        $future1a->setCreatedAt(DateTimeImmutable::createFromMutable($date_future_1a));
        $future1a->setRound($this->getReference(RoundFixtures::FUTURE_1_REFERENCE, Round::class));
        $future1a->setMemo('Pellentesque non diam urna. Cras ac leo ut odio efficitur accumsan.');
        $this->addFixture($future1a);
    }
}
