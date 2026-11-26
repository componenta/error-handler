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

    public static function sanitize(mixed $value, int $depth = 0): mixed
    {
        if (is_array($value)) {
            if ($depth >= 5) {
                return '[depth limit]';
            }
            $result = [];
            $count = 0;
            foreach ($value as $key => $item) {
                if ($count++ >= 100) {
                    break;
                }
                $result[$key] = self::isSensitiveKey($key) ? self::REDACTED : self::sanitize($item, $depth + 1);
            }
            return $result;
        }

        if (is_object($value)) {
            return $value instanceof \SensitiveParameterValue ? self::REDACTED : $value::class;
        }
        if (is_resource($value)) {
            return '[resource]';
        }
        if (is_string($value) && strlen($value) > 4096) {
            return mb_strcut($value, 0, 4096, 'UTF-8') . '...';
        }

        return $value;
    }

    public static function terminal(string $value): string
    {
        return preg_replace_callback('/[\x00-\x08\x0b-\x1f\x7f]|\xc2[\x80-\x9f]/', static fn (array $m): string =>
            '\x' . strtoupper(bin2hex($m[0])), $value) ?? '[unavailable]';
    }
}
