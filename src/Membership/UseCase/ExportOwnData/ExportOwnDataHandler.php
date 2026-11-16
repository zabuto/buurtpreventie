<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ExportOwnData;

use App\Audit\UseCase\ListOwnActivity\ListOwnActivity;
use App\Audit\UseCase\ListOwnActivity\ListOwnActivityHandler;
use App\Identity\UseCase\ListApiTokens\ListApiTokens;
use App\Identity\UseCase\ListApiTokens\ListApiTokensHandler;
use App\Membership\UseCase\GetOwnProfile\GetOwnProfile;
use App\Membership\UseCase\GetOwnProfile\GetOwnProfileHandler;
use App\Patrol\UseCase\ExportOwnPatrolData\ExportOwnPatrolData;
use App\Patrol\UseCase\ExportOwnPatrolData\ExportOwnPatrolDataHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\ReadAction;
use Psr\Clock\ClockInterface;

/**
 * Gathers the own data of the member acting from every module, through their use cases, each of which reads only
 * that member's data.
 */
#[ReadAction]
final readonly class ExportOwnDataHandler
{
    public function __construct(
        private GetOwnProfileHandler $profile,
        private ExportOwnPatrolDataHandler $patrol,
        private ListApiTokensHandler $tokens,
        private ListOwnActivityHandler $activity,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws AccessDenied when nobody acts
     */
    public function __invoke(ExportOwnData $input): ExportOwnDataOutput
    {
        $profile = ($this->profile)(new GetOwnProfile());

        $activity = [];
        $page = 1;
        do {
            $output = ($this->activity)(new ListOwnActivity($page));
            $activity = [...$activity, ...$output->entries];
            $page = $output->nextPage;
        } while (null !== $page);

        return new ExportOwnDataOutput(
            LocalTime::of($this->clock->now())->format(\DATE_ATOM),
            $profile,
            ($this->patrol)(new ExportOwnPatrolData()),
            ($this->tokens)(new ListApiTokens()),
            $activity,
        );
    }
}
