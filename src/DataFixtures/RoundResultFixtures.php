<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Result;
use App\Entity\Round;
use App\Entity\RoundResult;
use App\Entity\User;
use Doctrine\Persistence\ObjectManager;

class RoundResultFixtures extends AbstractDataFixtures
{
    public function getDependencies(): array
    {
        return [
            ResultFixtures::class,
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

        $result1 = new RoundResult();
        $result1->setCreatedBy($user_walker_3);
        $result1->setRound($this->getReference(RoundFixtures::PAST_1_REFERENCE, Round::class));
        $result1->setResult($this->getReference(ResultFixtures::REMARKS_REFERENCE, Result::class));
        $result1->setMemo('Praesent tempor mi ac rutrum dictum. Cras consequat, nisi non sagittis pulvinar, mauris ex pulvinar justo, in imperdiet sapien ante eu magna.');
        $this->addFixture($result1);

        $result2 = new RoundResult();
        $result2->setCreatedBy($user_walker_2);
        $result2->setRound($this->getReference(RoundFixtures::PAST_2_REFERENCE, Round::class));
        $result2->setResult($this->getReference(ResultFixtures::INCIDENT_REFERENCE, Result::class));
        $result2->setMemo('In quis lectus pharetra, efficitur libero ut, vulputate orci. Maecenas dapibus auctor augue ut ultrices. In tincidunt vel mauris et commodo. Nulla convallis tellus lectus, in congue dolor ullamcorper dapibus. Donec auctor risus vel sem dictum, bibendum cursus lacus vestibulum. Quisque at condimentum turpis. Sed imperdiet fermentum nisi, et vulputate libero. Sed at ipsum lacus. ');
        $this->addFixture($result2);

        $result3a = new RoundResult();
        $result3a->setCreatedBy($user_walker_1);
        $result3a->setRound($this->getReference(RoundFixtures::PAST_4_REFERENCE, Round::class));
        $result3a->setResult($this->getReference(ResultFixtures::NO_REMARKS_REFERENCE, Result::class));
        $this->addFixture($result3a);

        $result3b = new RoundResult();
        $result3b->setCreatedBy($user_walker_4);
        $result3b->setRound($this->getReference(RoundFixtures::PAST_4_REFERENCE, Round::class));
        $result3b->setResult($this->getReference(ResultFixtures::REMARKS_REFERENCE, Result::class));
        $result3b->setMemo('Aliquam libero nisi, hendrerit non dignissim non, ullamcorper ac dolor. Maecenas quis nulla non purus euismod venenatis nec id urna. Etiam velit leo, vehicula tristique turpis nec, venenatis finibus velit. Phasellus efficitur pulvinar neque, sit amet tristique arcu lobortis sed.');
        $this->addFixture($result3b);
    }
}
