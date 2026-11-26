<?php

declare(strict_types=1);

namespace Componenta\Error\Handler;

use Componenta\Error\Context\CliErrorContextInterface;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Renderer\RenderedError;
use Componenta\Error\Renderer\SafeCliRenderer;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI error handler that renders exceptions to console output.
 */
readonly class RenderingCliErrorHandler implements CliErrorHandlerInterface
{
    /**
     * @param ErrorRendererInterface $renderer Renderer for exception output (defaults to SafeCliRenderer)
     */
    public function __construct(
        protected ErrorRendererInterface $renderer = new SafeCliRenderer(),
    ) {
    }

    /**
     * Handle exception in CLI context
     *
     * Renders exception and writes output to console.
     * @param ErrorOccurrence $error Error occurrence
     * @param CliErrorContextInterface $context CLI context with output
     * @return void
     */
    public function handle(
        ErrorOccurrence $error,
        CliErrorContextInterface $context,
    ): void {
        $output = $context->output;
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }
        $output->writeln((string) $this->render($error, $context), OutputInterface::OUTPUT_RAW);
    }

    /**
     * Render the exception
     *
     * @param ErrorOccurrence $error Error occurrence
     * @param ErrorContextInterface $context Context information
     * @return RenderedError Rendered content and media type
     */
    public function render(ErrorOccurrence $error, ErrorContextInterface $context): RenderedError
    {
        return $this->renderer->render($error, $context);
    }

    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool
    {
        return $context instanceof CliErrorContextInterface;
    }
}
