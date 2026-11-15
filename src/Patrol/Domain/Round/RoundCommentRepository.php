<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use App\Shared\Domain\NotFound;
use Symfony\Component\Uid\Uuid;

interface RoundCommentRepository
{
    /**
     * @throws NotFound
     */
    public function get(Uuid $id): RoundComment;

    /**
     * Stores the round comment when the running transaction commits.
     */
    public function add(RoundComment $roundComment): void;

    public function remove(RoundComment $roundComment): void;

    /**
     * @return list<RoundComment> oldest first
     */
    public function forRound(Round $round): array;
}
