<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Fixture;

use Componenta\Config\DefaultValue;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\CliErrorContextInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Exposes the CLI context contract without the concrete attributes property. */
final class ContractCliContext implements CliErrorContextInterface
{
    public InputInterface $input {
        get => $this->context->input;
    }

    public OutputInterface $output {
        get => $this->context->output;
    }

    public function __construct(private readonly CliContext $context)
    {
    }

    public function getAttributes(): array
    {
        return $this->context->getAttributes();
    }

    public function getAttribute(string|int $key, mixed $default = DefaultValue::None): mixed
    {
        return $this->context->getAttribute($key, $default);
    }

    public function withAttribute(string|int $key, mixed $value): static
    {
        return new self($this->context->withAttribute($key, $value));
    }

    public function withAttributes(array $attributes): static
    {
        return new self($this->context->withAttributes($attributes));
    }
}
