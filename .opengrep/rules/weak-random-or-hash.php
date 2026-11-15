<?php

// ruleid: weak-random-or-hash
$token = \sha1(\random_bytes(10));

// ruleid: weak-random-or-hash
$checksum = md5($content);

// ruleid: weak-random-or-hash
$code = \rand(1000, 9999);

// ruleid: weak-random-or-hash
$code = mt_rand(1000, 9999);

// ruleid: weak-random-or-hash
$name = \uniqid('export_');

// ruleid: weak-random-or-hash
$chance = lcg_value();

// ruleid: weak-random-or-hash
$secret = \str_shuffle('abcdefghijklmnopqrstuvwxyz');

// ruleid: weak-random-or-hash
$digest = \hash('sha1', $secret);

// ruleid: weak-random-or-hash
$digest = hash('md5', $secret);

// ok: weak-random-or-hash
$secret = \bin2hex(\random_bytes(32));

// ok: weak-random-or-hash
$digest = \hash('sha256', $secret);

// ok: weak-random-or-hash
$code = \random_int(1000, 9999);
