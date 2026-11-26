<?php

declare(strict_types=1);

namespace Componenta\Error\Http;

use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\ErrorContextInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/** Independent response path: no renderer, status resolver, template or reporter. */
final readonly class EmergencyHttpResponseGenerator implements HttpErrorResponseGeneratorInterface
{
    public function __construct(private ResponseFactoryInterface $factory)
    {
    }

    public function supports(Throwable $exception, ErrorContextInterface $context): bool
    {
        return $context instanceof HttpErrorContextInterface;
    }

    public function generate(Throwable $exception, HttpErrorContextInterface $context): ResponseInterface
    {
        $response = $this->factory->createResponse(500)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
        if (strtoupper($context->request->getMethod()) !== 'HEAD') {
            $id = \Componenta\Error\Internal\ErrorCorrelation::read($context);
            $response->getBody()->write('An unexpected error occurred' . ($id !== null ? "\nError ID: " . $id : ''));
        }

        return $response;
    }
}
