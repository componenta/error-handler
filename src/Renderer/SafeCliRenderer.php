<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\Context\CliErrorContextInterface;
use Componenta\Error\Diagnostic\DiagnosticData;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Internal\ErrorCorrelation;
use Throwable;

/** Production CLI output: a generic message and the correlation ID only. */
final readonly class SafeCliRenderer implements ErrorRendererInterface
{
    public function __construct(private string $message = 'An unexpected error occurred')
    {
    }

    public function supports(Throwable $exception, ErrorContextInterface $context): bool
    {
        return $context instanceof CliErrorContextInterface;
    }

    public function render(Throwable $exception, ErrorContextInterface $context): string
    {
        $id = ErrorCorrelation::read($context);

        return DiagnosticData::terminal($this->message)
            . ($id === null ? '' : "\nError ID: " . $id);
    }
}
