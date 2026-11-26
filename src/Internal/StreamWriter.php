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
            // Bound repeated copies when a stream accepts very small writes.
            $chunk = substr($body, $offset, 8192);
            $chunkLength = strlen($chunk);
            $position = 0;
            while ($position < $chunkLength) {
                $written = $stream->write(substr($chunk, $position));
                if ($written <= 0 || $written > $chunkLength - $position) {
                    throw new RuntimeException('Unable to write the complete error response.');
                }
                $position += $written;
            }
            $offset += $chunkLength;
        }
    }
}
