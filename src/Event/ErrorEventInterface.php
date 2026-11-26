<?php

declare(strict_types=1);

namespace Componenta\Error\Event;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use DateTimeImmutable;

interface ErrorEventInterface
{
    public ErrorOccurrence $error { get; }
    public ErrorContextInterface $context { get; }
    public DateTimeImmutable $timestamp { get; }
}
