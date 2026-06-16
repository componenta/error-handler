<?php

declare(strict_types=1);

namespace Componenta\Error\Handler;

use Componenta\Error\Context\CliErrorContextInterface;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Renderer\PlainTextRenderer;
use Throwable;

/**
 * CLI error handler that renders exceptions to console output.
 */
readonly class RenderingCliErrorHandler implements CliErrorHandlerInterface
{
    /**
     * @param ErrorRendererInterface $renderer Renderer for exception output (defaults to PlainTextRenderer)
     */
    public function __construct(
        protected ErrorRendererInterface $renderer = new PlainTextRenderer(),
    ) {
    }

    /**
     * Handle exception in CLI context
     *
     * Renders exception and writes output to console.
     * @param Throwable $exception Exception to handle
     * @param CliErrorContextInterface $context CLI context with output
     * @return void
     */
    public function handle(
        Throwable $exception,
        CliErrorContextInterface $context,
    ): void {
        $context->output->writeln($this->render($exception, $context));
    }

    /**
     * Render exception to string
     *
     * @param Throwable $exception Exception to render
     * @param ErrorContextInterface $context Context information
     * @return string Rendered output
     */
    public function render(Throwable $exception, ErrorContextInterface $context): string
    {
        return $this->renderer->render($exception, $context);
    }

    public function supports(Throwable $exception, ErrorContextInterface $context): bool
    {
        return $context instanceof CliErrorContextInterface;
    }
}
