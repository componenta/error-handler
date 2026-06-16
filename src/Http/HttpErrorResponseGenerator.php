<?php

declare(strict_types=1);

namespace Componenta\Error\Http;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Context\ErrorContextAttribute;
use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\Renderer\SafeRenderer;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final readonly class HttpErrorResponseGenerator implements HttpErrorResponseGeneratorInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private ErrorRendererInterface $renderer = new SafeRenderer(),
        private HttpStatusResolverInterface $statusResolver = new HttpStatusResolver(),
    ) {
    }

    public function supports(Throwable $exception, ErrorContextInterface $context): bool
    {
        return $context instanceof HttpErrorContextInterface;
    }

    public function generate(Throwable $exception, HttpErrorContextInterface $context): ResponseInterface
    {
        $statusCode = $this->statusResolver->resolve($exception, $context);
        $context = $context->withAttribute(ErrorContextAttribute::HTTP_STATUS_CODE, $statusCode);
        $content = $this->renderer->render($exception, $context);
        $response = $this->responseFactory
            ->createResponse($statusCode)
            ->withHeader('Content-Type', $this->getContentType($context));

        $response->getBody()->write($content);

        return $response;
    }

    private function getContentType(HttpErrorContextInterface $context): string
    {
        $accept = $context->request->getHeaderLine('Accept');

        if (str_contains($accept, 'application/json')) {
            return 'application/json; charset=utf-8';
        }

        return 'text/html; charset=utf-8';
    }
}
