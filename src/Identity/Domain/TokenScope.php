<?php

declare(strict_types=1);

namespace App\Identity\Domain;

enum TokenScope: string
{
    case Read = 'read';
    case Write = 'write';
    case Admin = 'admin';
}
