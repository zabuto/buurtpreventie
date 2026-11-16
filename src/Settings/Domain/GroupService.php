<?php

declare(strict_types=1);

namespace App\Settings\Domain;

/**
 * Where the group of an association lives; the frontend shows the name and icon of the service.
 */
enum GroupService: string
{
    case WhatsApp = 'whatsapp';
    case Signal = 'signal';
}
