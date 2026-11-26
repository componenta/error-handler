<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\Context\CliErrorContextInterface;
use Componenta\Error\Diagnostic\DiagnosticData;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;

/** Production CLI output: a generic message and the correlation ID only. */
final readonly class SafeCliRenderer implements ErrorRendererInterface
{
    public function __construct(private string $message = 'An unexpected error occurred')
    {
    }

    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool
    {
        return $context instanceof CliErrorContextInterface;
    }

    public function render(ErrorOccurrence $error, ErrorContextInterface $context): RenderedError
    {
        return new RenderedError(
            DiagnosticData::terminal($this->message) . "\nError ID: " . $error->id,
            'text/plain; charset=utf-8',
        );
    }
}
