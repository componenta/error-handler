<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;

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
     * @param ErrorOccurrence $error Error occurrence
     * @param ErrorContextInterface $context Context information
     * @return RenderedError Rendered content and media type
     */
    public function render(ErrorOccurrence $error, ErrorContextInterface $context): RenderedError
    {
        return new RenderedError('', 'text/plain; charset=utf-8');
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
}
