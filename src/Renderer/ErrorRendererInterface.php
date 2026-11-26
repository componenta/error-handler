<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\SupportsErrorInterface;

/**
 * Interface for exception renderers
 *
 * Renderers convert exceptions to content with an explicit media type (HTML, JSON, text, etc.).
 * Multiple renderers can be combined using CompositeErrorRenderer.
 */
interface ErrorRendererInterface extends SupportsErrorInterface
{
    /**
     * Render the exception
     *
     * @param ErrorOccurrence $error Error occurrence
     * @param ErrorContextInterface $context Context information
     * @return RenderedError Rendered content and media type
     */
    public function render(ErrorOccurrence $error, ErrorContextInterface $context): RenderedError;
}
