<?php

declare(strict_types=1);

namespace Componenta\Error\Internal;

use Throwable;

/** @internal Best-effort diagnostics that cannot replace the primary failure. */
final class FailureLog
{
    public static function write(string $stage, Throwable $failure, ?Throwable $original = null): void
    {
        try {
            @error_log(sprintf('[error-handler] %s: %s%s', $stage, get_debug_type($failure),
                $original === null ? '' : ' (while handling ' . get_debug_type($original) . ')'));
        } catch (Throwable) {
            // The last-resort diagnostic must not create a third failure.
        }
    }
}
