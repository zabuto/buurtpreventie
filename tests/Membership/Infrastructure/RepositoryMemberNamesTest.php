<?php

declare(strict_types=1);

namespace App\Tests\Membership\Infrastructure;

use App\Membership\Infrastructure\RepositoryMemberNames;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RepositoryMemberNamesTest extends TestCase
{
    #[Test]
    public function it_gives_the_display_name_of_a_member(): void
    {
        $members = new InMemoryMemberRepository();
        $jan = MemberFixture::stored($members, 'jan@example.org', 'Jan Bakker', 'Jan B.');

        $this->assertSame('Jan B.', new RepositoryMemberNames($members)->displayNameOf($jan->id()));
    }

    #[Test]
    public function it_gives_no_name_for_an_erased_member(): void
    {
        $members = new InMemoryMemberRepository();
        $jan = MemberFixture::stored($members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $jan->erase(new DateTimeImmutable('2026-11-15T12:00:00+00:00'));

        $this->assertNull(new RepositoryMemberNames($members)->displayNameOf($jan->id()));
    }

    #[Test]
    public function it_gives_no_name_for_an_unknown_member(): void
    {
        $this->assertNull(new RepositoryMemberNames(new InMemoryMemberRepository())->displayNameOf(Uuid::v7()));
    }
}
