<?php

declare(strict_types=1);

namespace App\Tests\Audit\Domain;

use App\Audit\Domain\ChainLink;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChainLinkTest extends TestCase
{
    #[Test]
    public function it_starts_an_empty_chain_at_position_zero_with_a_hash_of_zero_bytes(): void
    {
        $start = ChainLink::start();

        $this->assertSame([0, \str_repeat("\0", 32)], [$start->position, $start->hash]);
    }

    #[Test]
    public function it_equals_a_link_with_the_same_position_and_hash_only(): void
    {
        $link = new ChainLink(3, \str_repeat('a', 32));

        $this->assertSame(
            [true, false, false],
            [
                $link->equals(new ChainLink(3, \str_repeat('a', 32))),
                $link->equals(new ChainLink(4, \str_repeat('a', 32))),
                $link->equals(new ChainLink(3, \str_repeat('b', 32))),
            ],
        );
    }

    #[Test]
    public function it_refuses_a_hash_that_is_not_32_bytes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ChainLink(1, \str_repeat('a', 31));
    }

    #[Test]
    public function it_refuses_a_negative_position(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ChainLink(-1, \str_repeat('a', 32));
    }
}
