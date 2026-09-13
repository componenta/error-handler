<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

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
            $rendered = $renderer->render($error, $context);
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

        $handler->handle(new RuntimeException('Original command failed'), new CliContext($input, $output));

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

        $handler->handle(new RuntimeException('Original command failed'), new CliContext($input, $output));

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

        $rendered = $renderer->render($error);

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
}
