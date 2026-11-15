<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * The fixed roles of a member. They do not inherit from each other: a member without a role can only read.
 */
enum Role: string
{
    use BackedEnumValues;

    case Walker = 'walker';
    case Coordinator = 'coordinator';
    case Analyst = 'analyst';
    case Admin = 'admin';
}
