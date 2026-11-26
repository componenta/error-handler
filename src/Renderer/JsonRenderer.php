<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Throwable;

/**
 * JSON error renderer for APIs
 *
 * Renders exceptions as JSON with optional debug information.
 */
readonly class JsonRenderer implements HttpErrorRendererInterface
{
    /**
     * Create JSON renderer
     *
     * @param bool $debug Include debug information (trace, file, line)
     * @param int $jsonFlags JSON encoding flags
     */
    public function __construct(
        private bool $debug = false,
        private int $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
    ) {
    }

    /**
     * Render exception as JSON
     *
     * @param Throwable $exception Exception to render
     * @param ErrorContextInterface $context Context information
     * @return string JSON output
     */
    public function render(Throwable $exception, ErrorContextInterface $context): string
    {
        $data = [
            'error' => [
                'type' => $exception::class,
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
            ],
        ];

        if ($this->debug) {
            $data['error']['file'] = $exception->getFile();
            $data['error']['line'] = $exception->getLine();
            $data['error']['trace'] = $this->formatTrace($exception);

            if ($previous = $exception->getPrevious()) {
                $data['error']['previous'] = $this->formatPrevious($previous);
            }
        }

        return json_encode($data, $this->jsonFlags | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Check if renderer supports the exception
     *
     * @param Throwable $exception Exception to check
     * @param ErrorContextInterface $context Context information
     * @return true Always returns true
     */
    public function supports(Throwable $exception, ErrorContextInterface $context): true
    {
        return true;
    }

    /**
     * Format stack trace for JSON
     * @return list<array{file: ?string, line: ?int, function: string, class: ?string, type: ?string}>
     */
    private function formatTrace(Throwable $exception): array
    {
        return array_map(
            static fn(array $frame): array => [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'],
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
            ],
            $exception->getTrace(),
        );
    }

    /**
     * Format previous exceptions chain
     * @return list<array{type: string, code: int|string, message: string, file: string, line: int}>
     */
    private function formatPrevious(Throwable $exception): array
    {
        $chain = [];

        while ($exception !== null && count($chain) < 32) {
            $chain[] = [
                'type' => $exception::class,
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
            $exception = $exception->getPrevious();
        }

        return $chain;
    }

    public function renderHttp(Throwable $exception, \Componenta\Error\Context\HttpErrorContextInterface $context): RenderedError
    {
        return new RenderedError($this->render($exception, $context), 'application/json; charset=utf-8');
    }
}
