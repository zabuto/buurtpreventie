<?php

namespace App\DataFixtures;

use App\Entity\Comment;
use DateTime;
use Doctrine\Common\Persistence\ObjectManager;
use Exception;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * CommentFixtures
 */
class CommentFixtures extends AbstractDataFixtures
{
    /**
     * @return array
     */
    public function getDependencies()
    {
        return [
            RoundFixtures::class,
            UserFixtures::class,
        ];
    }

    /**
     * @param  ObjectManager $manager
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {
        /** @var UserInterface $user_walker_1 */
        $user_walker_1 = $this->getReference(UserFixtures::WALKER1_REFERENCE);
        /** @var UserInterface $user_walker_2 */
        $user_walker_2 = $this->getReference(UserFixtures::WALKER2_REFERENCE);
        /** @var UserInterface $user_walker_3 */
        $user_walker_3 = $this->getReference(UserFixtures::WALKER3_REFERENCE);
        /** @var UserInterface $user_walker_4 */
        $user_walker_4 = $this->getReference(UserFixtures::WALKER4_REFERENCE);

        $date_past_2a = new DateTime();
        $date_past_2a->modify('-10 day');
        $past2a = new Comment();
        $past2a->setCreatedBy($user_walker_2);
        $past2a->setCreatedAt($date_past_2a);
        $past2a->setRound($this->getReference(RoundFixtures::PAST_2_REFERENCE));
        $past2a->setMemo('Proin suscipit dignissim diam, nec rhoncus ipsum tempor vitae. Phasellus ullamcorper lobortis nisl quis tempus.');
        $this->addFixture($past2a);

        $date_past_2b = new DateTime();
        $date_past_2b->modify('-9 day');
        $past2b = new Comment();
        $past2b->setCreatedBy($user_walker_3);
        $past2b->setCreatedAt($date_past_2b);
        $past2b->setRound($this->getReference(RoundFixtures::PAST_2_REFERENCE));
        $past2b->setMemo('Suspendisse massa odio, volutpat a placerat ac, elementum quis nibh.');
        $this->addFixture($past2b);

        $date_past_2c = new DateTime();
        $date_past_2c->modify('-9 day')->modify('+15 minutes');
        $past2c = new Comment();
        $past2c->setCreatedBy($user_walker_4);
        $past2c->setCreatedAt($date_past_2c);
        $past2c->setRound($this->getReference(RoundFixtures::PAST_2_REFERENCE));
        $past2c->setMemo('Vestibulum finibus velit urna. Nullam libero turpis, consequat id hendrerit ac, tempus sit amet libero. Aenean rhoncus urna nec sem lobortis, et sagittis elit mattis. Suspendisse ullamcorper, quam vel gravida tempus, quam mi porttitor leo, malesuada placerat risus nulla sed quam.');
        $this->addFixture($past2c);

        $date_past_2d = new DateTime();
        $date_past_2d->modify('-8 day')->modify('-1 hour')->modify('+5 minutes')->modify('+5 seconds');
        $past2d = new Comment();
        $past2d->setCreatedBy($user_walker_2);
        $past2d->setCreatedAt($date_past_2d);
        $past2d->setRound($this->getReference(RoundFixtures::PAST_2_REFERENCE));
        $past2d->setMemo('Proin vitae.');
        $this->addFixture($past2d);

        $date_future_1a = new DateTime();
        $date_future_1a->modify('-2 day');
        $future1a = new Comment();
        $future1a->setCreatedBy($user_walker_1);
        $future1a->setCreatedAt($date_future_1a);
        $future1a->setRound($this->getReference(RoundFixtures::FUTURE_1_REFERENCE));
        $future1a->setMemo('Pellentesque non diam urna. Cras ac leo ut odio efficitur accumsan.');
        $this->addFixture($future1a);
    }
}
