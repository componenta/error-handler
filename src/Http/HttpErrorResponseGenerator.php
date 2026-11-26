<?php

declare(strict_types=1);

namespace Componenta\Error\Http;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Context\ErrorContextAttribute;
use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\Renderer\SafeRenderer;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class HttpErrorResponseGenerator implements HttpErrorResponseGeneratorInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private ErrorRendererInterface $renderer = new SafeRenderer(),
        private HttpStatusResolverInterface $statusResolver = new HttpStatusResolver(),
    ) {
    }

    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool
    {
        return $context instanceof HttpErrorContextInterface;
    }

    public function generate(ErrorOccurrence $error, HttpErrorContextInterface $context): ResponseInterface
    {
        $exception = $error->exception;
        $statusCode = $this->statusResolver->resolve($exception, $context);
        if ($statusCode < 400 || $statusCode > 599) {
            throw new \UnexpectedValueException('An HTTP error status must be between 400 and 599.');
        }
        $context = $context->withAttribute(ErrorContextAttribute::HTTP_STATUS_CODE, $statusCode);
        // Resolve the status once before testing a renderer that may depend
        // on it. A declined representation does not make this generator
        // incapable of producing a safe HTTP error response.
        $renderer = $this->renderer->supports($error, $context)
            ? $this->renderer : new SafeRenderer();
        $rendered = $renderer->render($error, $context);

        $response = $this->responseFactory->createResponse($statusCode);

        if ($exception instanceof HttpHeadersAwareInterface || $exception instanceof \Componenta\Http\Exception\HttpException) {
            foreach ($exception->headers as $name => $value) {
                if (strtolower($name) === 'upgrade' && $statusCode === 426
                    && $context->request->getProtocolVersion() === '1.1') {
                    $response = $response->withHeader('Upgrade', $value)->withHeader('Connection', 'Upgrade');
                    continue;
                }
                // Representation and transport framing are owned by the generator.
                $field = strtolower($name);
                if ($field === 'content-range' && $statusCode !== 416) {
                    continue;
                }
                if (!in_array($field, [
                    'content-type', 'content-length', 'content-encoding', 'transfer-encoding',
                    'connection', 'proxy-connection', 'keep-alive', 'te', 'trailer', 'upgrade', 'cache-control',
                    'content-digest', 'repr-digest', 'digest', 'content-md5',
                    'x-content-type-options',
                ], true)) {
                    $response = $response->withHeader($name, $value);
                }
            }
        }

        $response = $response
            ->withHeader('Content-Type', $rendered->contentType)
            ->withHeader('X-Error-ID', $error->id)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Content-Type-Options', 'nosniff');

        // Vary is either "*" or a list of field names; never append a field
        // to the wildcard. Avoid duplicating an existing Accept token.
        $vary = array_map(static fn (string $name): string => strtolower(trim($name)), explode(',', $response->getHeaderLine('Vary')));
        if (in_array('*', $vary, true)) {
            $response = $response->withHeader('Vary', '*');
        } elseif (!in_array('accept', $vary, true)) {
            $response = $response->withAddedHeader('Vary', 'Accept');
        }

        if (strtoupper($context->request->getMethod()) !== 'HEAD') {
            \Componenta\Error\Internal\StreamWriter::write($response->getBody(), $rendered->content);
        }

        return $response;
    }
}
