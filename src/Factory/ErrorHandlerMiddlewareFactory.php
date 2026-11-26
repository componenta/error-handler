<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ConfigKey;
use Componenta\Error\Handler\HttpErrorHandlerInterface;
use Componenta\Error\Http\Middleware\ErrorHandlerMiddleware;
use LogicException;
use Psr\Container\ContainerInterface;
use Componenta\Error\Internal\TypedService;
use Componenta\Config\Config;
use Componenta\Error\Http\EmergencyHttpResponseGenerator;
use Psr\Http\Message\ResponseFactoryInterface;

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

        $handler = TypedService::get($container, HttpErrorHandlerInterface::class, HttpErrorHandlerInterface::class);

        $errorLevel = $container->has(Config::class)
            ? TypedService::get($container, Config::class, Config::class)->int(ConfigKey::ERROR_LEVEL, E_ALL)
            : E_ALL;

        return new ErrorHandlerMiddleware(
            $handler,
            $errorLevel,
            $container->has(ResponseFactoryInterface::class)
                ? new EmergencyHttpResponseGenerator(TypedService::get($container, ResponseFactoryInterface::class, ResponseFactoryInterface::class))
                : null,
        );
    }
}
