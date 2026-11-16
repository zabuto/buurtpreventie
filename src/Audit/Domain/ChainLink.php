<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use InvalidArgumentException;

/**
 * A place in the audit chain: the position of an entry and its hash, which the next entry includes in its own.
 */
final readonly class ChainLink
{
    private const int HASH_LENGTH = 32;

    /**
     * @param string $hash 32 raw bytes
     *
     * @throws InvalidArgumentException for a negative position or a hash of another length
     */
    public function __construct(
        public int $position,
        public string $hash,
    ) {
        if ($position < 0 || self::HASH_LENGTH !== \strlen($hash)) {
            throw new InvalidArgumentException('A chain link has a position from 0 and a hash of 32 bytes.');
        }
    }

    /**
     * Where the chain of an empty log starts: the first entry takes position 1.
     */
    public static function start(): self
    {
        return new self(0, \str_repeat("\0", self::HASH_LENGTH));
    }

    public function equals(self $other): bool
    {
        return $this->position === $other->position && \hash_equals($this->hash, $other->hash);
    }
}
