<?php

declare(strict_types=1);

namespace Componenta\Error\Internal;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/** @internal Complete writes to streams that may accept only part of a buffer. */
final class StreamWriter
{
    public static function write(StreamInterface $stream, string $body): void
    {
        $length = strlen($body);
        $offset = 0;

        while ($offset < $length) {
            $written = $stream->write(substr($body, $offset));
            if ($written <= 0 || $written > $length - $offset) {
                throw new RuntimeException('Unable to write the complete error response.');
            }
            $offset += $written;
        }
    }
}
