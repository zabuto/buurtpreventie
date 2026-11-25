<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\GetMember;

use App\Membership\UseCase\GetMember\GetMember;
use App\Membership\UseCase\GetMember\GetMemberHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\NotFound;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\FakeAuthorization;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class GetMemberHandlerTest extends TestCase
{
    private InMemoryMemberRepository $members;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
    }

    #[Test]
    public function it_shows_the_member_with_everything_the_admin_manages(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $jan->deactivate();
        $jan->allowMcp(true);

        $output = $this->handler()(new GetMember($jan->id()->toRfc4122()));

        $this->assertSame(
            [$jan->id()->toRfc4122(), 'jan@example.org', 'Jan Bakker', '06 1234 5678', ['walker'], false, true],
            [$output->id, $output->email, $output->name, $output->phone, $output->roles, $output->active, $output->mcpAllowed],
        );
    }

    #[Test]
    public function it_refuses_an_unknown_member(): void
    {
        $this->expectException(NotFound::class);

        $this->handler()(new GetMember(Uuid::v7()->toRfc4122()));
    }

    #[Test]
    public function it_refuses_whoever_may_not_view_every_member(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $authorization = FakeAuthorization::refusing();

        try {
            $this->handler($authorization)(new GetMember($jan->id()->toRfc4122()));
            $this->fail('The member was shown.');
        } catch (AccessDenied) {
            $this->assertSame([['member.view', null]], $authorization->asked);
        }
    }

    private function handler(?FakeAuthorization $authorization = null): GetMemberHandler
    {
        return new GetMemberHandler($this->members, $authorization ?? FakeAuthorization::granting());
    }
}
