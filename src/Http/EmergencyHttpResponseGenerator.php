<?php

declare(strict_types=1);

namespace Componenta\Error\Http;

use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/** Independent response path: no renderer, status resolver, template or reporter. */
final readonly class EmergencyHttpResponseGenerator implements HttpErrorResponseGeneratorInterface
{
    public function __construct(private ResponseFactoryInterface $factory)
    {
    }

    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool
    {
        return $context instanceof HttpErrorContextInterface;
    }

    public function generate(ErrorOccurrence $error, HttpErrorContextInterface $context): ResponseInterface
    {
        return $this->build($context, $error->id);
    }

    /** Last-resort path if occurrence creation itself failed. */
    public function generateUncorrelated(HttpErrorContextInterface $context): ResponseInterface
    {
        return $this->build($context, null);
    }

    private function build(HttpErrorContextInterface $context, ?string $id): ResponseInterface
    {
        $response = $this->factory->createResponse(500)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Content-Type-Options', 'nosniff');
        if ($id !== null) {
            $response = $response->withHeader('X-Error-ID', $id);
        }
        if (strtoupper($context->request->getMethod()) !== 'HEAD') {
            \Componenta\Error\Internal\StreamWriter::write($response->getBody(), 'An unexpected error occurred' . ($id !== null ? "\nError ID: " . $id : ''));
        }

        return $response;
    }
}
