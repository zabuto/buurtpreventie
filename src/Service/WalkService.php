<?php declare(strict_types=1);

namespace App\Service;

use App\Dto\Formatter\DateTimeFormatter;
use App\Entity\AbstractBaseEntity;
use App\Entity\Comment;
use App\Entity\Result;
use App\Entity\Round;
use App\Entity\RoundResult;
use App\Entity\RoundWalker;
use App\Entity\User;
use App\Model\MetricModel;
use App\Model\ResultsModel;
use App\Repository\CommentRepository;
use App\Repository\ResultRepository;
use App\Repository\RoundRepository;
use App\Repository\RoundResultRepository;
use App\Repository\RoundWalkerRepository;
use DateTime;
use Doctrine\ORM\EntityNotFoundException;
use Exception;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class WalkService
{
    public function __construct(
        private CommentRepository     $commentRepo,
        private ResultRepository      $resultRepo,
        private RoundRepository       $roundRepo,
        private RoundResultRepository $roundResultRepo,
        private RoundWalkerRepository $roundWalkerRepo,
        private Security              $security,
        #[Autowire(env: 'APPLICATION_WALKER_MINIMUM')]
        private int                   $walkerMinimum,
    )
    {
    }

    public function getWalkerMinimum(): int
    {
        return $this->walkerMinimum;
    }

    /**
     * @return Round[]
     */
    public function getWalked(): array
    {
        $user = $this->security->getUser();
        if (!($user instanceof User)) {
            return [];
        }

        $rounds = $this->roundRepo->getWalkedRounds($user, 'DESC');
        foreach ($rounds as $key => $round) {
            $walkers = $round->getWalkers();
            if (count($walkers) < $this->walkerMinimum) {
                unset($rounds[$key]);
            }
        }

        return $rounds;
    }

    public function addRound(Round $round, ?string $memo): ?Round
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

        $this->roundRepo->create($round);

        return $round;
    }

    public function walkRound(Round $round, ?string $memo): ?Round
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

        $this->roundRepo->update($round);

        return $round;
    }

    public function exitRound(Round $round): ?Round
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $walker = $this->roundWalkerRepo->getByRoundWalker($round, $user);
        if (null === $walker) {
            return null;
        }

        $round->removeWalker($walker);
        $this->roundWalkerRepo->delete($walker);

        if (count($round->getWalkers()) === 0) {
            $this->roundRepo->delete($round);
        } else {
            $this->roundRepo->update($round);
        }

        return $round;
    }

    public function roundResult(Round $round, Result $result, ?string $memo): RoundResult
    {
        $roundResult = new RoundResult();
        $roundResult->setRound($round);
        $roundResult->setResult($result);

        if (null !== $memo && $result->allowRemarks()) {
            $roundResult->setMemo(trim($memo));
        }

        $this->roundResultRepo->create($roundResult);

        return $roundResult;
    }

    public function isUserRoundResult(RoundResult $result): bool
    {
        $owner = $result->getUpdatedBy() ?? $result->getCreatedBy();

        return $owner === $this->security->getUser();
    }

    public function addComment(Round $round, string $memo): Comment
    {
        $comment = new Comment();
        $comment->setRound($round);
        $comment->setMemo($memo);

        $this->commentRepo->create($comment);

        return $comment;
    }

    public function isUserComment(Comment $comment): bool
    {
        $owner = $comment->getUpdatedBy() ?? $comment->getCreatedBy();

        return $owner === $this->security->getUser();
    }

    public function walkerRemoveFromFutureRounds(User $user): void
    {
        $walking = $this->roundWalkerRepo->getFutureForWalker($user);
        foreach ($walking as $walk) {
            $walk->doHardDelete();
            $this->roundWalkerRepo->delete($walk);
        }
    }

    /**
     * @return array<int, string>
     */
    public function getWalkedWith(Round $round): array
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $list = [];
        $inactive = 0;

        foreach ($round->getWalkers() as $roundWalker) {
            try {
                $walker = $roundWalker->getWalker();
                if ($walker === $user) {
                    continue;
                }

                if (null !== $walker && $walker->isActive() && !$walker->isDeleted()) {
                    $list[] = $walker->getName();
                } else {
                    $inactive++;
                }
            } catch (EntityNotFoundException) {
                $inactive++;
            }
        }

        sort($list);
        if ($inactive > 0) {
            $list[] = sprintf('%s inactieve %s', $inactive, ($inactive === 1) ? 'loper' : 'lopers');
        }

        return $list;
    }

    public function hasMinimumWalkers(Round $round): bool
    {
        $walkers = $round->getWalkers();

        return count($walkers) >= $this->walkerMinimum;
    }

    public function getWalkers(Round $round): array
    {
        $entities = $this->roundWalkerRepo->findAllWalkersByRound($round);
        $list = [];
        $inactive = 0;
        $deleted = 0;

        foreach ($entities as $entity) {
            if ($entity->isDeleted()) {
                $deleted++;
                continue;
            }

            if (null !== $entity->getWalker() && false === $entity->getWalker()->isDeleted()) {
                $list[] = (string)$entity->getWalker();
            } else {
                $inactive++;
            }
        }

        sort($list);
        if ($inactive > 0) {
            $list[] = sprintf('%s inactieve %s', $inactive, ($inactive === 1) ? 'loper' : 'lopers');
        }

        if ($deleted > 0 && ($this->security->isGranted('ROLE_COORDINATE'))) {
            $list[] = sprintf('%s %s afgemeld', $deleted, ($deleted === 1) ? 'loper' : 'lopers');
        }

        return $list;
    }

    /**
     * @throws Exception
     */
    public function wasWalked(Round $round): bool
    {
        if (false === $this->inPast($round)) {
            return false;
        }

        return $this->hasMinimumWalkers($round);
    }

    public function userWalking(Round $round): bool
    {
        /** @var User $user */
        $user = $this->security->getUser();

        foreach ($round->getWalkers() as $walker) {
            if ($walker->getWalker() === $user) {
                return true;
            }
        }

        return false;
    }

    public function userResult(Round $round): ?RoundResult
    {
        /** @var User $user */
        $user = $this->security->getUser();

        foreach ($round->getResults() as $result) {
            if (false === $result->isDeleted() && $result->getCreatedBy() === $user) {
                return $result;
            }
        }

        return null;
    }

    public function inPast(Round $round): bool
    {
        $today = new DateTime();
        $date = $round->getDatetime()?->format('Y-m-d');
        $time = $round->getDatetime()?->format('H:i');

        if ($date > $today->format('Y-m-d')) {
            return false;
        }

        if ($date === $today->format('Y-m-d') && $time > $today->format('H:i')) {
            return false;
        }

        return true;
    }

    public function getTimeOfDay(Round $round): ?string
    {
        return DateTimeFormatter::timeOfDay($round->getDatetime(), $round);
    }

    public function hasResult(Round $round): bool
    {
        return count($round->getResults()) > 0;
    }

    public function hasIncident(Round $round): bool
    {
        foreach ($round->getResults() as $result) {
            if ($result->getResult()?->isIncident()) {
                return true;
            }
        }

        return false;
    }

    public function getResults(?User $user = null): ResultsModel
    {
        $list = $this->roundResultRepo->getOrderedResults($user, 'DESC');

        $total = count($list);
        $metrics = [];
        $results = $this->resultRepo->findAll();
        foreach ($results as $result) {
            $class = 'secondary';
            if ($result->isIncident()) {
                $class = 'danger';
            } elseif ($result->isRemarks()) {
                $class = 'success';
            }

            $metric = new MetricModel(id: $result->getId(), description: $result->getDescription(), class: $class);
            $metrics[$result->getId()] = $metric;
        }

        foreach ($list as $roundResult) {
            if (null !== $roundResult->getResult()) {
                /** @var MetricModel $metric */
                $metric = $metrics[$roundResult->getResult()->getId()];
                $metric->add($total);
            }
        }

        return new ResultsModel(metrics: $metrics, list: $list);
    }

    public function getUserName(mixed $entity): string
    {
        try {
            if ($entity instanceof AbstractBaseEntity) {
                /** @var User|null $user */
                $user = $entity->getUpdatedBy() ?? $entity->getCreatedBy();
                if (null !== $user && false === $user->isDeleted()) {
                    return (string)$user;
                }
            }
        } catch (Exception) {
        }

        return 'Inactieve loper';
    }
}
