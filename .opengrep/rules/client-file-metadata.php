<?php

// ruleid: client-file-metadata
$name = $upload->getClientOriginalName();

// ruleid: client-file-metadata
$extension = $upload->getClientOriginalExtension();

// ruleid: client-file-metadata
$type = $request->files->get('logo')->getClientMimeType();

// ruleid: client-file-metadata
$extension = $upload->guessClientExtension();

// ok: client-file-metadata
$type = $images->facts($content)->format;

// ok: client-file-metadata
$ip = $request->getClientIp();
