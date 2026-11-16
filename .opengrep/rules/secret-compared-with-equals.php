<?php

// ruleid: secret-compared-with-equals
if ($token === $expected) {
}

// ruleid: secret-compared-with-equals
if ($storedHash == self::hash($secret)) {
}

// ruleid: secret-compared-with-equals
if ($this->secretHash !== $candidate) {
}

// ruleid: secret-compared-with-equals
if ($typed != $invitationSecret) {
}

// ruleid: secret-compared-with-equals
if (\strcmp($codeHash, $expected) === 0) {
}

// ruleid: secret-compared-with-equals
if (strcmp($expected, $apiToken) === 0) {
}

// ok: secret-compared-with-equals
if (\hash_equals($this->secretHash, self::hash($secret))) {
}

// ok: secret-compared-with-equals
if (null === $token || !$token->hasAttribute('scopes')) {
}

// ok: secret-compared-with-equals
if ('' !== $this->token && false !== $hash && $secret !== true) {
}

// ok: secret-compared-with-equals
if ($token->revokedAt() === null) {
}

// ok: secret-compared-with-equals
if ($tokenCount === 3 || $scope === Scope::Read) {
}

// ok: secret-compared-with-equals
if (\strcmp($this->id->toBinary(), $other->id->toBinary()) < 0) {
}
