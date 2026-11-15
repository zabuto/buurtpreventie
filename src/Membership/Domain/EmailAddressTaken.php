<?php

declare(strict_types=1);

namespace App\Membership\Domain;

use App\Shared\Domain\EmailAddress;
use DomainException;

final class EmailAddressTaken extends DomainException
{
    public static function forAddress(EmailAddress $email): self
    {
        return new self(\sprintf('Another member has the e-mail address %s.', $email->value));
    }
}
