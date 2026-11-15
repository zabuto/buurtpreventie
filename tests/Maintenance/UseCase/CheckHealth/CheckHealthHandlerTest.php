<?php

declare(strict_types=1);

namespace App\Tests\Maintenance\UseCase\CheckHealth;

use App\Maintenance\UseCase\CheckHealth\CheckHealth;
use App\Maintenance\UseCase\CheckHealth\CheckHealthHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckHealthHandlerTest extends TestCase
{
    #[Test]
    public function it_reports_ok_while_the_application_runs(): void
    {
        $output = new CheckHealthHandler()(new CheckHealth());

        self::assertSame('ok', $output->status);
    }
}
