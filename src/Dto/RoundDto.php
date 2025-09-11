<?php declare(strict_types=1);

namespace App\Dto;

final readonly class RoundDto
{
    public function __construct(
        public int     $id,
        public string  $date,
        public string  $time,
        public string  $time_of_day,
        public ?string $meetingpoint = null,
        public bool    $minimum = false,
        /** @var  array<int, WalkerDto> */
        public array   $walkers = [],
    )
    {
    }
}
