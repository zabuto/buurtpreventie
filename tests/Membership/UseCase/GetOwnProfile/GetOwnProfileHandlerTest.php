<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\GetOwnProfile;

use App\Membership\UseCase\GetOwnProfile\GetOwnProfile;
use App\Membership\UseCase\GetOwnProfile\GetOwnProfileHandler;
use App\Membership\UseCase\GetOwnProfile\GetOwnProfileOutput;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\FixedExecutionContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetOwnProfileHandlerTest extends TestCase
{
    #[Test]
    public function it_shows_the_member_acting_their_whole_profile(): void
    {
        $members = new InMemoryMemberRepository();
        $jan = MemberFixture::stored($members, 'jan@example.org', 'Jan Bakker', 'Jan B.', [Role::Walker, Role::Coordinator]);
        $jan->changeContactConsent(true);
        MemberFixture::stored($members, 'piet@example.org', 'Piet de Vries', 'Piet V.');

        $output = new GetOwnProfileHandler($members, new FixedExecutionContext(new Actor($jan->id(), [])))(new GetOwnProfile());

        $this->assertEquals(
            new GetOwnProfileOutput(
                id: $jan->id()->toRfc4122(),
                email: 'jan@example.org',
                name: 'Jan Bakker',
                displayName: 'Jan B.',
                phone: '06 1234 5678',
                address: 'Dorpsstraat 1',
                roles: ['walker', 'coordinator'],
                consentContactShare: true,
                notifyOpenRounds: false,
                certified: false,
            ),
            $output,
        );
    }

    #[Test]
    public function it_refuses_when_nobody_acts(): void
    {
        $this->expectException(AccessDenied::class);

        new GetOwnProfileHandler(new InMemoryMemberRepository(), new FixedExecutionContext())(new GetOwnProfile());
    }
}
