<?php

declare(strict_types=1);

namespace Componenta\Error\Handler;

use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\ErrorContextInterface;
use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Defers the expensive HTTP error-handler graph until an error is actually handled.
 */
final class LazyHttpErrorHandler implements HttpErrorHandlerInterface
{
    private ?HttpErrorHandlerInterface $handler = null;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $serviceId = HttpErrorHandlerInterface::class,
    ) {
    }

    public function supports(Throwable $exception, ErrorContextInterface $context): bool
    {
        return $this->handler()->supports($exception, $context);
    }

    public function handle(Throwable $exception, HttpErrorContextInterface $context): ResponseInterface
    {
        return $this->handler()->handle($exception, $context);
    }

    private function handler(): HttpErrorHandlerInterface
    {
        if ($this->handler !== null) {
            return $this->handler;
        }

        $handler = $this->container->get($this->serviceId);

        if (!$handler instanceof HttpErrorHandlerInterface) {
            throw new LogicException(sprintf(
                'Service "%s" must implement "%s".',
                $this->serviceId,
                HttpErrorHandlerInterface::class,
            ));
        }

        return $this->handler = $handler;
    }
}
