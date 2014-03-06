<?php
/**
 * Validatie Gmail account
 */

if (!isset($_SERVER['HTTP_HOST'])) {
    exit('Dit script kan alleen in een browser worden gedraaid.');
}

umask(0000);
$baseDir = realpath(dirname(__FILE__) . '/..');

$loader = require_once $baseDir . '/app/bootstrap.php.cache';
require_once $baseDir . '/app/AppKernel.php';
$kernel = new AppKernel('prod', false);
$kernel->boot();

$container = $kernel->getContainer();

try {
    $message = \Swift_Message::newInstance();
    $message->setSubject('Validatie applicatie');
    $message->setFrom($container->getParameter('mailer_address'));
    $message->setTo($container->getParameter('mailer_address'));
    $message->setBody('Bericht voor controle van gebruik Gmail account door Buurtpreventie applicatie.');
    $container->get('mailer')->send($message);
    echo '<h1>Email verzonden</h1>';
} catch (\Exception $e) {
    echo '<h1>' . $e->getMessage() . '</h1>';
    echo $e->getTraceAsString();
    echo '<a href="https://accounts.google.com/DisplayUnlockCaptcha">Google Validatie</a>';
}

