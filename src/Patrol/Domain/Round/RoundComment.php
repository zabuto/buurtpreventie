<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * A comment on a round. The author is fixed: who may delete it never depends on who touched it last.
 */
#[ORM\Entity]
#[ORM\Index(name: 'round_comment_author', columns: ['author_id'])]
final readonly class RoundComment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    private function __construct(
        #[ORM\ManyToOne(targetEntity: Round::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Round $round,
        #[ORM\Column(type: 'uuid')]
        private Uuid $authorId,
        #[ORM\Column(type: 'text')]
        private string $text,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private DateTimeImmutable $postedAt,
    ) {
        $this->id = Uuid::v7();
    }

    /**
     * @throws InvalidArgumentException for a comment without text
     */
    public static function post(Round $round, Uuid $authorId, string $text, DateTimeImmutable $now): self
    {
        $text = \trim($text);
        if ('' === $text) {
            throw new InvalidArgumentException('A comment needs text.');
        }

        return new self($round, $authorId, $text, $now);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function round(): Round
    {
        return $this->round;
    }

    public function authorId(): Uuid
    {
        return $this->authorId;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function postedAt(): DateTimeImmutable
    {
        return $this->postedAt;
    }
}
