<?php

// ruleid: command-execution
eval($code);

// ruleid: command-execution
\exec('ls ' . $folder, $output);

// ruleid: command-execution
shell_exec("tar -xf {$archive}");

// ruleid: command-execution
system('whoami');

// ruleid: command-execution
passthru($command);

// ruleid: command-execution
proc_open($command, [], $pipes);

// ruleid: command-execution
popen($command, 'r');

// ruleid: command-execution
pcntl_exec('/bin/sh');

// ruleid: command-execution
$listing = `ls -la`;

// ok: command-execution
$statement->execute();

// ok: command-execution
$connection->exec('SELECT 1');
