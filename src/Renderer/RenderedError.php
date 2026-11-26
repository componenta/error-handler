<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use InvalidArgumentException;
use Stringable;

/** Immutable rendered content with a concrete media type, independent of transport. */
final readonly class RenderedError implements Stringable
{
    public function __construct(public string $content, public string $contentType)
    {
        // RFC 9110 sections 5.6 and 8.3: one type/subtype and optional parameters.
        // This sender-side contract rejects controls and empty parameter slots.
        $token = '[!#$%&\'*+\-.^_\x60|~0-9A-Za-z]+';
        $mediaToken = '[!#$%&\'+\-.^_\x60|~0-9A-Za-z]+';
        $quoted = '"(?:[\x20-\x21\x23-\x5b\x5d-\x7e\x80-\xff]|\\\\[\x20-\x7e\x80-\xff])*"';
        $pattern = '@\A' . $mediaToken . '/' . $mediaToken
            . '(?: *; *' . $token . '=(?:' . $token . '|' . $quoted . '))*\z@D';

        if (preg_match($pattern, $contentType) !== 1) {
            throw new InvalidArgumentException('Expected a concrete error representation media type.');
        }
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
