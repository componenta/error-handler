<?php

declare(strict_types=1);

namespace Componenta\Error\Diagnostic;

/** Limits diagnostic data without invoking application object hooks. */
final class DiagnosticData
{
    public const string REDACTED = '[redacted]';

    public static function isSensitiveKey(string|int $key): bool
    {
        $name = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $key) ?? '');

        return preg_match('/password|passwd|passphrase|secret|token|apikey|authorization|cookie|session|credential|privatekey|signature|csrf|jwt|bearer|accesskey|clientsecret/', $name) === 1;
    }

    /** Limits total traversal and string data, including shared and recursive arrays. */
    public static function sanitize(mixed $value, int $depth = 0): mixed
    {
        return (new DiagnosticBudget())->sanitize($value, $depth);
    }

    /** @internal Bounds a diagnostic string before encoding or formatting. */
    public static function boundedText(string $value, int $limit = 16_384): string
    {
        if ($limit <= 0) {
            return '';
        }
        if (strlen($value) <= $limit) {
            return $value;
        }
        if ($limit <= 3) {
            return str_repeat('.', $limit);
        }

        return mb_strcut($value, 0, $limit - 3, 'UTF-8') . '...';
    }

    /** Escapes terminal controls while keeping ordinary UTF-8, tabs and newlines. */
    public static function terminal(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');

        return preg_replace_callback(
            '/[\x{0000}-\x{0008}\x{000b}-\x{001f}\x{007f}-\x{009f}\x{061c}\x{200e}\x{200f}\x{202a}-\x{202e}\x{2066}-\x{2069}]/u',
            static fn (array $match): string => '\x' . strtoupper(bin2hex($match[0])),
            $value,
        ) ?? '[unavailable]';
    }
}
