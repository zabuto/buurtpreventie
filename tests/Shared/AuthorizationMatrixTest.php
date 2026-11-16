<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Audit\Domain\AuditPolicy;
use App\Audit\Infrastructure\Security\AuditVoter;
use App\Identity\Domain\CredentialPolicy;
use App\Identity\Infrastructure\Security\AccountVoter;
use App\Identity\Infrastructure\Security\ApiTokenVoter;
use App\Membership\Domain\MemberPolicy;
use App\Membership\Infrastructure\Security\MemberVoter;
use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\ReportReadingPolicy;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundComment;
use App\Patrol\Domain\Round\RoundPolicy;
use App\Patrol\Infrastructure\Security\CatalogueVoter;
use App\Patrol\Infrastructure\Security\CommentVoter;
use App\Patrol\Infrastructure\Security\ParticipationVoter;
use App\Patrol\Infrastructure\Security\ReportVoter;
use App\Patrol\Infrastructure\Security\RoundVoter;
use App\Settings\Domain\SettingsPolicy;
use App\Settings\Infrastructure\Security\SettingsVoter;
use App\Shared\Domain\Role;
use App\Shared\Infrastructure\Security\MemberUser;
use App\Shared\Infrastructure\Security\SystemUser;
use App\Tests\Identity\Fake\ApiTokenFixture;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Patrol\Domain\Round\RoundFixture;
use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Uid\Uuid;

/**
 * Every authorization attribute against every kind of principal: nobody signed in, the system, and a member with
 * no role or exactly one role. Roles add up, so one role per column shows what each role grants on its own.
 *
 * Where a right depends on the actor's relation to the subject (oneself, the author, a walker of the round), a row
 * per relation shows it; the relation rules themselves are tested in the policy tests.
 */
final class AuthorizationMatrixTest extends TestCase
{
    private const array MEMBERS = ['no role', 'walker', 'coordinator', 'analyst', 'admin'];

    /**
     * @return iterable<string, array{string, (Closure(Uuid): mixed)|string, list<string>}>
     *     attribute, the subject for an actor with this id (or member:self or member:other for a stored member),
     *     and the principals granted
     */
    public static function rows(): iterable
    {
        yield from self::patrolRows();
        yield from self::memberRows();
        yield from self::credentialRows();
        yield 'audit.erase_actor_name' => ['audit.erase_actor_name', static fn(Uuid $actor): Uuid => Uuid::v7(), ['admin']];
        yield 'audit.view_round_history' => ['audit.view_round_history', static fn(Uuid $actor): Uuid => Uuid::v7(), ['coordinator']];
        yield 'audit.view' => ['audit.view', static fn(Uuid $actor): null => null, ['admin']];
        yield 'settings.view' => ['settings.view', static fn(Uuid $actor): null => null, ['admin']];
        yield 'settings.change' => ['settings.change', static fn(Uuid $actor): null => null, ['admin']];
    }

    /**
     * @return iterable<string, array{string, Closure(Uuid): mixed, list<string>}>
     */
    private static function patrolRows(): iterable
    {
        $stranger = static fn(Uuid $actor): Uuid => Uuid::v7();
        $self = static fn(Uuid $actor): Uuid => $actor;
        $roundOfOthers = static fn(Uuid $actor): Round => RoundFixture::withWalkers(Uuid::v7(), 2);
        $roundWalkedAlone = static fn(Uuid $actor): Round => RoundFixture::withWalkers($actor, 1);
        $roundWalkedWithBuddy = self::roundWith(...);
        $nobody = static fn(Uuid $actor): null => null;

        yield 'round.create for oneself' => ['round.create', $self, ['walker']];
        yield 'round.create on behalf' => ['round.create', $stranger, ['coordinator']];
        yield 'round.change of others' => ['round.change', $roundOfOthers, ['coordinator']];
        yield 'round.change by the owner walking alone' => ['round.change', $roundWalkedAlone, self::MEMBERS];
        yield 'round.delete of others' => ['round.delete', $roundOfOthers, ['coordinator']];
        yield 'round.delete by the owner walking alone' => ['round.delete', $roundWalkedAlone, self::MEMBERS];
        yield 'round.cancel of others' => ['round.cancel', $roundOfOthers, ['coordinator']];
        yield 'round.cancel by a walker of the round' => ['round.cancel', $roundWalkedWithBuddy, self::MEMBERS];
        yield 'round.list' => ['round.list', $nobody, [...self::MEMBERS, 'system']];
        yield 'round.view' => ['round.view', $roundOfOthers, [...self::MEMBERS, 'system']];
        yield 'round.send_reminders' => ['round.send_reminders', $nobody, ['system']];
        yield 'round.notify_buddy_needed' => ['round.notify_buddy_needed', $nobody, ['system']];

        yield 'participation.sign_up of oneself' => ['participation.sign_up', $self, ['walker']];
        yield 'participation.sign_up on behalf' => ['participation.sign_up', $stranger, ['coordinator']];
        yield 'participation.withdraw of oneself' => ['participation.withdraw', $self, ['walker']];
        yield 'participation.withdraw of another walker' => ['participation.withdraw', $stranger, ['coordinator']];
        yield 'participation.withdraw_member_from_upcoming' => ['participation.withdraw_member_from_upcoming', $stranger, ['admin']];

        yield 'report.submit on a round of others' => ['report.submit', $roundOfOthers, ['coordinator']];
        yield 'report.submit by a walker of the round' => ['report.submit', $roundWalkedWithBuddy, self::MEMBERS];
        yield 'report.delete of another author' => ['report.delete', static fn(Uuid $actor): mixed => self::reportBy(Uuid::v7()), ['coordinator']];
        yield 'report.delete by its author' => ['report.delete', self::reportBy(...), self::MEMBERS];
        yield 'report.list' => ['report.list', $nobody, ['coordinator', 'analyst']];

        yield 'comment.add on a round of others' => ['comment.add', $roundOfOthers, ['coordinator']];
        yield 'comment.add by a walker of the round' => ['comment.add', $roundWalkedWithBuddy, self::MEMBERS];
        yield 'comment.delete of another author' => ['comment.delete', static fn(Uuid $actor): RoundComment => self::commentBy(Uuid::v7()), ['coordinator']];
        yield 'comment.delete by its author' => ['comment.delete', self::commentBy(...), self::MEMBERS];

        yield 'catalogue.manage' => ['catalogue.manage', $nobody, ['admin']];
        yield 'catalogue.list' => ['catalogue.list', $nobody, [...self::MEMBERS, 'system']];
    }

    /**
     * @return iterable<string, array{string, (Closure(Uuid): mixed)|string, list<string>}>
     */
    private static function memberRows(): iterable
    {
        $nobody = static fn(Uuid $actor): null => null;

        foreach (['member.add', 'member.change', 'member.change_roles', 'member.deactivate', 'member.reactivate', 'member.erase'] as $attribute) {
            yield $attribute => [$attribute, $nobody, ['admin']];
        }
        yield 'member.invite' => ['member.invite', $nobody, ['coordinator', 'admin']];
        yield 'member.add_first_admin' => ['member.add_first_admin', $nobody, ['system']];
        yield 'member.change_consent of oneself' => ['member.change_consent', 'member:self', self::MEMBERS];
        yield 'member.change_consent of another member' => ['member.change_consent', 'member:other', []];
        yield 'member.change_open_round_notifications of oneself' => ['member.change_open_round_notifications', 'member:self', self::MEMBERS];
        yield 'member.change_open_round_notifications of another member' => ['member.change_open_round_notifications', 'member:other', []];
        yield 'member.change_contact_details of oneself' => ['member.change_contact_details', 'member:self', self::MEMBERS];
        yield 'member.change_contact_details of another member' => ['member.change_contact_details', 'member:other', []];
        yield 'member.list' => ['member.list', $nobody, [...self::MEMBERS, 'system']];
        yield 'member.view' => ['member.view', $nobody, ['admin']];
    }

    /**
     * @return iterable<string, array{string, Closure(Uuid): mixed, list<string>}>
     */
    private static function credentialRows(): iterable
    {
        $stranger = static fn(Uuid $actor): Uuid => Uuid::v7();
        $self = static fn(Uuid $actor): Uuid => $actor;

        yield 'api_token.create for oneself' => ['api_token.create', $self, self::MEMBERS];
        yield 'api_token.create for another member' => ['api_token.create', $stranger, []];
        yield 'api_token.revoke of an own token' => ['api_token.revoke', self::tokenOf(...), self::MEMBERS];
        yield 'api_token.revoke of a token of another member' => ['api_token.revoke', static fn(Uuid $actor): mixed => self::tokenOf(Uuid::v7()), []];
        yield 'api_token.revoke_all of oneself' => ['api_token.revoke_all', $self, self::MEMBERS];
        yield 'api_token.revoke_all of another member' => ['api_token.revoke_all', $stranger, ['admin']];
        yield 'account.set_password of oneself' => ['account.set_password', $self, self::MEMBERS];
        yield 'account.set_password of another member' => ['account.set_password', $stranger, []];
        yield 'account.issue_invitation' => ['account.issue_invitation', $stranger, ['coordinator', 'admin']];
        yield 'account.request_login_code' => ['account.request_login_code', static fn(Uuid $actor): null => null, ['nobody', 'system', ...self::MEMBERS]];
    }

    /**
     * @return list<string>
     */
    public static function coveredAttributes(): array
    {
        $attributes = [];
        foreach (self::rows() as [$attribute]) {
            $attributes[] = $attribute;
        }

        return \array_values(\array_unique($attributes));
    }

    /**
     * @param (Closure(Uuid): mixed)|string $subject a closure, or member:self or member:other for a stored member
     * @param list<string> $granted
     */
    #[DataProvider('rows')]
    #[Test]
    public function every_principal_gets_exactly_its_rights(string $attribute, Closure|string $subject, array $granted): void
    {
        $expected = [];
        $decided = [];
        foreach (['nobody', 'system', ...self::MEMBERS] as $principal) {
            $expected[$principal] = \in_array($principal, $granted, strict: true);
            $decided[$principal] = $this->decide($principal, $attribute, $subject);
        }

        $this->assertSame($expected, $decided);
    }

    /**
     * @param (Closure(Uuid): mixed)|string $subject
     */
    private function decide(string $principal, string $attribute, Closure|string $subject): bool
    {
        $memberId = Uuid::v7();
        $resolved = $subject;
        if (\is_string($subject)) {
            $member = MemberFixture::stored(new InMemoryMemberRepository(), 'jan@example.org', 'Jan Bakker', 'Jan B.');
            $memberId = 'member:self' === $subject ? $member->id() : $memberId;
            $resolved = $member;
        }
        if ($resolved instanceof Closure) {
            $resolved = $resolved($memberId);
        }

        $tokenStorage = new TokenStorage();
        $user = match ($principal) {
            'nobody' => null,
            'system' => new SystemUser(),
            'no role' => new MemberUser($memberId, []),
            default => new MemberUser($memberId, [Role::from($principal)]),
        };
        if (null !== $user) {
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'test', $user->getRoles()));
        }

        return new AuthorizationChecker($tokenStorage, new AccessDecisionManager(self::voters()))->isGranted($attribute, $resolved);
    }

    /**
     * Every voter of the app, as the container tags them.
     *
     * @return list<\Symfony\Component\Security\Core\Authorization\Voter\VoterInterface>
     */
    public static function voters(): array
    {
        $roundPolicy = new RoundPolicy();
        $credentialPolicy = new CredentialPolicy();

        return [
            new RoundVoter($roundPolicy),
            new ParticipationVoter($roundPolicy),
            new ReportVoter($roundPolicy, new ReportReadingPolicy()),
            new CommentVoter($roundPolicy),
            new CatalogueVoter($roundPolicy),
            new MemberVoter(new MemberPolicy()),
            new ApiTokenVoter($credentialPolicy),
            new AccountVoter($credentialPolicy),
            new AuditVoter(new AuditPolicy()),
            new SettingsVoter(new SettingsPolicy()),
        ];
    }

    private static function roundWith(Uuid $walker): Round
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 1);
        $round->signUp($walker, new DateTimeImmutable(RoundFixture::CREATED_AT));

        return $round;
    }

    private static function reportBy(Uuid $author): mixed
    {
        return RoundFixture::withWalkers(Uuid::v7(), 2)->submitReport(
            $author,
            Outcome::create('Niets te melden', OutcomeKind::NothingToReport),
            null,
            new DateTimeImmutable(RoundFixture::AT_START),
        );
    }

    private static function commentBy(Uuid $author): RoundComment
    {
        return RoundComment::post(
            RoundFixture::withWalkers(Uuid::v7(), 1),
            $author,
            'Ik neem een zaklamp mee',
            new DateTimeImmutable(RoundFixture::CREATED_AT),
        );
    }

    private static function tokenOf(Uuid $memberId): mixed
    {
        return ApiTokenFixture::stored(new InMemoryApiTokenRepository(), $memberId);
    }
}
