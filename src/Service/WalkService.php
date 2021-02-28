<?php

namespace App\Service;

use App\Entity\AbstractBaseEntity;
use App\Entity\Comment;
use App\Entity\Result;
use App\Entity\Round;
use App\Entity\RoundResult;
use App\Entity\RoundWalker;
use App\Entity\User;
use App\Interfaces\WalkerInterface;
use App\Model\MetricModel;
use App\Model\ResultsModel;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Security\Core\Security;

/**
 * WalkService
 */
class WalkService
{
    /** time of day */
    public const TIMEOFDAY_MORNING = 'morning';
    public const TIMEOFDAY_AFTERNOON = 'afternoon';
    public const TIMEOFDAY_EVENING = 'evening';

    /**
     * @var int
     */
    private $walkerMinimum;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var Security
     */
    private $security;

    /**
     * @param  int                    $walkerMinimum
     * @param  EntityManagerInterface $em
     * @param  Security               $security
     */
    public function __construct($walkerMinimum, EntityManagerInterface $em, Security $security)
    {
        $this->walkerMinimum = $walkerMinimum;
        $this->em = $em;
        $this->security = $security;
    }

    /**
     * @return int
     */
    public function getWalkerMinimum(): int
    {
        return $this->walkerMinimum;
    }

    /**
     * @return Round[]
     * @throws Exception
     */
    public function getWalked()
    {
        $user = $this->security->getUser();
        if (!($user instanceof WalkerInterface)) {
            return [];
        }

        $repo = $this->em->getRepository(Round::class);
        $rounds = $repo->getWalkedRounds($user, 'DESC');

        foreach ($rounds as $key => $round) {
            $walkers = $round->getWalkers();
            if (count($walkers) < $this->walkerMinimum) {
                unset($rounds[$key]);
            }
        }

        return $rounds;
    }

    /**
     * @param  Round       $round
     * @param  string|null $memo
     * @return Round|null
     */
    public function addRound(Round $round, ?string $memo)
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $walker = new RoundWalker();
        $walker->setWalker($user);
        $round->addWalker($walker);

        if (!empty($memo)) {
            $comment = new Comment();
            $comment->setMemo($memo);
            $round->addComment($comment);
        }

        $this->em->persist($round);
        $this->em->flush();

        return $round;
    }

    /**
     * @param  Round       $round
     * @param  string|null $memo
     * @return Round|null
     */
    public function walkRound(Round $round, ?string $memo)
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $walker = new RoundWalker();
        $walker->setWalker($user);
        $round->addWalker($walker);

        if (!empty($memo)) {
            $comment = new Comment();
            $comment->setMemo($memo);
            $round->addComment($comment);
        }

        $this->em->flush();

        return $round;
    }

    /**
     * @param  Round $round
     * @return Round|null
     */
    public function exitRound(Round $round)
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $walker = $this->em->getRepository(RoundWalker::class)->getByRoundWalker($round, $user);
        if (null === $walker) {
            return null;
        }

        $round->removeWalker($walker);
        $this->em->remove($walker);

        if (count($round->getWalkers()) === 0) {
            $this->em->remove($round);
        }

        $this->em->flush();

        return $round;
    }

    /**
     * @param  Round       $round
     * @param  Result      $result
     * @param  string|null $memo
     * @return RoundResult
     */
    public function roundResult(Round $round, Result $result, ?string $memo)
    {
        $roundResult = new RoundResult();
        $roundResult->setRound($round);
        $roundResult->setResult($result);

        if ($result->allowRemarks() && trim($memo) != '') {
            $roundResult->setMemo(trim($memo));
        }

        $this->em->persist($roundResult);
        $this->em->flush();

        return $roundResult;
    }

    /**
     * @param  Round  $round
     * @param  string $memo
     * @return Comment
     */
    public function addComment(Round $round, string $memo)
    {
        $comment = new Comment();
        $comment->setRound($round);
        $comment->setMemo($memo);

        $this->em->persist($comment);
        $this->em->flush();

        return $comment;
    }

    /**
     * @param  Comment $comment
     * @return bool
     */
    public function userComment(Comment $comment)
    {
        $owner = $comment->getCreatedBy();
        if (null !== $comment->getUpdatedBy()) {
            $owner = $comment->getUpdatedBy();
        }

        if ($owner === $this->security->getUser()) {
            return true;
        }

        return false;
    }

    /**
     * @param  User $user
     */
    public function walkerRemoveFromFutureRounds(User $user)
    {
        $walking = $this->em->getRepository(RoundWalker::class)->getFutureForWalker($user);
        foreach ($walking as $walk) {
            $walk->doHardDelete();
            $this->em->remove($walk);
        }
    }

    /**
     * @param  Round $round
     * @return string[]
     */
    public function getWalkedWith(Round $round)
    {
        $list = [];
        $inactive = 0;

        foreach ($round->getWalkers() as $walker) {
            if ($walker->getWalker() !== $this->security->getUser()) {
                try {
                    $list[] = $walker->getWalker()->getName();
                } catch (Exception $e) {
                    $inactive++;
                }
            }
        }

        sort($list);
        if ($inactive > 0) {
            $list[] = sprintf('%s inactieve %s', $inactive, ($inactive === 1) ? 'loper' : 'lopers');
        }

        return $list;
    }

    /**
     * @param  Round $round
     * @return bool
     */
    public function hasMinimumWalkers(Round $round)
    {
        $walkers = $round->getWalkers();
        if (count($walkers) >= $this->walkerMinimum) {
            return true;
        }

        return false;
    }

    /**
     * @param  Round $round
     * @return array
     */
    public function getWalkers(Round $round)
    {
        $filterEnabled = $this->em->getFilters()->isEnabled('soft_delete');
        if ($filterEnabled) {
            $this->em->getFilters()->disable('soft_delete');
        }

        $entities = $this->em->getRepository(RoundWalker::class)->findBy(['round' => $round]);
        $list = [];
        $inactive = 0;
        $deleted = 0;

        /** @var RoundWalker $entity */
        foreach ($entities as $entity) {
            if ($entity->isDeleted()) {
                $deleted++;
                continue;
            }

            /** @var User $user */
            $user = $entity->getWalker();
            if (false === $user->isDeleted()) {
                $list[] = (string)$user;
            } else {
                $inactive++;
            }
        }

        sort($list);
        if ($inactive > 0) {
            $list[] = sprintf('%s inactieve %s', $inactive, ($inactive === 1) ? 'loper' : 'lopers');
        }

        if ($deleted > 0 && ($this->security->isGranted('ROLE_COORDINATE'))) {
            $list[] = sprintf('%s %s verwijderd', $deleted, ($deleted === 1) ? 'loper' : 'lopers');
        }

        if ($filterEnabled) {
            $this->em->getFilters()->enable('soft_delete');
        }

        return $list;
    }

    /**
     * @param  Round $round
     * @return bool
     * @throws Exception
     */
    public function wasWalked(Round $round)
    {
        if (false === $this->inPast($round)) {
            return false;
        }

        return $this->hasMinimumWalkers($round);
    }

    /**
     * @param  Round $round
     * @return bool
     * @throws Exception
     */
    public function userWalking(Round $round)
    {
        foreach ($round->getWalkers() as $walker) {
            if ($walker->getWalker() === $this->security->getUser()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Round $round
     * @return RoundResult|null
     */
    public function userResult(Round $round)
    {
        foreach ($round->getResults() as $result) {
            if ($result->getCreatedBy() === $this->security->getUser() && false === $result->isDeleted()) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param  Round $round
     * @return bool
     * @throws Exception
     */
    public function inPast(Round $round)
    {
        $today = new DateTime();
        $date = $round->getDate()->format('Y-m-d');
        $time = $round->getTime()->format('H:i');

        if ($date > $today->format('Y-m-d')) {
            return false;
        }

        if ($date === $today->format('Y-m-d') && $time > $today->format('H:i')) {
            return false;
        }

        return true;
    }

    /**
     * @param  Round $round
     * @return string
     */
    public function getTimeOfDay(Round $round)
    {
        $hour = (int)$round->getTime()->format('H');

        if ($hour < 12) {
            return self::TIMEOFDAY_MORNING;
        } elseif ($hour < 18) {
            return self::TIMEOFDAY_AFTERNOON;
        } else {
            return self::TIMEOFDAY_EVENING;
        }
    }

    /**
     * @param  Round $round
     * @return bool
     */
    public function hasResult(Round $round)
    {
        if (count($round->getResults()) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param  Round $round
     * @return bool
     */
    public function hasIncident(Round $round)
    {
        foreach ($round->getResults() as $result) {
            if ($result->getResult()->isIncident()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  WalkerInterface|null $walker
     * @return ResultsModel
     */
    public function getResults(?WalkerInterface $walker = null)
    {
        $repo = $this->em->getRepository(RoundResult::class);
        $list = $repo->getOrderedResults($walker, 'DESC');

        $total = count($list);
        $metrics = [];
        $results = $this->em->getRepository(Result::class)->findAll();
        foreach ($results as $result) {
            $metric = new MetricModel($result->getId(), $result->getDescription());
            if ($result->isIncident()) {
                $metric->setClass('danger');
            } elseif ($result->isRemarks()) {
                $metric->setClass('success');
            } else {
                $metric->setClass('secondary');
            }

            $metrics[$result->getId()] = $metric;
        }

        foreach ($list as $roundResult) {
            /** @var MetricModel $metric */
            $metric = $metrics[$roundResult->getResult()->getId()];
            $metric->add($total);
        }

        $model = new ResultsModel();
        $model->setList($list);
        $model->setMetrics($metrics);

        return $model;
    }

    /**
     * @param  object $entity
     * @return string
     */
    public function getUserName($entity)
    {
        try {
            if ($entity instanceof AbstractBaseEntity) {
                /** @var User $user */
                $user = (null !== $entity->getUpdatedBy()) ? $entity->getUpdatedBy() : $entity->getCreatedBy();
                if (null !== $user && false === $user->isDeleted()) {
                    return (string)$user;
                }
            }
        } catch (Exception $e) {
        }

        return 'Inactieve loper';
    }
}
