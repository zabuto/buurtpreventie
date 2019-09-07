<?php

namespace App\Command\Output;

use Symfony\Component\Console\Output\Output;

/**
 * StringOutput
 */
class StringOutput extends Output
{
    /**
     * @var string
     */
    protected $buffer = '';

    /**
     * Writes a message to the output.
     *
     * @param  string $message A message to write to the output
     * @param  bool   $newline Whether to add a newline or not
     */
    public function doWrite($message, $newline)
    {
        $this->buffer .= $message . ($newline === true ? PHP_EOL : '');
    }

    /**
     * @return string
     */
    public function getBuffer()
    {
        return $this->buffer;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->buffer;
    }
}
