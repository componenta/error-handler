<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Throwable;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;

/**
 * Null error renderer that outputs nothing
 *
 * Useful for testing, silent mode, or when only reporting is needed.
 */
readonly class NullRenderer implements HttpErrorRendererInterface
{
    /**
     * Render exception as empty string
     *
     * @param Throwable $exception Exception to render
     * @param ErrorContextInterface $context Context information
     * @return string Empty string
     */
    public function render(Throwable $exception, ErrorContextInterface $context): string
    {
        return '';
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

    public function renderHttp(Throwable $exception, \Componenta\Error\Context\HttpErrorContextInterface $context): RenderedError
    {
        return new RenderedError($this->render($exception, $context), 'text/plain; charset=utf-8');
    }
}
