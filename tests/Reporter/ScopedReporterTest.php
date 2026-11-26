<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Reporter;

use Componenta\Error\ConfigKey;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\ErrorListenerProvider;
use Componenta\Error\Factory\CliErrorReporterFactory;
use Componenta\Error\Factory\HttpErrorReporterFactory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ScopedReporterTest extends TestCase
{
    public function testConfiguredListenersAreIsolatedByTransport(): void
    {
        $seen = [];
        $http = new ErrorListenerProvider();
        $cli = new ErrorListenerProvider();
        $http->addListener(ErrorListener::createFrom(static function () use (&$seen): void { $seen[] = 'http'; }));
        $cli->addListener(ErrorListener::createFrom(static function () use (&$seen): void { $seen[] = 'cli'; }));
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => in_array($id, [ConfigKey::HTTP_LISTENERS, ConfigKey::CLI_LISTENERS], true));
        $container->method('get')->willReturnMap([
            [ConfigKey::HTTP_LISTENERS, $http],
            [ConfigKey::CLI_LISTENERS, $cli],
        ]);

        $httpReporter = (new HttpErrorReporterFactory())($container);
        $cliReporter = (new CliErrorReporterFactory())($container);
        $httpReporter->report(new RuntimeException(), new HttpContext(new ServerRequest('GET', '/')));
        $cliReporter->report(new RuntimeException(), new CliContext(new ArrayInput([]), new BufferedOutput()));

        self::assertSame(['http', 'cli'], $seen);
    }
}
