<?php

declare(strict_types=1);

namespace Componenta\Error\Diagnostic;

/** @internal Per-render budgets; never retained on a shared renderer. */
final class DiagnosticBudget
{
    private int $nodes = 1024;
    private int $bytes = 262_144;
    private int $sourceReads = 32;
    private int $sourceBytes = 262_144;

    public function hasRemaining(): bool
    {
        return $this->nodes > 0 && $this->bytes > 0;
    }

    public function sanitize(mixed $value, int $depth = 0): mixed
    {
        if (!$this->hasRemaining()) {
            return is_array($value) ? [] : '[diagnostic limit]';
        }

        return $this->walk($value, max(0, $depth));
    }

    /** @return array{startLine: int, errorLine: int, lines: list<string>}|null */
    public function source(string $file, int $line, int $context): ?array
    {
        if ($this->sourceReads <= 0 || $this->sourceBytes <= 0) {
            return null;
        }
        --$this->sourceReads;
        $excerpt = SourceExcerpt::read($file, $line, $context);
        if ($excerpt === null) {
            return null;
        }

        $lines = [];
        foreach ($excerpt['lines'] as $text) {
            if ($this->sourceBytes <= 0) {
                break;
            }
            $text = DiagnosticData::boundedText($text, $this->sourceBytes);
            $this->sourceBytes -= strlen($text) + 1;
            $lines[] = $text;
        }

        return ['startLine' => $excerpt['startLine'], 'errorLine' => $excerpt['errorLine'], 'lines' => $lines];
    }

    private function walk(mixed $value, int $depth): mixed
    {
        --$this->nodes;
        if (is_array($value)) {
            if ($depth >= 5) {
                return '[depth limit]';
            }

            $result = [];
            $count = 0;
            foreach ($value as $key => $item) {
                if ($count++ >= 100 || $this->nodes <= 0 || $this->bytes <= 0) {
                    break;
                }

                if (is_string($key)) {
                    $keyLength = strlen($key);
                    if ($keyLength > 256 || $keyLength > $this->bytes) {
                        --$this->nodes;
                        continue;
                    }
                    $this->bytes -= $keyLength;
                }

                if (DiagnosticData::isSensitiveKey($key)) {
                    --$this->nodes;
                    $result[$key] = DiagnosticData::REDACTED;
                } else {
                    $result[$key] = $this->walk($item, $depth + 1);
                }
            }
            return $result;
        }

        if (is_object($value)) {
            $value = $value instanceof \SensitiveParameterValue ? DiagnosticData::REDACTED : $value::class;
        }
        if (is_resource($value)) {
            return '[resource]';
        }
        if (is_string($value)) {
            $limit = max(0, min(4096, $this->bytes));
            if (strlen($value) > $limit) {
                $value = DiagnosticData::boundedText($value, $limit);
            }
            $this->bytes -= strlen($value);
        }

        return $value;
    }
}
