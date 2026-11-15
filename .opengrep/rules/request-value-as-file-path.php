<?php

$name = $request->query->get('file');
// ruleid: request-value-as-file-path
$contents = \file_get_contents('/var/uploads/' . $name);

// ruleid: request-value-as-file-path
unlink($request->get('path'));

$upload = $request->files->get('logo');
// ruleid: request-value-as-file-path
$upload->move($brandFolder, $upload->getClientOriginalName());

// ruleid: request-value-as-file-path
include $_GET['page'];

// ok: request-value-as-file-path
$contents = \file_get_contents($this->projectDir . '/config/defaults.json');

// ok: request-value-as-file-path
$upload->move($brandFolder, \bin2hex(\random_bytes(16)) . '.png');
