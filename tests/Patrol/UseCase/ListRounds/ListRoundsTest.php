<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ListRounds;

use App\Patrol\Domain\RoundStatus;
use App\Patrol\UseCase\ListRounds\ListRounds;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/**
 * The status filter lists the values written out, as an attribute takes no enum call; a status added to the enum
 * must be added there too.
 */
final class ListRoundsTest extends TestCase
{
    #[Test]
    public function every_round_status_is_a_valid_filter(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $refused = [];
        foreach (RoundStatus::cases() as $status) {
            if (0 !== \count($validator->validate(new ListRounds('2026-11-14T00:00:00+01:00', '2026-11-17T00:00:00+01:00', $status->value)))) {
                $refused[] = $status->value;
            }
        }

        $this->assertSame([], $refused);
    }
}
