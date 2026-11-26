<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\ErrorContextInterface;
use Throwable;

/** Explicitly declares the HTTP representation of a string-only renderer. */
final readonly class FixedMediaTypeRenderer implements HttpErrorRendererInterface
{
    public function __construct(
        private ErrorRendererInterface $renderer,
        private string $contentType,
    ) {
        new RenderedError('', $contentType);
    }

    public function supports(Throwable $exception, ErrorContextInterface $context): bool
    {
        return $this->renderer->supports($exception, $context);
    }

    public function render(Throwable $exception, ErrorContextInterface $context): string
    {
        return $this->renderer->render($exception, $context);
    }

    public function renderHttp(Throwable $exception, HttpErrorContextInterface $context): RenderedError
    {
        return new RenderedError($this->render($exception, $context), $this->contentType);
    }
}
