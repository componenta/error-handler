<?php

declare(strict_types=1);

namespace Componenta\Error\Event\Attribute;

use Attribute;
use InvalidArgumentException;
use Throwable;

/** Registers a listener for an exception type, including its subtypes. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class ListenTo
{
    /** @var class-string<Throwable> */
    public string $exception;

    /** @param string $exception Throwable class or interface name. */
    public function __construct(
        string $exception,
        public int $priority = 0,
    ) {
        if (!is_a($exception, Throwable::class, true)) {
            throw new InvalidArgumentException('ListenTo requires a Throwable type.');
        }
        $this->exception = $exception;
    }
}
