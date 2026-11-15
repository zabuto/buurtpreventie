<?php

declare(strict_types=1);

namespace App\Tests\Maintenance\UseCase\CheckHealth;

use App\Maintenance\UseCase\CheckHealth\CheckHealth;
use App\Maintenance\UseCase\CheckHealth\CheckHealthHandler;
use App\Maintenance\UseCase\CheckHealth\CheckHealthTool;
use PHPUnit\Framework\TestCase;

final class CheckHealthToolTest extends TestCase
{
    public function test_returns_the_handler_output_unchanged(): void
    {
        $handler = new CheckHealthHandler();

        self::assertEquals($handler(new CheckHealth()), (new CheckHealthTool($handler))(new CheckHealth()));
    }
}
