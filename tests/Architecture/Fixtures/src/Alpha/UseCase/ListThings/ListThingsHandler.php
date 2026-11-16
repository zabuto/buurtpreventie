<?php

declare(strict_types=1);

namespace App\Alpha\UseCase\ListThings;

final class ListThingsHandler
{
    public function __invoke(ListThings $input): ListThings
    {
        return $input;
    }
}
