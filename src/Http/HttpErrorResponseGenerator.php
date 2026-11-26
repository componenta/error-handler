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
        if ($statusCode < 400 || $statusCode > 599) {
            throw new \UnexpectedValueException('An HTTP error status must be between 400 and 599.');
        }
        $context = $context->withAttribute(ErrorContextAttribute::HTTP_STATUS_CODE, $statusCode);
        $rendered = $this->renderer->render($exception, $context);

        $response = $this->responseFactory->createResponse($statusCode);

        if ($exception instanceof HttpHeadersAwareInterface || $exception instanceof \Componenta\Http\Exception\HttpException) {
            foreach ($exception->headers as $name => $value) {
                if (strtolower($name) === 'upgrade' && $statusCode === 426
                    && $context->request->getProtocolVersion() === '1.1') {
                    $response = $response->withHeader('Upgrade', $value)->withHeader('Connection', 'Upgrade');
                    continue;
                }
                // Representation and transport framing are owned by the generator.
                if (!in_array(strtolower($name), [
                    'content-type', 'content-length', 'content-encoding', 'transfer-encoding',
                    'connection', 'keep-alive', 'trailer', 'upgrade', 'cache-control',
                    'x-content-type-options',
                ], true)) {
                    $response = $response->withHeader($name, $value);
                }
            }
        }

        $response = $response
            ->withHeader('Content-Type', $rendered->contentType)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withAddedHeader('Vary', 'Accept');

        if (strtoupper($context->request->getMethod()) !== 'HEAD') {
            \Componenta\Error\Internal\StreamWriter::write($response->getBody(), $rendered->content);
        }

        return $response;
    }
}
