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

        foreach (explode(',', $accept) as $entry) {
            $parts = explode(';', $entry);
            $media = strtolower(trim(array_shift($parts)));
            $rank = match ($media) {
                $type => 2,
                $major . '/*' => 1,
                '*/*' => 0,
                default => -1,
            };

            if ($rank < 0 || $rank < $specificity) {
                continue;
            }

            $q = 1.0;
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
                if ($key !== 'charset' || strtolower(trim($value, " \t\"")) !== 'utf-8') {
                    $rank = -1;
                    break;
                }
            }

            if ($rank < 0) {
                continue;
            }

            $quality = $rank > $specificity ? $q : max($quality, $q);
            $specificity = $rank;
        }

        return $quality;
    }
}
