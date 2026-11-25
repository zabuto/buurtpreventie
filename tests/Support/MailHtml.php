<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Checks on the HTML part of a mail that no mail test would see by its text alone.
 */
final readonly class MailHtml
{
    /**
     * CSS that several mail programs drop or change: a margin, the background shorthand, rounded corners, display,
     * font-weight, letter-spacing or text-decoration. Spacing goes on table cells as padding, bold text in strong.
     */
    public static function assertOnlyRobustCss(string $html): void
    {
        \preg_match_all('/(?<![\w-])(margin|background|border-radius|display|font-weight|letter-spacing|text-decoration)\s*:/', $html, $found);

        Assert::assertSame([], \array_values(\array_unique($found[1])), 'Fragile CSS in the HTML of the mail.');
    }
}
