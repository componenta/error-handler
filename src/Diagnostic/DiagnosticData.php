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
        $nodes = 1024;
        $bytes = 262_144;

        return self::walk($value, max(0, $depth), $nodes, $bytes);
    }

    private static function walk(mixed $value, int $depth, int &$nodes, int &$bytes): mixed
    {
        --$nodes;
        if (is_array($value)) {
            if ($depth >= 5) {
                return '[depth limit]';
            }

            $result = [];
            $count = 0;
            foreach ($value as $key => $item) {
                if ($count++ >= 100 || $nodes <= 0 || $bytes <= 0) {
                    break;
                }

                if (is_string($key)) {
                    $keyLength = strlen($key);
                    if ($keyLength > 256 || $keyLength > $bytes) {
                        --$nodes;
                        continue;
                    }
                    $bytes -= $keyLength;
                }

                if (self::isSensitiveKey($key)) {
                    --$nodes;
                    $result[$key] = self::REDACTED;
                } else {
                    $result[$key] = self::walk($item, $depth + 1, $nodes, $bytes);
                }
            }
            return $result;
        }

        if (is_object($value)) {
            $value = $value instanceof \SensitiveParameterValue ? self::REDACTED : $value::class;
        }
        if (is_resource($value)) {
            return '[resource]';
        }
        if (is_string($value)) {
            $limit = max(0, min(4096, $bytes));
            if (strlen($value) > $limit) {
                $value = mb_strcut($value, 0, $limit, 'UTF-8') . '...';
            }
            $bytes -= strlen($value);
        }

        return $value;
    }

    public static function terminal(string $value): string
    {
        return preg_replace_callback('/[\x00-\x08\x0b-\x1f\x7f]|\xc2[\x80-\x9f]/', static fn (array $m): string =>
            '\x' . strtoupper(bin2hex($m[0])), $value) ?? '[unavailable]';
    }
}
