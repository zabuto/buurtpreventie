<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Infrastructure\Persistence\Concurrency;

use App\Patrol\UseCase\SignUpForRound\SignUpForRoundHandler;
use App\Patrol\UseCase\WithdrawFromRound\WithdrawFromRoundHandler;
use App\Shared\Infrastructure\Security\RunAs;

/**
 * The sign-up and withdrawal handlers as the container wires them, for the test and for the process it races.
 */
final readonly class ParticipationHandlers
{
    public function __construct(
        public SignUpForRoundHandler $signUp,
        public WithdrawFromRoundHandler $withdraw,
        public RunAs $runAs,
    ) {
    }
}
