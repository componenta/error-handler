<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Fixture;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Event\Attribute\ListenTo;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListenerInterface;
use RuntimeException;
use Throwable;

#[ListenTo(Throwable::class)]
#[ListenTo(RuntimeException::class, priority: 100)]
final class BenchmarkAttributedListener implements ErrorListenerInterface
{
    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool { return true; }
    public function handleEvent(ErrorEventInterface $event): void {}
}
