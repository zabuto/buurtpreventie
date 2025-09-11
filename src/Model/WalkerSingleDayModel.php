<?php declare(strict_types=1);

namespace App\Model;

use App\Dto\RoundDto;
use App\Dto\WalkerDto;
use DateTimeImmutable;

final class WalkerSingleDayModel
{
    public function __construct(
        public WalkerDto         $walker,
        public DateTimeImmutable $datetime,
        /* @var RoundDto[] */
        public array             $rounds = [],
        /* @var int[] */
        public array             $walking_ids = [],
    )
    {
    }

    public function addRound(RoundDto $round): void
    {
        $this->rounds[] = $round;
    }

    public function addRoundWalkerId(int $id): void
    {
        $this->walking_ids[] = $id;
    }

    public function toArray(): array
    {
        return [
            'walker' => $this->walker,
            'datetime' => $this->datetime,
            'rounds' => $this->rounds,
            'walking_ids' => $this->walking_ids,
        ];
    }
}
