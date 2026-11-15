<?php

declare(strict_types=1);

namespace App\Membership\Domain;

use App\Shared\Domain\LookupKey;
use Symfony\Component\Uid\Uuid;

/**
 * Refuses an e-mail address or display name another member already has. The unique indexes catch two requests at
 * the same moment; this gives the common case its own error before anything is written.
 */
final readonly class UniqueMemberDetails
{
    public function __construct(
        private MemberRepository $members,
    ) {
    }

    /**
     * @param Uuid|null $memberId the member taking the details, whose own address and name are no conflict
     *
     * @throws EmailAddressTaken
     * @throws DisplayNameTaken
     */
    public function ensureAvailable(MemberDetails $details, ?Uuid $memberId = null): void
    {
        if ($this->members->isEmailAddressTaken($details->email, except: $memberId)) {
            throw EmailAddressTaken::forAddress($details->email);
        }

        if ($this->members->isDisplayNameTaken(LookupKey::fromName($details->displayName), except: $memberId)) {
            throw DisplayNameTaken::forName($details->displayName);
        }
    }
}
