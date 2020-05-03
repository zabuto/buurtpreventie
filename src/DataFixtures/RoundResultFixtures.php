<?php

namespace App\DataFixtures;

use App\Entity\RoundResult;
use Doctrine\Common\Persistence\ObjectManager;
use Exception;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * RoundResultFixtures
 */
class RoundResultFixtures extends AbstractDataFixtures
{
    /**
     * @return array
     */
    public function getDependencies()
    {
        return [
            ResultFixtures::class,
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

        $result1 = new RoundResult();
        $result1->setCreatedBy($user_walker_3);
        $result1->setRound($this->getReference(RoundFixtures::PAST_1_REFERENCE));
        $result1->setResult($this->getReference(ResultFixtures::REMARKS_REFERENCE));
        $result1->setMemo('Praesent tempor mi ac rutrum dictum. Cras consequat, nisi non sagittis pulvinar, mauris ex pulvinar justo, in imperdiet sapien ante eu magna.');
        $this->addFixture($result1);

        $result2 = new RoundResult();
        $result2->setCreatedBy($user_walker_2);
        $result2->setRound($this->getReference(RoundFixtures::PAST_2_REFERENCE));
        $result2->setResult($this->getReference(ResultFixtures::INCIDENT_REFERENCE));
        $result2->setMemo('In quis lectus pharetra, efficitur libero ut, vulputate orci. Maecenas dapibus auctor augue ut ultrices. In tincidunt vel mauris et commodo. Nulla convallis tellus lectus, in congue dolor ullamcorper dapibus. Donec auctor risus vel sem dictum, bibendum cursus lacus vestibulum. Quisque at condimentum turpis. Sed imperdiet fermentum nisi, et vulputate libero. Sed at ipsum lacus. ');
        $this->addFixture($result2);

        $result3a = new RoundResult();
        $result3a->setCreatedBy($user_walker_1);
        $result3a->setRound($this->getReference(RoundFixtures::PAST_4_REFERENCE));
        $result3a->setResult($this->getReference(ResultFixtures::NO_REMARKS_REFERENCE));
        $this->addFixture($result3a);

        $result3b = new RoundResult();
        $result3b->setCreatedBy($user_walker_4);
        $result3b->setRound($this->getReference(RoundFixtures::PAST_4_REFERENCE));
        $result3b->setResult($this->getReference(ResultFixtures::REMARKS_REFERENCE));
        $result3b->setMemo('Aliquam libero nisi, hendrerit non dignissim non, ullamcorper ac dolor. Maecenas quis nulla non purus euismod venenatis nec id urna. Etiam velit leo, vehicula tristique turpis nec, venenatis finibus velit. Phasellus efficitur pulvinar neque, sit amet tristique arcu lobortis sed.');
        $this->addFixture($result3b);
    }
}
