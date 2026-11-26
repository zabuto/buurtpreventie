<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeMember;

use App\Membership\Domain\Member;
use App\Membership\Domain\MemberDetails;
use App\Membership\Domain\MemberErased;
use App\Membership\Domain\MemberRepository;
use App\Membership\Domain\UniqueMemberDetails;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\PhoneNumber;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\Unchanged;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'member.change', audit: 'member.changed')]
final readonly class ChangeMemberHandler
{
    /**
     * Fields recorded as changed without their values, so the audit log never outlives an erasure with them.
     */
    private const array CONTACT_DETAILS = ['email', 'name', 'phone', 'address'];

    public function __construct(
        private MemberRepository $members,
        private UniqueMemberDetails $uniqueMemberDetails,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws MemberErased
     */
    public function __invoke(ChangeMember $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $member = $this->members->get(Uuid::fromString($input->memberId));
            $this->authorization->denyUnlessGranted('member.change', $member);
            $details = self::details($member, $input);
            $this->uniqueMemberDetails->ensureAvailable($details, $member->id());

            $before = self::snapshot($member);
            $member->change($details);
            if (!$input->certified instanceof Unchanged) {
                $member->certify($input->certified);
            }

            if (!$input->mcpAllowed instanceof Unchanged) {
                $member->allowMcp($input->mcpAllowed);
            }
            $this->members->update($member);

            $changes = self::changes($before, self::snapshot($member));
            if ([] !== $changes) {
                $this->auditTrail->record(new AuditedAction('member.changed', 'member', $member->id(), $changes));
            }
        });
    }

    /**
     * The current details with the fields of the input that are not left out.
     *
     * @throws MemberErased
     */
    private static function details(Member $member, ChangeMember $input): MemberDetails
    {
        $email = $member->email();
        $name = $member->name();
        $displayName = $member->displayName();
        if (null === $email || null === $name || null === $displayName) {
            throw MemberErased::forMember($member->id());
        }

        return new MemberDetails(
            $input->email instanceof Unchanged ? $email : EmailAddress::fromString($input->email),
            $input->name instanceof Unchanged ? $name : $input->name,
            $input->displayName instanceof Unchanged ? $displayName : $input->displayName,
            self::phone($member, $input->phone),
            $input->address instanceof Unchanged ? $member->address() : $input->address,
        );
    }

    private static function phone(Member $member, string|Unchanged|null $phone): ?PhoneNumber
    {
        return match (true) {
            $phone instanceof Unchanged => $member->phone(),
            null === $phone => null,
            default => PhoneNumber::fromString($phone),
        };
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function snapshot(Member $member): array
    {
        return [
            'email' => $member->email()?->value,
            'name' => $member->name(),
            'displayName' => $member->displayName(),
            'phone' => $member->phone()?->value,
            'address' => $member->address(),
            'certified' => $member->isCertified(),
            'mcpAllowed' => $member->isMcpAllowed(),
        ];
    }

    /**
     * @param array<string, scalar|null> $before
     * @param array<string, scalar|null> $after
     *
     * @return array<string, array{old: scalar|null, new: scalar|null}>
     */
    private static function changes(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $field => $value) {
            if (($before[$field] ?? null) === $value) {
                continue;
            }

            $changes[$field] = \in_array($field, self::CONTACT_DETAILS, strict: true)
                ? ['old' => null, 'new' => null]
                : ['old' => $before[$field] ?? null, 'new' => $value];
        }

        return $changes;
    }
}
