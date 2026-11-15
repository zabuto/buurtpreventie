<?php

// ruleid: byte-lowercase
$key = \strtolower($name);

// ruleid: byte-lowercase
$key = strtolower($name);

// ruleid: byte-lowercase
$code = \strtoupper($postcode);

// ok: byte-lowercase
$key = \mb_strtolower($name);

// ok: byte-lowercase
$code = \mb_strtoupper($postcode);
