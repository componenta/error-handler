<?php

declare(strict_types=1);

namespace Componenta\Error\Internal;

use RuntimeException;
use Throwable;

/** @internal Captures a trusted PHP template without leaking removable output buffers. */
final class TemplateOutput
{
    /** @param array<string, mixed> $data */
    public static function render(string $templatePath, array $data): string
    {
        $level = ob_get_level();

        // A static closure prevents templates from accessing renderer internals.
        $render = static function (string $templatePath, array $data): void {
            $e = static fn (mixed $value): string => htmlspecialchars(
                is_scalar($value) ? (string) $value : '',
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8',
            );
            extract($data, EXTR_SKIP);
            require $templatePath;
        };

        if (!ob_start()) {
            throw new RuntimeException('Unable to start the template output buffer.');
        }

        try {
            $render($templatePath, $data);
            if (ob_get_level() !== $level + 1) {
                throw new RuntimeException('The error template changed output-buffer nesting.');
            }
            $output = ob_get_contents();
            if ($output === false) {
                throw new RuntimeException('Unable to read the template output buffer.');
            }

            return $output;
        } finally {
            while (ob_get_level() > $level) {
                $before = ob_get_level();
                $status = ob_get_status();
                $flags = $status['flags'] ?? 0;
                if (!is_int($flags) || ($flags & PHP_OUTPUT_HANDLER_REMOVABLE) === 0) {
                    FailureLog::write('template output buffer', new RuntimeException('Non-removable template buffer.'));
                    break;
                }
                try {
                    ob_end_clean();
                } catch (Throwable $failure) { // @phpstan-ignore catch.neverThrown (PHP invokes user-supplied output handlers during cleanup.)
                    FailureLog::write('template output buffer', $failure);
                }
                if (ob_get_level() >= $before) {
                    break;
                }
            }
        }
    }
}
