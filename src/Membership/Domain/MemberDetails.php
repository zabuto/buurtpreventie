<?php

declare(strict_types=1);

namespace App\Membership\Domain;

use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\PhoneNumber;

/**
 * Who a member is and how to reach them. The e-mail address serves both mail and login.
 */
final readonly class MemberDetails
{
    public string $name;
    public string $displayName;
    public ?string $address;

    public function __construct(
        public EmailAddress $email,
        string $name,
        string $displayName,
        public ?PhoneNumber $phone = null,
        ?string $address = null,
    ) {
        $address = \trim($address ?? '');

        $this->name = \trim($name);
        $this->displayName = \trim($displayName);
        $this->address = '' === $address ? null : $address;
    }
}
