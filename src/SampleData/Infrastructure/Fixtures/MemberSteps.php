<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use App\Identity\UseCase\CreateApiToken\CreateApiToken;
use App\Identity\UseCase\CreateApiToken\CreateApiTokenHandler;
use App\Identity\UseCase\CreateApiToken\CreateApiTokenOutput;
use App\Identity\UseCase\SetPassword\SetPassword;
use App\Identity\UseCase\SetPassword\SetPasswordHandler;
use App\Membership\UseCase\AddFirstAdmin\AddFirstAdmin;
use App\Membership\UseCase\AddFirstAdmin\AddFirstAdminHandler;
use App\Membership\UseCase\AddFirstAdmin\AddFirstAdminOutput;
use App\Membership\UseCase\AddMember\AddMember;
use App\Membership\UseCase\AddMember\AddMemberHandler;
use App\Membership\UseCase\AddMember\AddMemberOutput;
use App\Membership\UseCase\ChangeContactConsent\ChangeContactConsent;
use App\Membership\UseCase\ChangeContactConsent\ChangeContactConsentHandler;
use App\Membership\UseCase\ChangeMember\ChangeMember;
use App\Membership\UseCase\ChangeMember\ChangeMemberHandler;
use App\Membership\UseCase\ChangeOpenRoundNotifications\ChangeOpenRoundNotifications;
use App\Membership\UseCase\ChangeOpenRoundNotifications\ChangeOpenRoundNotificationsHandler;
use App\Membership\UseCase\DeactivateMember\DeactivateMember;
use App\Membership\UseCase\DeactivateMember\DeactivateMemberHandler;
use App\Membership\UseCase\EraseMember\EraseMember;
use App\Membership\UseCase\EraseMember\EraseMemberHandler;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Role;
use App\Shared\Infrastructure\Security\RunAs;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Uid\Uuid;

/**
 * Membership and credentials through their use cases, each run by whoever would run it: the system adds the first
 * admin, the admin adds and manages members, and members make their own choices.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
final readonly class MemberSteps
{
    public function __construct(
        private Timeline $timeline,
        private RunAs $runAs,
        private AddFirstAdminHandler $addFirstAdmin,
        private AddMemberHandler $addMember,
        private ChangeContactConsentHandler $changeContactConsent,
        private ChangeMemberHandler $changeMember,
        private ChangeOpenRoundNotificationsHandler $changeOpenRoundNotifications,
        private DeactivateMemberHandler $deactivateMember,
        private EraseMemberHandler $eraseMember,
        private CreateApiTokenHandler $giveApiAccess,
        private SetPasswordHandler $giveLogin,
    ) {
    }

    public function firstAdmin(DateTimeImmutable $at, SampleProfile $profile): SampleMember
    {
        $input = new AddFirstAdmin($profile->email, $profile->name, $profile->displayName, $profile->phone);
        $output = $this->timeline->at($at, fn(): mixed => $this->runAs->system(Channel::Console, fn(): AddFirstAdminOutput => ($this->addFirstAdmin)(
            $input,
        )));

        $admin = new SampleMember($output->id, new Actor(Uuid::fromString($output->id), [Role::Admin]));
        $this->giveSamplePassword($admin, $at);

        return $admin;
    }

    public function add(SampleMember $admin, DateTimeImmutable $at, SampleProfile $profile): SampleMember
    {
        $input = new AddMember(
            email: $profile->email,
            name: $profile->name,
            displayName: $profile->displayName,
            phone: $profile->phone,
            roles: \array_map(static fn(Role $role): string => $role->value, $profile->roles),
        );
        $output = $this->as($admin, $at, fn(): AddMemberOutput => ($this->addMember)($input));

        return new SampleMember($output->id, new Actor(Uuid::fromString($output->id), $profile->roles));
    }

    public function consentToContactShare(SampleMember $member, DateTimeImmutable $at): void
    {
        $this->as($member, $at, fn() => ($this->changeContactConsent)(new ChangeContactConsent($member->id, consent: true)));
    }

    public function notifyAboutOpenRounds(SampleMember $member, DateTimeImmutable $at): void
    {
        $this->as($member, $at, fn() => ($this->changeOpenRoundNotifications)(new ChangeOpenRoundNotifications($member->id, notify: true)));
    }

    public function allowMcp(SampleMember $admin, SampleMember $member, DateTimeImmutable $at): void
    {
        $this->as($admin, $at, fn() => ($this->changeMember)(new ChangeMember($member->id, mcpAllowed: true)));
    }

    public function deactivate(SampleMember $admin, SampleMember $member, DateTimeImmutable $at): void
    {
        $this->as($admin, $at, fn() => ($this->deactivateMember)(new DeactivateMember($member->id)));
    }

    public function erase(SampleMember $admin, SampleMember $member, DateTimeImmutable $at): void
    {
        $this->as($admin, $at, fn() => ($this->eraseMember)(new EraseMember($member->id)));
    }

    /**
     * @param list<string> $scopes
     *
     * @return string the secret, which the use case shows only here
     */
    public function giveApiAccess(SampleMember $member, DateTimeImmutable $at, string $name, array $scopes, DateTimeImmutable $expiresAt): string
    {
        return $this->as($member, $at, fn(): CreateApiTokenOutput => ($this->giveApiAccess)(
            new CreateApiToken($name, $scopes, $expiresAt->format(\DATE_ATOM), $member->id),
        ))->token;
    }

    /**
     * The password of SamplePassword, set by the member as after a login with a code. Only a few members get it:
     * every hash costs the real hasher half a second.
     */
    public function giveSamplePassword(SampleMember $member, DateTimeImmutable $at): void
    {
        $this->as($member, $at, fn() => ($this->giveLogin)(new SetPassword(SamplePassword::VALUE)));
    }

    /**
     * @template T
     *
     * @param callable(): T $step
     *
     * @return T
     */
    private function as(SampleMember $member, DateTimeImmutable $at, callable $step): mixed
    {
        return $this->timeline->at($at, fn(): mixed => $this->runAs->member($member->actor, Channel::Console, $step));
    }
}
