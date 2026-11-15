<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain;

use App\Patrol\Domain\RoundComment;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RoundCommentTest extends TestCase
{
    #[Test]
    public function it_keeps_its_author_and_trimmed_text(): void
    {
        $author = Uuid::v7();
        $round = RoundFixture::withWalkers($author, 1);

        $comment = RoundComment::post($round, $author, ' Ik neem een zaklamp mee. ', new DateTimeImmutable(RoundFixture::CREATED_AT));

        $this->assertEquals($author, $comment->authorId());
        $this->assertSame('Ik neem een zaklamp mee.', $comment->text());
    }

    #[Test]
    public function it_refuses_a_blank_comment(): void
    {
        $author = Uuid::v7();
        $round = RoundFixture::withWalkers($author, 1);

        $this->expectException(InvalidArgumentException::class);

        RoundComment::post($round, $author, " \n ", new DateTimeImmutable(RoundFixture::CREATED_AT));
    }
}
