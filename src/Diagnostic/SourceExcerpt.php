<?php

declare(strict_types=1);

namespace Componenta\Error\Diagnostic;

use SplFileObject;
use Throwable;

/** Bounded local source reading; never opens URL or stream wrappers. */
final class SourceExcerpt
{
    private const int MAX_FILE_BYTES = 2_097_152;

    /** @return array{startLine: int, errorLine: int, lines: list<string>}|null */
    public static function read(string $file, int $line, int $context): ?array
    {
        if ($line < 1 || $context < 0 || str_contains($file, '://')) {
            return null;
        }

        try {
            $path = realpath($file);
            if ($path === false || !is_file($path) || !is_readable($path)) {
                return null;
            }
            if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['php', 'phtml', 'inc'], true)) {
                return null;
            }
            $size = filesize($path);
            if ($size === false || $size > self::MAX_FILE_BYTES) {
                return null;
            }
            $context = min($context, 50);
            $start = max(1, $line - $context);
            $end = $line + $context;
            $stream = new SplFileObject($path);
            $stream->setFlags(SplFileObject::DROP_NEW_LINE);
            $stream->seek($start - 1);
            $lines = [];
            for ($i = $start; $i <= $end && !$stream->eof(); $i++) {
                $current = $stream->current();
                if (!is_string($current)) {
                    break;
                }
                $lines[] = mb_strcut($current, 0, 4096, 'UTF-8');
                $stream->next();
            }

            return $lines === [] ? null : ['startLine' => $start, 'errorLine' => $line, 'lines' => $lines];
        } catch (Throwable) {
            return null;
        }
    }
}
