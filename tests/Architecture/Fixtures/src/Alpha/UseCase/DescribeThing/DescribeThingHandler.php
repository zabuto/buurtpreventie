<?php

declare(strict_types=1);

namespace App\Alpha\UseCase\DescribeThing;

final class DescribeThingHandler
{
    public function __invoke(DescribeThing $input): DescribeThing
    {
        return $input;
    }
}
