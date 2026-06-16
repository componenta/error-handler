<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\SupportsErrorInterface;
use Throwable;

/**
 * Interface for exception renderers
 *
 * Renderers convert exceptions to string output (HTML, JSON, text, etc.).
 * Multiple renderers can be combined using CompositeErrorRenderer.
 */
interface ErrorRendererInterface extends SupportsErrorInterface
{
    /**
     * Render exception to string
     *
     * @param Throwable $exception Exception to render
     * @param ErrorContextInterface $context Context information
     * @return string Rendered output
     */
    public function render(Throwable $exception, ErrorContextInterface $context): string;
}
