<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Fixture;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Renderer\RenderedError;
use Throwable;

final class ErrorRendererStub implements ErrorRendererInterface
{
    private mixed $output;

    public function __construct(callable|string $output = '')
    {
        $this->output = $output;
    }

    public function render(Throwable $exception, ErrorContextInterface $context): RenderedError
    {
        if (!is_string($this->output) && is_callable($this->output)) {
            $output = ($this->output)($exception, $context);
            return $output instanceof RenderedError ? $output : new RenderedError($output, 'text/plain; charset=utf-8');
        }

        return new RenderedError($this->output, 'text/plain; charset=utf-8');
    }

    public function supports(Throwable $exception, ErrorContextInterface $context): bool
    {
        return true;
    }
}
