<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Fixture;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\Http\HttpErrorResponseGeneratorInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class HttpErrorHandlerStub implements HttpErrorResponseGeneratorInterface
{
    private mixed $renderOutput;

    /** @var Throwable[] */
    public array $handledExceptions = [];

    public function __construct(
        private readonly ?ResponseInterface $response = null,
        private readonly bool $supports = true,
        private readonly ?string $supportsException = null,
        string|callable $renderOutput = '',
    ) {
        $this->renderOutput = $renderOutput;
    }

    public function generate(
        ErrorOccurrence $error,
        HttpErrorContextInterface $context,
    ): ResponseInterface {
        $this->handledExceptions[] = $error->exception;

        return $this->response;
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
