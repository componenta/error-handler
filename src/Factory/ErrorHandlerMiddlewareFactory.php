<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ConfigKey;
use Componenta\Error\Handler\HttpErrorHandlerInterface;
use Componenta\Error\Http\Middleware\ErrorHandlerMiddleware;
use LogicException;
use Psr\Container\ContainerInterface;

final readonly class ErrorHandlerMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): ErrorHandlerMiddleware
    {
        if (!$container->has(HttpErrorHandlerInterface::class)) {
            throw new LogicException(sprintf(
                'ErrorHandlerMiddleware requires "%s". Register Componenta\\Error\\ConfigProvider or provide the handler explicitly.',
                HttpErrorHandlerInterface::class,
            ));
        }

        $handler = $container->get(HttpErrorHandlerInterface::class);

        $errorLevel = $container->has(ConfigKey::ERROR_LEVEL)
            ? $container->get(ConfigKey::ERROR_LEVEL)
            : E_ALL;

        return new ErrorHandlerMiddleware($handler, $errorLevel);
    }
}
