<?php

use App\Kernel;
use App\Command\Output\StringOutput;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

require __DIR__ . '/../env.php';
require __DIR__ . '/../src/.bootstrap.php';

echo '<h1>Setup</h1>';

$kernel = new Kernel($_SERVER['APP_ENV'], $_SERVER['APP_DEBUG']);

$application = new Application($kernel);
$application->setAutoExit(false);

$input = new ArrayInput([
    'command' => 'app:setup',
    '--force' => null,
]);

$input->setInteractive(false);

$output = new StringOutput();

$application->run($input, $output);

ob_start();
$errorCode = $application->run($input, $output);
if (!$result = $output->getBuffer()) {
    $result = ob_get_contents();
}
ob_end_clean();

echo nl2br($result);
