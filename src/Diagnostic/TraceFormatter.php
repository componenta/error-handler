<?php

declare(strict_types=1);

namespace Componenta\Error\Diagnostic;

use Throwable;

/** @internal A bounded stack description that never formats argument values. */
final class TraceFormatter
{
    public static function format(Throwable $exception): string
    {
        $trace = $exception->getTrace();
        $lines = [];

        foreach (array_slice($trace, 0, 100) as $index => $frame) {
            $location = isset($frame['file'])
                ? sprintf('%s(%d)', $frame['file'], $frame['line'] ?? 0)
                : '[internal function]';
            $lines[] = sprintf(
                '#%d %s: %s%s%s()',
                $index,
                $location,
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'],
            );
        }

        if (count($trace) > 100) {
            $lines[] = sprintf('... %d additional frames', count($trace) - 100);
        }

        return implode("\n", $lines);
    }
}
