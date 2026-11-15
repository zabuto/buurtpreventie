<?php

declare(strict_types=1);

namespace App\Beta\Infrastructure;

use Twig\Environment;

/** Violates the Twig rule: Twig outside e-mail and the installer. */
final class Report
{
    public function __construct(public Environment $twig)
    {
    }
}
