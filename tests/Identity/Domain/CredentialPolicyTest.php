<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\CredentialPolicy;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use App\Tests\Identity\Fake\ApiTokenFixture;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Passwords and tokens belong to the member alone; an admin only revokes every token of a member, as part of
 * deactivating or erasing.
 */
final class CredentialPolicyTest extends TestCase
{
    #[Test]
    public function it_lets_a_member_create_a_token_and_set_a_password_for_themselves(): void
    {
        $jan = new Actor(Uuid::v7(), []);

        $this->assertSame([true, true], [
            new CredentialPolicy()->mayCreateToken($jan, $jan->memberId),
            new CredentialPolicy()->maySetPassword($jan, $jan->memberId),
        ]);
    }

    #[Test]
    public function it_refuses_an_admin_a_token_or_password_for_another_member(): void
    {
        $admin = new Actor(Uuid::v7(), [Role::Admin]);
        $jan = Uuid::v7();

        $this->assertSame([false, false], [new CredentialPolicy()->mayCreateToken($admin, $jan), new CredentialPolicy()->maySetPassword($admin, $jan)]);
    }

    #[Test]
    public function it_lets_only_the_owner_revoke_one_token(): void
    {
        $jan = new Actor(Uuid::v7(), []);
        $token = ApiTokenFixture::stored(new InMemoryApiTokenRepository(), $jan->memberId);

        $this->assertSame(
            [true, false],
            [new CredentialPolicy()->mayRevokeToken($jan, $token), new CredentialPolicy()->mayRevokeToken(new Actor(Uuid::v7(), [Role::Admin]), $token)],
        );
    }

    #[Test]
    public function it_lets_the_member_or_an_admin_revoke_every_token_of_a_member(): void
    {
        $jan = Uuid::v7();

        $this->assertSame(
            [true, true, false],
            [
                new CredentialPolicy()->mayRevokeAllTokens(new Actor($jan, []), $jan),
                new CredentialPolicy()->mayRevokeAllTokens(new Actor(Uuid::v7(), [Role::Admin]), $jan),
                new CredentialPolicy()->mayRevokeAllTokens(new Actor(Uuid::v7(), [Role::Coordinator]), $jan),
            ],
        );
    }

    /**
     * @return iterable<string, array{list<Role>, bool, bool}>
     */
    public static function inviters(): iterable
    {
        yield 'walker' => [[Role::Walker], false, false];
        yield 'analyst' => [[Role::Analyst], false, false];
        yield 'coordinator' => [[Role::Coordinator], true, false];
        yield 'admin' => [[Role::Admin], true, true];
    }

    /**
     * The coordinator may have a new invitation sent but never sees the link, which logs in as the member.
     *
     * @param list<Role> $roles
     */
    #[DataProvider('inviters')]
    #[Test]
    public function it_lets_a_coordinator_or_admin_invite_and_only_an_admin_see_the_link(array $roles, bool $mayInvite, bool $maySeeLink): void
    {
        $actor = new Actor(Uuid::v7(), $roles);

        $this->assertSame(
            [$mayInvite, $maySeeLink],
            [new CredentialPolicy()->mayInvite($actor), new CredentialPolicy()->maySeeInvitationLink($actor)],
        );
    }
}
