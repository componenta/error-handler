<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use InvalidArgumentException;

/** Body and its actual HTTP representation, selected together. */
final readonly class RenderedError
{
    public function __construct(public string $body, public string $contentType)
    {
        if ($contentType === '' || preg_match('/[\x00-\x1f\x7f]/', $contentType)) {
            throw new InvalidArgumentException('Invalid error representation media type.');
        }
    }
}
