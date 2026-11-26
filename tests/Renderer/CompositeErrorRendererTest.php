<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\ErrorOccurrence;
use Throwable;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Componenta\Error\Context\CliContext;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Renderer\RenderedError;
use Componenta\Error\Renderer\CompositeErrorRenderer;

#[TestDox('CompositeErrorRenderer')]
final class CompositeErrorRendererTest extends TestCase
{
    public function testRenderDelegatesToFirstSupportingRenderer(): void
    {
        $notSupporting = new class implements ErrorRendererInterface {
            public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool { return false; }
            public function render(ErrorOccurrence $error, ErrorContextInterface $context): RenderedError { return new RenderedError('not-supporting', 'text/plain; charset=utf-8'); }
        };

        $supporting = new class implements ErrorRendererInterface {
            public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool { return true; }
            public function render(ErrorOccurrence $error, ErrorContextInterface $context): RenderedError { return new RenderedError('supporting', 'text/plain; charset=utf-8'); }
        };

        $composite = new CompositeErrorRenderer();
        $composite->addRenderer($notSupporting);
        $composite->addRenderer($supporting);

        $output = $composite->render(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), CliContext::fromArgv())->content;

        self::assertSame('supporting', $output);
    }

    public function testRenderUsesHigherPriorityFirst(): void
    {
        $low = new class implements ErrorRendererInterface {
            public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool { return true; }
            public function render(ErrorOccurrence $error, ErrorContextInterface $context): RenderedError { return new RenderedError('low', 'text/plain; charset=utf-8'); }
        };

        $high = new class implements ErrorRendererInterface {
            public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool { return true; }
            public function render(ErrorOccurrence $error, ErrorContextInterface $context): RenderedError { return new RenderedError('high', 'text/plain; charset=utf-8'); }
        };

        $composite = new CompositeErrorRenderer();
        $composite->addRenderer($low, priority: 0);
        $composite->addRenderer($high, priority: 100);

        $output = $composite->render(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), CliContext::fromArgv())->content;

        self::assertSame('high', $output);
    }

    public function testRenderUsesFallbackWhenNoneSupports(): void
    {
        $fallback = new class implements ErrorRendererInterface {
            public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool { return true; }
            public function render(ErrorOccurrence $error, ErrorContextInterface $context): RenderedError { return new RenderedError('fallback', 'text/plain; charset=utf-8'); }
        };

        $notSupporting = new class implements ErrorRendererInterface {
            public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool { return false; }
            public function render(ErrorOccurrence $error, ErrorContextInterface $context): RenderedError { return new RenderedError('not-supporting', 'text/plain; charset=utf-8'); }
        };

        $composite = new CompositeErrorRenderer($fallback);
        $composite->addRenderer($notSupporting);

        $output = $composite->render(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), CliContext::fromArgv())->content;

        self::assertSame('fallback', $output);
    }
}