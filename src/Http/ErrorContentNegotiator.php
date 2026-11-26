<?php

declare(strict_types=1);

namespace Componenta\Error\Http;

/** Selects one of the two built-in error representations. */
final class ErrorContentNegotiator
{
    public static function prefersJson(string $accept): bool
    {
        $html = self::quality('text/html', $accept);
        $json = self::quality('application/json', $accept);

        // Prefer HTML for ties and use it as the documented default if neither
        // representation is acceptable. Do not recursively generate a 406.
        return $json > 0 && $json > $html;
    }

    private static function quality(string $type, string $accept): float
    {
        if (trim($accept) === '') {
            return 1.0;
        }

        [$major] = explode('/', $type, 2);
        $specificity = -1;
        $quality = 0.0;

        foreach (self::split($accept, ',') as $entry) {
            $parts = self::split($entry, ';');
            $media = strtolower(trim(array_shift($parts) ?? ''));
            $rank = match ($media) {
                $type => 4,
                $major . '/*' => 2,
                '*/*' => 0,
                default => -1,
            };

            if ($rank < 0) {
                continue;
            }

            $q = 1.0;
            $charset = false;
            foreach ($parts as $parameter) {
                [$key, $value] = array_pad(explode('=', trim($parameter), 2), 2, '');
                $key = strtolower(trim($key));
                if ($key === 'q') {
                    $value = trim($value);
                    $q = preg_match('/^(?:0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/D', $value)
                        ? (float) $value : 0.0;
                    break;
                }
                // Both built-in representations are UTF-8. Do not match
                // representation parameters we cannot satisfy.
                if ($key !== 'charset' || $charset || !in_array(strtolower(trim($value)), ['utf-8', '"utf-8"'], true)) {
                    $rank = -1;
                    break;
                }
                $charset = true;
                ++$rank;
            }

            if ($rank < 0 || $rank < $specificity) {
                continue;
            }

            $quality = $rank > $specificity ? $q : max($quality, $q);
            $specificity = $rank;
        }

        return $quality;
    }

    /** @return list<string> Split a list without treating quoted delimiters as separators. */
    private static function split(string $value, string $delimiter): array
    {
        if (!str_contains($value, '"')) {
            return explode($delimiter, $value);
        }

        $parts = [];
        $start = 0;
        $quoted = false;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $char = $value[$index];
            if ($quoted && $char === '\\') {
                ++$index;
                continue;
            }
            if ($char === '"') {
                $quoted = !$quoted;
                continue;
            }
            if (!$quoted && $char === $delimiter) {
                $parts[] = substr($value, $start, $index - $start);
                $start = $index + 1;
            }
        }
        if (!$quoted) {
            $parts[] = substr($value, $start);
        }

        return $parts;
    }
}
