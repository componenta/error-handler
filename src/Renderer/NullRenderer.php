<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Throwable;
use Componenta\Error\ErrorContextInterface;

/**
 * Null error renderer that outputs nothing
 *
 * Useful for testing, silent mode, or when only reporting is needed.
 */
readonly class NullRenderer implements ErrorRendererInterface
{
    /**
     * Render empty content
     *
     * @param Throwable $exception Exception to render
     * @param ErrorContextInterface $context Context information
     * @return RenderedError Rendered content and media type
     */
    public function render(Throwable $exception, ErrorContextInterface $context): RenderedError
    {
        return new RenderedError('', 'text/plain; charset=utf-8');
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
}
