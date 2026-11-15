<?php

// ruleid: unserialize-on-variable
unserialize($payload);

// ruleid: unserialize-on-variable
unserialize($request->getContent(), ['allowed_classes' => true]);

// ok: unserialize-on-variable
unserialize('a:0:{}');

// ok: unserialize-on-variable
unserialize($cached, ['allowed_classes' => false]);

// ruleid: unserialize-on-variable
\unserialize($payload);
