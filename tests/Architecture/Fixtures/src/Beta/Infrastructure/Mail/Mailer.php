<?php

declare(strict_types=1);

namespace App\Beta\Infrastructure\Mail;

use Twig\Environment;

/** Complies: e-mail infrastructure may render Twig. */
final class Mailer
{
    public function __construct(public Environment $twig)
    {
    }
}
