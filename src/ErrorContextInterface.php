<?php

declare(strict_types=1);

namespace Componenta\Error;

use Componenta\Config\DefaultValue;
use InvalidArgumentException;

/**
 * Base interface for error contexts
 *
 * Defines common methods for accessing context attributes.
 * Implementations provide environment-specific information (HTTP, CLI, etc.).
 */
interface ErrorContextInterface
{
    /**
     * Get all attributes
     *
     * @return array<string|int, mixed>
     */
    public function getAttributes(): array;

    /**
     * Get attribute value by key
     *
     * @param string|int $key Attribute key
     * @param mixed $default Default value if key not found
     * @return mixed Attribute value or default
     * @throws InvalidArgumentException If key not found and no default provided
     */
    public function getAttribute(string|int $key, mixed $default = DefaultValue::None): mixed;

    /**
     * Create new context with single attribute added
     *
     * @param string|int $key Attribute key
     * @param mixed $value Attribute value
     * @return static New instance with attribute added
     */
    public function withAttribute(string|int $key, mixed $value): static;

    /**
     * Create new context with multiple attributes added
     *
     * @param array<string|int, mixed> $attributes Attributes to add
     * @return static New instance with attributes added
     */
    public function withAttributes(array $attributes): static;
}
