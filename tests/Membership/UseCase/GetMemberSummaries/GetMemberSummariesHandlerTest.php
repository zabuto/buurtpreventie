<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\GetMemberSummaries;

use App\Membership\UseCase\GetMemberSummaries\GetMemberSummaries;
use App\Membership\UseCase\GetMemberSummaries\GetMemberSummariesHandler;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedExecutionContext;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class GetMemberSummariesHandlerTest extends TestCase
{
    private InMemoryMemberRepository $members;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
    }

    /**
     * @return iterable<string, array{Role, bool}>
     */
    public static function rolesAndFullNames(): iterable
    {
        yield 'walker' => [Role::Walker, false];
        yield 'analyst' => [Role::Analyst, false];
        yield 'coordinator' => [Role::Coordinator, true];
        yield 'admin' => [Role::Admin, true];
    }

    #[DataProvider('rolesAndFullNames')]
    #[Test]
    public function it_shows_the_full_name_only_to_a_coordinator_or_admin(Role $role, bool $seesFullName): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');

        $output = $this->handler(new Actor(Uuid::v7(), [$role]))(new GetMemberSummaries());

        $expected = ['id' => $jan->id()->toRfc4122(), 'displayName' => 'Jan B.', 'active' => true];
        $this->assertSame([$seesFullName ? [...$expected, 'name' => 'Jan Bakker'] : $expected], $output->members);
    }

    #[Test]
    public function it_finds_members_by_part_of_their_name_or_display_name(): void
    {
        MemberFixture::stored($this->members, 'jose@example.org', 'José Jansen', 'José J.');
        MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        MemberFixture::stored($this->members, 'piet@example.org', 'Piet de Vries', 'Piet V.');

        $output = $this->handler(new Actor(Uuid::v7(), [Role::Walker]))(new GetMemberSummaries(search: 'JAN'));

        $this->assertSame(['Jan B.', 'José J.'], \array_column($output->members, 'displayName'));
    }

    #[Test]
    public function it_leaves_erased_members_out(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $jan->erase(new DateTimeImmutable('2026-11-15T12:00:00+00:00'));

        $output = $this->handler(new Actor(Uuid::v7(), [Role::Admin]))(new GetMemberSummaries());

        $this->assertSame([], $output->members);
    }

    private function handler(Actor $actor): GetMemberSummariesHandler
    {
        return new GetMemberSummariesHandler($this->members, FakeAuthorization::granting(), new FixedExecutionContext($actor));
    }
}
