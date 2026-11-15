<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Fake;

use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundComment;
use App\Patrol\Domain\RoundCommentRepository;
use App\Shared\Domain\NotFound;
use Override;
use Symfony\Component\Uid\Uuid;

final class InMemoryRoundCommentRepository implements RoundCommentRepository
{
    /** @var array<string, RoundComment> */
    private array $comments = [];

    #[Override]
    public function get(Uuid $id): RoundComment
    {
        return $this->comments[$id->toRfc4122()] ?? throw NotFound::forId('round comment', $id);
    }

    #[Override]
    public function add(RoundComment $roundComment): void
    {
        $this->comments[$roundComment->id()->toRfc4122()] = $roundComment;
    }

    #[Override]
    public function remove(RoundComment $roundComment): void
    {
        unset($this->comments[$roundComment->id()->toRfc4122()]);
    }

    #[Override]
    public function forRound(Round $round): array
    {
        $found = \array_values(\array_filter(
            $this->comments,
            static fn(RoundComment $comment): bool => $comment->round() === $round,
        ));
        \usort($found, static fn(RoundComment $a, RoundComment $b): int => $a->postedAt() <=> $b->postedAt());

        return $found;
    }
}
