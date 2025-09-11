<?php declare(strict_types=1);

namespace App\Service;

use App\Dto\Formatter\DateTimeFormatter;
use App\Dto\RoundDto;
use App\Dto\WalkerDto;
use App\Entity\Round;
use App\Model\WalkerSingleDayModel;
use App\Repository\RoundRepository;
use DateTime;
use Exception;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

readonly class CalendarService
{
    public function __construct(
        private WalkService           $walkService,
        private ObjectMapperInterface $mapper,
        private RoundRepository       $roundRepo,
    )
    {
    }

    /**
     * @throws Exception
     */
    public function getMonth(int $year, int $month): array
    {
        $rounds = $this->roundRepo->getRoundsForMonth($year, $month);
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
                'date' => $date,
                'classname' => 'tod-' . implode('-', $parts),
                'markup' => $markup,
            ];
        }

        return $data;
    }

    /**
     * @return WalkerSingleDayModel[]
     */
    public function getWalksForDateReminders(DateTime $date): array
    {
        $rounds = $this->roundRepo->getRoundsForDate($date);

        $walksForDate = [];
        foreach ($rounds as $round) {
            $roundDto = $this->mapper->map($round, RoundDto::class);

            foreach ($round->getWalkers() as $roundWalker) {
                if ($roundWalker->wasReminded() || null === $roundWalker->getWalker()) {
                    continue;
                }

                $walkerDto = $this->mapper->map($roundWalker, WalkerDto::class);
                $key = sprintf('%s|%s', $roundWalker->getWalker()->getId(), $roundDto->date);
                if (array_key_exists($key, $walksForDate)) {
                    $walkerSingleDay = $walksForDate[$key];
                } else {
                    $walkerSingleDay = new WalkerSingleDayModel(
                        walker: $walkerDto,
                        datetime: $round->getDatetime(),
                    );
                }

                $walkerSingleDay->addRound($roundDto);
                $walkerSingleDay->addRoundWalkerId($roundWalker->getId());

                $walksForDate[$key] = $walkerSingleDay;
            }
        }

        return array_values($walksForDate);
    }

    /**
     * @param  Round[] $rounds
     * @return array
     * @throws Exception
     */
    private function getDates(array $rounds): array
    {
        $dates = [];
        foreach ($rounds as $round) {
            $date = $round->getDatetime()?->format('Y-m-d');
            $tod = $this->walkService->getTimeOfDay($round);
            $min = $this->walkService->hasMinimumWalkers($round);

            if (!array_key_exists($date, $dates)) {
                $item = [
                    'walking' => 0,
                    'walked' => 0,
                    'result' => 0,
                    'incident' => 0,
                    'morning' => 0,
                    'morning_ok' => 0,
                    'afternoon' => 0,
                    'afternoon_ok' => 0,
                    'evening' => 0,
                    'evening_ok' => 0,
                ];
            } else {
                $item = $dates[$date];
            }

            if ($tod === DateTimeFormatter::TIMEOFDAY_MORNING) {
                $item['morning']++;
                if ($min) {
                    $item['morning_ok']++;
                }
            } elseif ($tod === DateTimeFormatter::TIMEOFDAY_AFTERNOON) {
                $item['afternoon']++;
                if ($min) {
                    $item['afternoon_ok']++;
                }
            } elseif ($tod === DateTimeFormatter::TIMEOFDAY_EVENING) {
                $item['evening']++;
                if ($min) {
                    $item['evening_ok']++;
                }
            }

            if ($this->walkService->userWalking($round)) {
                $item['walking']++;
            }

            if ($this->walkService->inPast($round) && $this->walkService->wasWalked($round)) {
                $item['walked']++;
                if ($this->walkService->hasResult($round)) {
                    $item['result']++;
                    if ($this->walkService->hasIncident($round)) {
                        $item['incident']++;
                    }
                }
            }

            $dates[$date] = $item;
        }

        return $dates;
    }
}
