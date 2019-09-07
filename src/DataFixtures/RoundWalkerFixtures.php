<?php

namespace App\DataFixtures;

use App\Entity\RoundWalker;
use Doctrine\Common\Persistence\ObjectManager;
use Exception;

/**
 * RoundWalkerFixtures
 */
class RoundWalkerFixtures extends AbstractDataFixtures
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
        $user_walker_1 = $this->getReference(UserFixtures::WALKER1_REFERENCE);
        $user_walker_2 = $this->getReference(UserFixtures::WALKER2_REFERENCE);
        $user_walker_3 = $this->getReference(UserFixtures::WALKER3_REFERENCE);

        $walk_p_1a = new RoundWalker();
        $walk_p_1a->setCreatedBy($user_walker_1);
        $walk_p_1a->setWalker($user_walker_1);
        $walk_p_1a->setRound($this->getReference(RoundFixtures::PAST_1_REFERENCE));
        $this->addFixture($walk_p_1a);

        $walk_p_1b = new RoundWalker();
        $walk_p_1b->setCreatedBy($user_walker_3);
        $walk_p_1b->setWalker($user_walker_3);
        $walk_p_1b->setRound($this->getReference(RoundFixtures::PAST_1_REFERENCE));
        $this->addFixture($walk_p_1b);

        $walk_p_2a = new RoundWalker();
        $walk_p_2a->setCreatedBy($user_walker_2);
        $walk_p_2a->setWalker($user_walker_2);
        $walk_p_2a->setRound($this->getReference(RoundFixtures::PAST_2_REFERENCE));
        $this->addFixture($walk_p_2a);

        $walk_p_2b = new RoundWalker();
        $walk_p_2b->setCreatedBy($user_walker_1);
        $walk_p_2b->setWalker($user_walker_1);
        $walk_p_2b->setRound($this->getReference(RoundFixtures::PAST_2_REFERENCE));
        $this->addFixture($walk_p_2b);

        $walk_p_2c = new RoundWalker();
        $walk_p_2c->setCreatedBy($user_walker_3);
        $walk_p_2c->setWalker($user_walker_3);
        $walk_p_2c->setRound($this->getReference(RoundFixtures::PAST_2_REFERENCE));
        $this->addFixture($walk_p_2c);

        $walk_p_3a = new RoundWalker();
        $walk_p_3a->setCreatedBy($user_walker_3);
        $walk_p_3a->setWalker($user_walker_3);
        $walk_p_3a->setRound($this->getReference(RoundFixtures::PAST_3_REFERENCE));
        $this->addFixture($walk_p_3a);

        $walk_p_3b = new RoundWalker();
        $walk_p_3b->setCreatedBy($user_walker_1);
        $walk_p_3b->setWalker($user_walker_1);
        $walk_p_3b->setRound($this->getReference(RoundFixtures::PAST_3_REFERENCE));
        $this->addFixture($walk_p_3b);

        $walk_p_4a = new RoundWalker();
        $walk_p_4a->setCreatedBy($user_walker_1);
        $walk_p_4a->setWalker($user_walker_1);
        $walk_p_4a->setRound($this->getReference(RoundFixtures::PAST_4_REFERENCE));
        $this->addFixture($walk_p_4a);

        $walk_p_4b = new RoundWalker();
        $walk_p_4b->setCreatedBy($user_walker_2);
        $walk_p_4b->setWalker($user_walker_2);
        $walk_p_4b->setRound($this->getReference(RoundFixtures::PAST_4_REFERENCE));
        $this->addFixture($walk_p_4b);

        $walk_p_5a = new RoundWalker();
        $walk_p_5a->setCreatedBy($user_walker_1);
        $walk_p_5a->setWalker($user_walker_1);
        $walk_p_5a->setRound($this->getReference(RoundFixtures::PAST_5_REFERENCE));
        $this->addFixture($walk_p_5a);

        $walk_f_1a = new RoundWalker();
        $walk_f_1a->setCreatedBy($user_walker_1);
        $walk_f_1a->setWalker($user_walker_1);
        $walk_f_1a->setRound($this->getReference(RoundFixtures::FUTURE_1_REFERENCE));
        $this->addFixture($walk_f_1a);

        $walk_f_1b = new RoundWalker();
        $walk_f_1b->setCreatedBy($user_walker_3);
        $walk_f_1b->setWalker($user_walker_3);
        $walk_f_1b->setRound($this->getReference(RoundFixtures::FUTURE_1_REFERENCE));
        $this->addFixture($walk_f_1b);

        $walk_f_2a = new RoundWalker();
        $walk_f_2a->setCreatedBy($user_walker_2);
        $walk_f_2a->setWalker($user_walker_2);
        $walk_f_2a->setRound($this->getReference(RoundFixtures::FUTURE_2_REFERENCE));
        $this->addFixture($walk_f_2a);

        $walk_f_3a = new RoundWalker();
        $walk_f_3a->setCreatedBy($user_walker_3);
        $walk_f_3a->setWalker($user_walker_3);
        $walk_f_3a->setRound($this->getReference(RoundFixtures::FUTURE_3_REFERENCE));
        $this->addFixture($walk_p_3a);

        $walk_f_3b = new RoundWalker();
        $walk_f_3b->setCreatedBy($user_walker_2);
        $walk_f_3b->setWalker($user_walker_2);
        $walk_f_3b->setRound($this->getReference(RoundFixtures::FUTURE_3_REFERENCE));
        $this->addFixture($walk_f_3b);

        $walk_f_4a = new RoundWalker();
        $walk_f_4a->setCreatedBy($user_walker_1);
        $walk_f_4a->setWalker($user_walker_1);
        $walk_f_4a->setRound($this->getReference(RoundFixtures::FUTURE_4_REFERENCE));
        $this->addFixture($walk_f_4a);
    }
}
