<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Throwable;

/**
 * Plain text error renderer for CLI and logs
 *
 * Renders exceptions as plain text with optional debug information.
 */
readonly class PlainTextRenderer implements HttpErrorRendererInterface
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
     * @param Throwable $exception Exception to render
     * @param ErrorContextInterface $context Context information
     * @return string Plain text output
     */
    public function render(Throwable $exception, ErrorContextInterface $context): string
    {
        $output = $this->formatException($exception, 0);

        if ($this->includePrevious) {
            $previous = $exception->getPrevious();
            $depth = 1;

            while ($previous !== null) {
                $output .= "\n" . $this->formatException($previous, $depth);
                $previous = $previous->getPrevious();
                $depth++;
            }
        }

        return $output;
    }

    /**
     * Check if renderer supports the exception
     *
     * @param Throwable $exception Exception to check
     * @param ErrorContextInterface $context Context information
     * @return bool Always returns true
     */
    public function supports(Throwable $exception, ErrorContextInterface $context): bool
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
                $exception::class,
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

            foreach (explode("\n", $exception->getTraceAsString()) as $traceLine) {
                $lines[] = $indent . '  ' . \Componenta\Error\Diagnostic\DiagnosticData::terminal($traceLine);
            }
        }

        return implode("\n", $lines);
    }

    public function renderHttp(Throwable $exception, \Componenta\Error\Context\HttpErrorContextInterface $context): RenderedError
    {
        return new RenderedError($this->render($exception, $context), 'text/plain; charset=utf-8');
    }
}
