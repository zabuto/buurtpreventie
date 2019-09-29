<?php

namespace App\Service;

use App\Entity\Round;
use App\Model\WalkerDayModel;
use App\Model\WalkModel;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

/**
 * CalendarService
 */
class CalendarService
{
    /**
     * @var WalkService
     */
    private $walkService;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * Constructor
     *
     * @param  WalkService            $walkService
     * @param  EntityManagerInterface $entityManager
     */
    public function __construct(WalkService $walkService, EntityManagerInterface $entityManager)
    {
        $this->walkService = $walkService;
        $this->entityManager = $entityManager;
    }

    /**
     * @param  int $year
     * @param  int $month
     * @return array
     * @throws Exception
     */
    public function getMonth(int $year, int $month)
    {
        $this->entityManager->getFilters()->enable('soft_delete');
        $repo = $this->entityManager->getRepository(Round::class);
        $rounds = $repo->getRoundsForMonth($year, $month);
        $dates = $this->getDates($rounds);

        $data = [];
        foreach ($dates as $date => $info) {
            $parts = [];
            if ($info['morning'] > 0) {
                if ($info['morning_ok'] > 0) {
                    $parts[] = 'm-1';
                } else {
                    $parts[] = 'm-0';
                }
            }

            if ($info['afternoon'] > 0) {
                if ($info['afternoon_ok'] > 0) {
                    $parts[] = 'a-1';
                } else {
                    $parts[] = 'a-0';
                }
            }

            if ($info['evening'] > 0) {
                if ($info['evening_ok'] > 0) {
                    $parts[] = 'e-1';
                } else {
                    $parts[] = 'e-0';
                }
            }

            $markup = null;
            if ($info['walked'] > 0) {
                if ($info['incident'] > 0) {
                    $markup = '<span class="badge badge-danger">[day]</span>';
                } elseif ($info['result'] > 0) {
                    $markup = '<span class="badge badge-success">[day]</span>';
                } else {
                    $markup = '<span class="badge badge-secondary">[day]</span>';
                }
            } elseif ($info['walking'] > 0) {
                $markup = '<span class="badge badge-warning">[day]</span>';
            }

            $data[] = [
                'date'      => $date,
                'classname' => 'tod-' . join('-', $parts),
                'markup'    => $markup,
            ];
        }

        return $data;
    }

    /**
     * @param  DateTime $date
     * @return WalkerDayModel[]
     * @throws Exception
     */
    public function getWalksForDate(DateTime $date)
    {
        $this->entityManager->getFilters()->enable('soft_delete');
        $repo = $this->entityManager->getRepository(Round::class);
        $rounds = $repo->getRoundsForDate($date);

        $walks = [];
        foreach ($rounds as $round) {
            foreach ($round->getWalkers() as $roundWalker) {
                if ($roundWalker->wasReminded()) {
                    continue;
                }

                $walker = $roundWalker->getWalker();
                $walk = new WalkModel(
                    $roundWalker->getId(),
                    $round,
                    $this->walkService->getTimeOfDay($round),
                    $this->walkService->hasMinimumWalkers($round)
                );

                $key = sprintf('%s|%s', $walker->getId(), $walk->getDate());
                if (array_key_exists($key, $walks)) {
                    $model = $walks[$key];
                } else {
                    $model = new WalkerDayModel();
                    $model->setWalker($walker);
                    $model->setDate($round->getDate());
                }

                $model->addWalk($walk);

                $walks[$key] = $model;
            }
        }

        return array_values($walks);
    }

    /**
     * @param  Round[] $rounds
     * @return array
     * @throws Exception
     */
    private function getDates(array $rounds)
    {
        $dates = [];
        foreach ($rounds as $round) {
            $date = $round->getDate()->format('Y-m-d');
            $tod = $this->walkService->getTimeOfDay($round);
            $min = $this->walkService->hasMinimumWalkers($round);

            if (!array_key_exists($date, $dates)) {
                $item = [
                    'walking'      => 0,
                    'walked'       => 0,
                    'result'       => 0,
                    'incident'     => 0,
                    'morning'      => 0,
                    'morning_ok'   => 0,
                    'afternoon'    => 0,
                    'afternoon_ok' => 0,
                    'evening'      => 0,
                    'evening_ok'   => 0,
                ];
            } else {
                $item = $dates[$date];
            }

            if ($tod === WalkService::TIMEOFDAY_MORNING) {
                $item['morning']++;
                if ($min) {
                    $item['morning_ok']++;
                }
            } elseif ($tod === WalkService::TIMEOFDAY_AFTERNOON) {
                $item['afternoon']++;
                if ($min) {
                    $item['afternoon_ok']++;
                }
            } elseif ($tod === WalkService::TIMEOFDAY_EVENING) {
                $item['evening']++;
                if ($min) {
                    $item['evening_ok']++;
                }
            }

            if ($this->walkService->userWalking($round)) {
                $item['walking']++;
            }

            if ($this->walkService->inPast($round)) {
                if ($this->walkService->wasWalked($round)) {
                    $item['walked']++;
                    if ($this->walkService->hasResult($round)) {
                        $item['result']++;
                        if ($this->walkService->hasIncident($round)) {
                            $item['incident']++;
                        }
                    }
                }
            }

            $dates[$date] = $item;
        }

        return $dates;
    }
}
