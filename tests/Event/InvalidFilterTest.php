<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Event;

use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Renderer\CompositeErrorRenderer;
use Componenta\Error\Reporter\ErrorReporter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InvalidFilterTest extends TestCase
{
    public function testListenerRejectsInvalidClassFilterDuringConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ErrorListener::createFrom(static function (): void {}, ['not a throwable class']);
    }

    public function testReporterRejectsInvalidClassFilterDuringConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ErrorReporter())->addNonReportableError('not a throwable class');
    }

    public function testCompositeRejectsIndirectCycles(): void
    {
        $first = new CompositeErrorRenderer();
        $second = new CompositeErrorRenderer($first);
        $this->expectException(InvalidArgumentException::class);
        $first->addRenderer($second);
    }
}
