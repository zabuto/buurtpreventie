<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeMember;

use App\Membership\Domain\Member;
use App\Membership\Domain\MemberDetails;
use App\Membership\Domain\MemberRepository;
use App\Membership\Domain\PhoneNumber;
use App\Membership\Domain\UniqueMemberDetails;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\Transaction;
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

    public function __invoke(ChangeMember $input): void
    {
        $details = new MemberDetails(
            EmailAddress::fromString($input->email),
            $input->name,
            $input->displayName,
            null === $input->phone ? null : PhoneNumber::fromString($input->phone),
            $input->address,
        );

        $this->transaction->run(function () use ($input, $details): void {
            $member = $this->members->get(Uuid::fromString($input->memberId));
            $this->authorization->denyUnlessGranted('member.change', $member);
            $this->uniqueMemberDetails->ensureAvailable($details, $member->id());

            $before = self::snapshot($member);
            $member->change($details);
            $member->certify($input->certified);
            $this->members->update($member);

            $changes = self::changes($before, self::snapshot($member));
            if ([] !== $changes) {
                $this->auditTrail->record(new AuditedAction('member.changed', 'member', $member->id(), $changes));
            }
        });
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
