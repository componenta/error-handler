<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Reporter;

use Componenta\Config\Config;
use Componenta\Config\Environment;
use Componenta\Error\ConfigKey;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\ErrorListenerProviderInterface;
use Componenta\Error\Factory\ErrorListenerProviderFactory;
use Componenta\Error\Factory\ErrorReporterFactory;
use Componenta\Error\Factory\NonReportableErrorsFactory;
use Componenta\Error\Reporter\Policy\ErrorReportingPolicyInterface;
use InvalidArgumentException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class ReportingConfigurationTest extends TestCase
{
    public function testConfiguredPolicyAndListenersAreSharedAcrossTransports(): void
    {
        $seen = [];
        $all = ErrorListener::createFrom(static function (ErrorEventInterface $event) use (&$seen): void {
            $seen[] = 'all:' . $event->error->id;
        });
        $http = ErrorListener::createFrom(static function (ErrorEventInterface $event) use (&$seen): void {
            $seen[] = 'http:' . $event->error->id;
        }, static fn (ErrorOccurrence $error, $context): bool => $context instanceof HttpContext);
        $config = new Config([
            ConfigKey::ERROR_LISTENERS => ['all', ['listener' => 'http', 'priority' => 100]],
            ConfigKey::NON_REPORTABLE_ERRORS => [InvalidArgumentException::class],
        ], new Environment([]));
        $container = $this->container([Config::class => $config, 'all' => $all, 'http' => $http]);
        $provider = (new ErrorListenerProviderFactory())($container);
        $policy = (new NonReportableErrorsFactory())($container);
        $reporter = (new ErrorReporterFactory())($this->container([
            ErrorListenerProviderInterface::class => $provider,
            ErrorReportingPolicyInterface::class => $policy,
        ]));

        $reporter->report(new ErrorOccurrence(new RuntimeException(), 'ERR-http'), new HttpContext(new ServerRequest('GET', '/')));
        $reporter->report(new ErrorOccurrence(new RuntimeException(), 'ERR-cli'), CliContext::fromArgv());
        $reporter->report(new ErrorOccurrence(new InvalidArgumentException(), 'ERR-excluded'), CliContext::fromArgv());

        self::assertSame(['http:ERR-http', 'all:ERR-http', 'all:ERR-cli'], $seen);
    }

    private function container(array $services): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => array_key_exists($id, $services));
        $container->method('get')->willReturnCallback(static fn (string $id): mixed => $services[$id]);
        return $container;
    }
}
