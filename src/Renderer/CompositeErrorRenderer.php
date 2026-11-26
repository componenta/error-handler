<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;

/**
 * Composite error renderer with priority-based selection
 *
 * Manages multiple renderers and selects the first one that
 * supports the given exception. Falls back to a default renderer
 * if no other renderer matches.
 */
final class CompositeErrorRenderer implements ErrorRendererInterface
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
        if ($renderer === $this || ($renderer instanceof self && $renderer->contains($this))) {
            throw new \InvalidArgumentException('Cyclic composite error renderer.');
        }
        $this->renderers[] = ['renderer' => $renderer, 'priority' => $priority];
        $this->needsSort = true;
    }

    /**
     * Render exception using first supporting renderer
     *
     * @param ErrorOccurrence $error Error occurrence
     * @param ErrorContextInterface $context Context information
     * @return RenderedError Rendered content and media type
     */
    public function render(ErrorOccurrence $error, ErrorContextInterface $context): RenderedError
    {
        $renderer = $this->getRenderer($error, $context);
        if ($renderer === null) {
            throw new \InvalidArgumentException('No error renderer supports this occurrence and context.');
        }

        return $renderer->render($error, $context);
    }

    /**
     * Check if composite renderer supports the exception
     *
     * A candidate or fallback must support the occurrence and context.
     *
     * @param ErrorOccurrence $error Error occurrence
     * @param ErrorContextInterface $context Context information
     * @return bool Whether a renderer supports the occurrence and context
     */
    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool
    {
        return $this->getRenderer($error, $context) !== null;
    }

    /**
     * Get renderer that supports the exception
     *
     * @param ErrorOccurrence $error Error occurrence
     * @param ErrorContextInterface $context Context information
     * @return ErrorRendererInterface|null Supporting renderer or no match
     */
    private function getRenderer(ErrorOccurrence $error, ErrorContextInterface $context): ?ErrorRendererInterface
    {
        $this->sortRenderers();

        foreach ($this->renderers as ['renderer' => $renderer]) {
            if ($renderer->supports($error, $context)) {
                return $renderer;
            }
        }

        return $this->fallbackRenderer->supports($error, $context) ? $this->fallbackRenderer : null;
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


    private function contains(self $needle): bool
    {
        if ($this === $needle) {
            return true;
        }
        if ($this->fallbackRenderer instanceof self && $this->fallbackRenderer->contains($needle)) {
            return true;
        }
        foreach ($this->renderers as ['renderer' => $renderer]) {
            if ($renderer instanceof self && $renderer->contains($needle)) {
                return true;
            }
        }

        return false;
    }
}
