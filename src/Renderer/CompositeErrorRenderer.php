<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Throwable;

/**
 * Composite error renderer with priority-based selection
 *
 * Manages multiple renderers and selects the first one that
 * supports the given exception. Falls back to a default renderer
 * if no other renderer matches.
 */
class CompositeErrorRenderer implements ErrorRendererInterface
{
    /**
     * @var array<array{renderer: ErrorRendererInterface, priority: int}> Registered renderers
     */
    private array $renderers = [];

    /**
     * @var bool Whether renderers need sorting
     */
    private bool $needsSort = false;

    /**
     * Create composite renderer
     *
     * @param ErrorRendererInterface $fallbackRenderer Default renderer for unmatched exceptions
     */
    public function __construct(
        private readonly ErrorRendererInterface $fallbackRenderer = new SafeRenderer(),
    ) {
    }

    /**
     * Add renderer with priority
     *
     * Higher priority renderers are checked first.
     *
     * @param ErrorRendererInterface $renderer Renderer to add
     * @param int $priority Renderer priority
     * @return void
     */
    public function addRenderer(ErrorRendererInterface $renderer, int $priority = 0): void
    {
        $this->renderers[] = ['renderer' => $renderer, 'priority' => $priority];
        $this->needsSort = true;
    }

    /**
     * Render exception using first supporting renderer
     *
     * @param Throwable $exception Exception to render
     * @param ErrorContextInterface $context Context information
     * @return string Rendered output
     */
    public function render(Throwable $exception, ErrorContextInterface $context): string
    {
        return $this->getRenderer($exception, $context)->render($exception, $context);
    }

    /**
     * Check if composite renderer supports the exception
     *
     * Always returns true as it has a fallback renderer.
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
     * Get renderer that supports the exception
     *
     * @param Throwable $exception Exception to find renderer for
     * @param ErrorContextInterface $context Context information
     * @return ErrorRendererInterface Supporting renderer
     */
    private function getRenderer(Throwable $exception, ErrorContextInterface $context): ErrorRendererInterface
    {
        $this->sortRenderers();

        foreach ($this->renderers as ['renderer' => $renderer]) {
            if ($renderer->supports($exception, $context)) {
                return $renderer;
            }
        }

        return $this->fallbackRenderer;
    }

    /**
     * Sort renderers by priority (descending)
     */
    private function sortRenderers(): void
    {
        if ($this->needsSort) {
            usort(
                $this->renderers,
                static fn(array $a, array $b): int => $b['priority'] <=> $a['priority'],
            );
            $this->needsSort = false;
        }
    }
}
