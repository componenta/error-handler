<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Handler\RenderingCliErrorHandler;
use Componenta\Error\Renderer\ConsoleErrorRenderer;
use Componenta\Error\Tests\Fixture\ContractCliContext;
use ErrorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

final class ConsoleErrorRendererTest extends TestCase
{
    #[DataProvider('contextImplementations')]
    public function testRendersAttributesThroughTheCliContextContract(bool $customContext): void
    {
        $marker = implode('-', ['trace', 'provided', 'by', 'context']);
        $builtin = new CliContext(new ArrayInput([]), new BufferedOutput(), ['trace' => $marker]);
        $context = $customContext ? new ContractCliContext($builtin) : $builtin;
        $renderer = new ConsoleErrorRenderer(colors: false, terminalWidth: 100, contextLines: 0);
        $error = new RuntimeException('Original command failed');
        set_error_handler(
            static fn (int $severity, string $message, string $file, int $line): never =>
                throw new ErrorException($message, 0, $severity, $file, $line),
        );

        try {
            $rendered = $renderer->render(new ErrorOccurrence($error, 'ERR-test'), $context)->content;
        } finally {
            restore_error_handler();
        }

        self::assertStringContainsString('Original command failed', $rendered);
        self::assertStringContainsString($marker, $rendered);
    }

    #[DataProvider('inputLocations')]
    public function testPrintsTheOriginalErrorWhenInputContainsInvalidUtf8(string $parameter, string $label): void
    {
        $input = new ArrayInput(
            ['command' => 'app:run', $parameter => "safe-prefix\xB1\x31-suffix"],
            new InputDefinition([
                new InputArgument('command', InputArgument::REQUIRED),
                new InputArgument('payload', InputArgument::OPTIONAL),
                new InputOption('payload-option', null, InputOption::VALUE_OPTIONAL),
            ]),
        );
        $output = new BufferedOutput();
        $handler = new RenderingCliErrorHandler(
            new ConsoleErrorRenderer(colors: false, terminalWidth: 100, contextLines: 0),
        );

        $handler->handle(new ErrorOccurrence(new RuntimeException('Original command failed'), 'ERR-test'), new CliContext($input, $output));

        $rendered = $output->fetch();
        self::assertStringContainsString('Original command failed', $rendered);
        self::assertMatchesRegularExpression('/' . $label . ':[^\r\n]*safe-prefix[^\r\n]*1-suffix/', $rendered);
        self::assertTrue(mb_check_encoding($rendered, 'UTF-8'));
    }

    #[DataProvider('inputLocations')]
    public function testPrintsTheOriginalErrorWhenInputCannotBeEncodedAsJson(string $parameter, string $label): void
    {
        $input = new ArrayInput(
            ['command' => 'app:run', $parameter => INF],
            new InputDefinition([
                new InputArgument('command', InputArgument::REQUIRED),
                new InputArgument('payload', InputArgument::OPTIONAL),
                new InputOption('payload-option', null, InputOption::VALUE_OPTIONAL),
            ]),
        );
        $output = new BufferedOutput();
        $handler = new RenderingCliErrorHandler(
            new ConsoleErrorRenderer(colors: false, terminalWidth: 100, contextLines: 0),
        );

        $handler->handle(new ErrorOccurrence(new RuntimeException('Original command failed'), 'ERR-test'), new CliContext($input, $output));

        $rendered = $output->fetch();
        self::assertStringContainsString('Original command failed', $rendered);
        self::assertStringContainsString($label . ': (unavailable)', $rendered);
    }

    /** @return iterable<string, array{string, string}> */
    public static function inputLocations(): iterable
    {
        yield 'argument' => ['payload', 'Arguments'];
        yield 'option' => ['--payload-option', 'Options'];
    }

    /** @param class-string<\Throwable> $exceptionClass */
    #[DataProvider('exceptionCategories')]
    public function testRendersTheSpecificExceptionCategory(string $exceptionClass, string $heading): void
    {
        $renderer = new ConsoleErrorRenderer(debug: false, colors: false, terminalWidth: 100);
        $error = new $exceptionClass('The command failed');

        $rendered = $renderer->render(new ErrorOccurrence($error, 'ERR-test'), \Componenta\Error\Context\CliContext::fromArgv())->content;

        self::assertMatchesRegularExpression('/✖\h+' . preg_quote($heading, '/') . '\h*$/mu', $rendered);
        self::assertStringContainsString('The command failed', $rendered);
    }

    /** @return iterable<string, array{class-string<\Throwable>, string}> */
    public static function exceptionCategories(): iterable
    {
        yield 'argument count' => [\ArgumentCountError::class, 'Argument Count Error'];
        yield 'type' => [\TypeError::class, 'Type Error'];
        yield 'value' => [\ValueError::class, 'Value Error'];
        yield 'generic error' => [\Error::class, 'PHP Error'];
        yield 'converted error' => [\ErrorException::class, 'Error Exception'];
        yield 'runtime' => [\RuntimeException::class, 'Runtime Exception'];
        yield 'invalid argument' => [\InvalidArgumentException::class, 'Invalid Argument'];
        yield 'bounds' => [\OutOfBoundsException::class, 'Out of Bounds'];
        yield 'domain' => [\DomainException::class, 'Entity Exception'];
        yield 'logic' => [\LogicException::class, 'Logic Exception'];
        yield 'generic exception' => [\Exception::class, 'Exception'];
    }

    /** @return iterable<string, array{bool}> */
    public static function contextImplementations(): iterable
    {
        yield 'built-in context' => [false];
        yield 'interface-only context' => [true];
    }

    public function testAutomaticColorsFollowCurrentOutputWithoutRetainingPreviousDecision(): void
    {
        $previous = getenv('NO_COLOR');
        putenv('NO_COLOR');
        try {
            $renderer = new ConsoleErrorRenderer(debug: false, terminalWidth: 80);
            $error = new ErrorOccurrence(new RuntimeException('command failure'), 'ERR-colors');
            $decorated = new BufferedOutput();
            $decorated->setDecorated(true);
            $plain = new BufferedOutput();
            $plain->setDecorated(false);
            $input = new ArrayInput([]);

            $colored = $renderer->render($error, new CliContext($input, $decorated))->content;
            $uncolored = $renderer->render($error, new CliContext($input, $plain))->content;
            self::assertStringContainsString("\x1b[", $colored);
            self::assertStringNotContainsString("\x1b", $uncolored);
            self::assertSame($uncolored, preg_replace('/\x1b\[[0-9;]*m/', '', $colored));
            self::assertSame($colored, $renderer->render($error, new CliContext($input, $decorated))->content);
        } finally {
            putenv($previous === false ? 'NO_COLOR' : 'NO_COLOR=' . $previous);
        }
    }

    public function testAutomaticColorsFollowErrorOutputRatherThanStandardOutput(): void
    {
        $previous = getenv('NO_COLOR');
        putenv('NO_COLOR');
        try {
            $stderr = new BufferedOutput();
            $stderr->setDecorated(false);
            $console = $this->createStub(ConsoleOutputInterface::class);
            $console->method('isDecorated')->willReturn(true);
            $console->method('getErrorOutput')->willReturn($stderr);
            $renderer = new ConsoleErrorRenderer(debug: false, terminalWidth: 80);
            $error = new ErrorOccurrence(new RuntimeException('failure'), 'ERR-stderr');
            $context = new CliContext(new ArrayInput([]), $console);

            self::assertStringNotContainsString("\x1b", $renderer->render($error, $context)->content);
            $stderr->setDecorated(true);
            self::assertStringContainsString("\x1b[", $renderer->render($error, $context)->content);
        } finally {
            putenv($previous === false ? 'NO_COLOR' : 'NO_COLOR=' . $previous);
        }
    }

    public function testExplicitColorOptionsOverrideAutomaticDetection(): void
    {
        $previous = getenv('NO_COLOR');
        try {
            putenv('NO_COLOR=1');
            $output = new BufferedOutput();
            $output->setDecorated(true);
            $error = new ErrorOccurrence(new RuntimeException('failure'), 'ERR-override');
            $context = new CliContext(new ArrayInput([]), $output);
            self::assertStringNotContainsString("\x1b", (new ConsoleErrorRenderer(debug: false))->render($error, $context)->content);
            $output->setDecorated(false);
            self::assertStringContainsString("\x1b[", (new ConsoleErrorRenderer(debug: false, colors: true))->render($error, $context)->content);
            putenv('NO_COLOR');
            $output->setDecorated(true);
            self::assertStringNotContainsString("\x1b", (new ConsoleErrorRenderer(debug: false, colors: false))->render($error, $context)->content);
        } finally {
            putenv($previous === false ? 'NO_COLOR' : 'NO_COLOR=' . $previous);
        }
    }
}
