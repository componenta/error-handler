<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Throwable;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Componenta\Error\Context\CliContext;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Renderer\CompositeErrorRenderer;

#[TestDox('CompositeErrorRenderer')]
final class CompositeErrorRendererTest extends TestCase
{
    public function testRenderDelegatesToFirstSupportingRenderer(): void
    {
        $notSupporting = new class implements ErrorRendererInterface {
            public function supports(Throwable $exception, ErrorContextInterface $context): bool { return false; }
            public function render(Throwable $exception, ErrorContextInterface $context): string { return 'not-supporting'; }
        };

        $supporting = new class implements ErrorRendererInterface {
            public function supports(Throwable $exception, ErrorContextInterface $context): bool { return true; }
            public function render(Throwable $exception, ErrorContextInterface $context): string { return 'supporting'; }
        };

        $composite = new CompositeErrorRenderer();
        $composite->addRenderer($notSupporting);
        $composite->addRenderer($supporting);

        $output = $composite->render(new RuntimeException(), CliContext::fromArgv());

        self::assertSame('supporting', $output);
    }

    public function testRenderUsesHigherPriorityFirst(): void
    {
        $low = new class implements ErrorRendererInterface {
            public function supports(Throwable $exception, ErrorContextInterface $context): bool { return true; }
            public function render(Throwable $exception, ErrorContextInterface $context): string { return 'low'; }
        };

        $high = new class implements ErrorRendererInterface {
            public function supports(Throwable $exception, ErrorContextInterface $context): bool { return true; }
            public function render(Throwable $exception, ErrorContextInterface $context): string { return 'high'; }
        };

        $composite = new CompositeErrorRenderer();
        $composite->addRenderer($low, priority: 0);
        $composite->addRenderer($high, priority: 100);

        $output = $composite->render(new RuntimeException(), CliContext::fromArgv());

        self::assertSame('high', $output);
    }

    public function testRenderUsesFallbackWhenNoneSupports(): void
    {
        $fallback = new class implements ErrorRendererInterface {
            public function supports(Throwable $exception, ErrorContextInterface $context): bool { return true; }
            public function render(Throwable $exception, ErrorContextInterface $context): string { return 'fallback'; }
        };

        $notSupporting = new class implements ErrorRendererInterface {
            public function supports(Throwable $exception, ErrorContextInterface $context): bool { return false; }
            public function render(Throwable $exception, ErrorContextInterface $context): string { return 'not-supporting'; }
        };

        $composite = new CompositeErrorRenderer($fallback);
        $composite->addRenderer($notSupporting);

        $output = $composite->render(new RuntimeException(), CliContext::fromArgv());

        self::assertSame('fallback', $output);
    }
}