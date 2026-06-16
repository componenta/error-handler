<?php

declare(strict_types=1);

namespace Componenta\Error\Context;

use Componenta\Error\Context\HttpErrorContextInterface;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HTTP error context implementation
 *
 * Provides HTTP request context for error handling.
 * Can be created from PSR-7 request or PHP superglobals.
 */
final readonly class HttpContext extends AbstractErrorContext implements HttpErrorContextInterface
{
    /**
     * Create HTTP error context
     *
     * @param ServerRequestInterface $request HTTP request
     * @param array<string|int, mixed> $attributes Additional context attributes
     */
    public function __construct(
        public ServerRequestInterface $request,
        array $attributes = [],
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create context from PHP superglobals
     *
     * Uses the provided PSR-7 server request creator to build a request from
     * PHP superglobals.
     *
     * @param array<string|int, mixed> $attributes Additional context attributes
     * @return self New context instance
     */
    public static function fromGlobals(ServerRequestCreatorInterface $creator, array $attributes = []): self
    {
        return new self($creator->fromGlobals(), $attributes);
    }

    /**
     * Create new context with additional attributes
     *
     * @param array<string|int, mixed> $attributes Attributes to add
     * @return static New instance with merged attributes
     */
    public function withAttributes(array $attributes): static
    {
        return new self(
            $this->request,
            array_merge($this->attributes, $attributes),
        );
    }
}
