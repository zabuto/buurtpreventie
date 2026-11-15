<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\BackedEnumValues;

enum TokenScope: string
{
    use BackedEnumValues;

    case Read = 'read';
    case Write = 'write';
    case Admin = 'admin';
}
