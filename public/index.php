<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context): Kernel {
    /** @var array{APP_ENV: string, APP_DEBUG: bool|string} $runtime Symfony Runtime always sets both keys */
    $runtime = $context;

    return new Kernel($runtime['APP_ENV'], (bool) $runtime['APP_DEBUG']);
};
