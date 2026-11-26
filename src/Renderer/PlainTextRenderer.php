<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Throwable;

/**
 * Plain text error renderer for CLI and logs
 *
 * Renders exceptions as plain text with optional debug information.
 */
readonly class PlainTextRenderer implements ErrorRendererInterface
{
    /**
     * Create plain text renderer
     *
     * @param bool $debug Include debug information (trace)
     * @param bool $includePrevious Include previous exceptions chain
     */
    public function __construct(
        private bool $debug = false,
        private bool $includePrevious = true,
    ) {
    }

    /**
     * Render exception as plain text
     *
     * @param ErrorOccurrence $error Error occurrence
     * @param ErrorContextInterface $context Context information
     * @return RenderedError Rendered content and media type
     */
    public function render(ErrorOccurrence $error, ErrorContextInterface $context): RenderedError
    {
        $exception = $error->exception;
        $output = $this->formatException($exception, 0);

        if ($this->includePrevious) {
            $previous = $exception->getPrevious();
            $depth = 1;

            while ($previous !== null && $depth <= 32) {
                $output .= "\n" . $this->formatException($previous, $depth);
                $previous = $previous->getPrevious();
                $depth++;
            }
            if ($previous !== null) {
                $output .= "\n... additional previous exceptions";
            }
        }

        return new RenderedError($output . "\nError ID: " . $error->id, 'text/plain; charset=utf-8');
    }

    /**
     * Check if renderer supports the exception
     *
     * @param ErrorOccurrence $error Error occurrence
     * @param ErrorContextInterface $context Context information
     * @return bool Always returns true
     */
    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool
    {
        return true;
    }

    /**
     * Format single exception
     */
    private function formatException(Throwable $exception, int $depth): string
    {
        $indent = str_repeat('  ', $depth);
        $prefix = $depth > 0 ? 'Caused by: ' : '';

        $lines = [
            sprintf(
                '%s%s%s: %s',
                $indent,
                $prefix,
                \Componenta\Error\Diagnostic\DiagnosticData::terminal($exception::class),
                \Componenta\Error\Diagnostic\DiagnosticData::terminal($exception->getMessage()),
            ),
            sprintf(
                '%s  in %s:%d',
                $indent,
                \Componenta\Error\Diagnostic\DiagnosticData::terminal($exception->getFile()),
                $exception->getLine(),
            ),
        ];

        if ($this->debug) {
            $lines[] = '';
            $lines[] = $indent . 'Stack trace:';

            foreach (explode("\n", \Componenta\Error\Diagnostic\TraceFormatter::format($exception)) as $traceLine) {
                $lines[] = $indent . '  ' . \Componenta\Error\Diagnostic\DiagnosticData::terminal($traceLine);
            }
        }

        return implode("\n", $lines);
    }
}
