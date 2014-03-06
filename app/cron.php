#!/usr/bin/env php
<?php
umask(0000);

$loader = require_once __DIR__ . '/bootstrap.php.cache';

use Zabuto\Bundle\BuurtpreventieBundle\Command\Reminder;
use Symfony\Bundle\FrameworkBundle\Console\Application;

require_once __DIR__ . '/AppKernel.php';

$kernel = new AppKernel('prod', false);
$kernel->loadClassCache();

$application = new Application($kernel);
$application->add(new Reminder);
$application->run();
