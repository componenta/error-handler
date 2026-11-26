<?php

declare(strict_types=1);

namespace Componenta\Error\Tests;

use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\ContainerFactory;
use Componenta\Error\ConfigProvider;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Handler\CliErrorHandlerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ContainerIntegrationTest extends TestCase
{
    public function testRegisteredHandlerRendersAnErrorWithTheRuntimeConfig(): void
    {
        $composition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider());
        $container = (new ContainerFactory())->create($composition->config, $composition->dependencies);
        $output = new BufferedOutput();

        $container->get(CliErrorHandlerInterface::class)->handle(
            new RuntimeException('configuration compatibility failure'),
            new CliContext(new ArrayInput([]), $output),
        );

        $rendered = $output->fetch();
        self::assertStringContainsString('An unexpected error occurred', $rendered);
        self::assertStringContainsString('Error ID: ERR-', $rendered);
        self::assertStringNotContainsString('configuration compatibility failure', $rendered);
    }
}
