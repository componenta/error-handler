<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Fixture;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Throwable;

final class ErrorRendererStub implements ErrorRendererInterface
{
    private mixed $output;

    public function __construct(callable|string $output = '')
    {
        $this->output = $output;
    }

    public function render(Throwable $exception, ErrorContextInterface $context): string
    {
        if (is_callable($this->output)) {
            return ($this->output)($exception, $context);
        }

        return $this->output;
    }

    public function supports(Throwable $exception, ErrorContextInterface $context): bool
    {
        return true;
    }
}
