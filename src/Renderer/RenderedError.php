<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use InvalidArgumentException;
use Stringable;

/** Immutable rendered content and its actual media type, independent of transport. */
final readonly class RenderedError implements Stringable
{
    public function __construct(public string $content, public string $contentType)
    {
        if (trim($contentType) === '' || preg_match('/[\x00-\x1f\x7f]/', $contentType)) {
            throw new InvalidArgumentException('Invalid error representation media type.');
        }
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
