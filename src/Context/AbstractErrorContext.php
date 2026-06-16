<?php

declare(strict_types=1);

namespace Componenta\Error\Context;

use Componenta\Config\DefaultValue;
use Componenta\Error\ErrorContextInterface;

/**
 * Abstract base class for error contexts
 *
 * Provides common attribute storage and manipulation functionality
 * for all error context implementations. Subclasses should extend
 * this class and add their specific context properties.
 *
 * This class is immutable - withAttribute() and withAttributes()
 * return new instances with the modified attributes.
 */
abstract readonly class AbstractErrorContext implements ErrorContextInterface
{
    /**
     * @param array<string|int, mixed> $attributes Initial context attributes
     */
    public function __construct(
        public array $attributes = [],
    ) {
    }

    /**
     * Get all attributes
     *
     * @return array<string|int, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Create new context with additional attribute
     *
     * @param string|int $key Attribute key
     * @param mixed $value Attribute value
     * @return static New instance with attribute added
     */
    public function withAttribute(string|int $key, mixed $value): static
    {
        return $this->withAttributes([$key => $value]);
    }

    /**
     * Create new context with multiple attributes
     *
     * @param array<string|int, mixed> $attributes Attributes to add
     * @return static New instance with attributes added
     */
    abstract public function withAttributes(array $attributes): static;

    /**
     * Get specific attribute value
     *
     * @param string|int $key Attribute key
     * @param mixed $default Default value if attribute not found
     * @return mixed Attribute value or default
     * @throws \InvalidArgumentException If attribute not found and no default provided
     */
    public function getAttribute(string|int $key, mixed $default = DefaultValue::None): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        if ($default === DefaultValue::None) {
            throw new \InvalidArgumentException(
                sprintf('Attribute "%s" not found in error context', $key)
            );
        }

        return $default;
    }
}