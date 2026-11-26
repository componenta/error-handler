<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Fixture;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Context\CliErrorContextInterface;
use Componenta\Error\Handler\CliErrorHandlerInterface;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Throwable;

final class CliErrorHandlerStub implements CliErrorHandlerInterface
{
    private mixed $renderOutput;

    /** @var Throwable[] */
    public array $handledExceptions = [];

    public function __construct(
        private readonly bool $supports = true,
        private readonly ?string $supportsException = null,
        string|callable $renderOutput = '',
    ) {
        $this->renderOutput = $renderOutput;
    }

    public function handle(
        ErrorOccurrence $error,
        CliErrorContextInterface $context,
    ): void {
        $this->handledExceptions[] = $error->exception;
        $context->output->writeln($this->render($error, $context));
    }

    public function render(ErrorOccurrence $error, ErrorContextInterface $context): string
    {
        if (is_callable($this->renderOutput)) {
            return ($this->renderOutput)($error, $context);
        }

        return $this->renderOutput;
    }

    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool
    {
        if ($this->supportsException !== null) {
            return $error->exception instanceof $this->supportsException;
        }
        return $this->supports;
    }

    public static function renderer(callable|string $callable = ''): ErrorRendererInterface
    {
        if ($callable === '') {
            $callable = static fn(ErrorOccurrence $error, ErrorContextInterface $context): string => $error->exception::class;
        }

        return new ErrorRendererStub($callable);
    }
}
