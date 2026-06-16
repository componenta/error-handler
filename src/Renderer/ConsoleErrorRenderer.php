<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Context\CliErrorContextInterface;
use Throwable;

/**
 * Beautiful console error renderer with ANSI colors
 *
 * Features:
 * - Colorful box-styled exception display
 * - Stack trace with syntax highlighting
 * - Source code preview with line numbers
 * - Previous exceptions chain
 * - Debug/production modes
 *
 * @example
 * ```php
 * $renderer = ConsoleErrorRenderer::create(debug: true);
 *
 * try {
 *     // your code
 * } catch (Throwable $e) {
 *     echo $renderer->render($e, CliContext::fromArgv());
 * }
 * ```
 */
final readonly class ConsoleErrorRenderer implements ErrorRendererInterface
{
    // ═══════════════════════════════════════════════════════════════════════════
    // ANSI Codes
    // ═══════════════════════════════════════════════════════════════════════════

    private const string RESET = "\033[0m";
    private const string BOLD = "\033[1m";
    private const string DIM = "\033[2m";
    private const string ITALIC = "\033[3m";
    private const string UNDERLINE = "\033[4m";

    // Foreground colors
    private const string FG_BLACK = "\033[30m";
    private const string FG_RED = "\033[31m";
    private const string FG_GREEN = "\033[32m";
    private const string FG_YELLOW = "\033[33m";
    private const string FG_BLUE = "\033[34m";
    private const string FG_MAGENTA = "\033[35m";
    private const string FG_CYAN = "\033[36m";
    private const string FG_WHITE = "\033[37m";
    private const string FG_GRAY = "\033[90m";
    private const string FG_BRIGHT_RED = "\033[91m";
    private const string FG_BRIGHT_GREEN = "\033[92m";
    private const string FG_BRIGHT_YELLOW = "\033[93m";
    private const string FG_BRIGHT_BLUE = "\033[94m";
    private const string FG_BRIGHT_MAGENTA = "\033[95m";
    private const string FG_BRIGHT_CYAN = "\033[96m";
    private const string FG_BRIGHT_WHITE = "\033[97m";

    // Background colors
    private const string BG_RED = "\033[41m";
    private const string BG_GREEN = "\033[42m";
    private const string BG_YELLOW = "\033[43m";
    private const string BG_BLUE = "\033[44m";
    private const string BG_MAGENTA = "\033[45m";
    private const string BG_GRAY = "\033[100m";
    private const string BG_BRIGHT_RED = "\033[101m";

    // ═══════════════════════════════════════════════════════════════════════════
    // Box Drawing Characters (Unicode)
    // ═══════════════════════════════════════════════════════════════════════════

    private const string BOX_TL = '╭';
    private const string BOX_TR = '╮';
    private const string BOX_BL = '╰';
    private const string BOX_BR = '╯';
    private const string BOX_H = '─';
    private const string BOX_V = '│';
    private const string BOX_CROSS = '┼';
    private const string BOX_T_DOWN = '┬';
    private const string BOX_T_UP = '┴';
    private const string BOX_T_RIGHT = '├';
    private const string BOX_T_LEFT = '┤';

    // ═══════════════════════════════════════════════════════════════════════════
    // Icons (Unicode)
    // ═══════════════════════════════════════════════════════════════════════════

    private const string ICON_ERROR = '✖';
    private const string ICON_WARNING = '⚠';
    private const string ICON_INFO = 'ℹ';
    private const string ICON_ARROW = '->';
    private const string ICON_DOT = '●';
    private const string ICON_CHAIN = '⤷';
    private const string ICON_FILE = '📄';
    private const string ICON_FOLDER = '📁';
    private const string ICON_CODE = '❯';

    private const int DEFAULT_WIDTH = 120;
    private const int CONTEXT_LINES = 5;

    /**
     * @param bool $debug Show detailed stack trace and code preview
     * @param bool $colors Enable ANSI colors
     * @param int $terminalWidth Terminal width (0 = auto-detect)
     * @param int $contextLines Number of code context lines
     * @param bool $showPreviousExceptions Show exception chain
     */
    public function __construct(
        private bool $debug = true,
        private bool $colors = true,
        private int $terminalWidth = 0,
        private int $contextLines = self::CONTEXT_LINES,
        private bool $showPreviousExceptions = true,
    ) {}

    /**
     * Create renderer with auto-detected settings
     */
    public static function create(bool $debug = true): self
    {
        return new self(
            debug: $debug,
            colors: self::supportsColors(),
            terminalWidth: self::detectTerminalWidth(),
        );
    }

    /**
     * Create renderer for production (minimal output)
     */
    public static function production(): self
    {
        return new self(
            debug: false,
            colors: self::supportsColors(),
            terminalWidth: self::detectTerminalWidth(),
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Main render method
    // ═══════════════════════════════════════════════════════════════════════════

    public function render(Throwable $exception, ?ErrorContextInterface $context = null): string
    {
        $width = $this->getWidth();
        $output = [];

        // Header with error icon
        $output[] = '';
        $output[] = $this->renderHeader($exception, $width);
        $output[] = '';

        // Exception box
        $output[] = $this->renderExceptionBox($exception, $width);
        $output[] = '';

        if ($this->debug) {
            // Code preview
            if ($exception->getFile() && $exception->getLine()) {
                $output[] = $this->renderCodePreview(
                    $exception->getFile(),
                    $exception->getLine(),
                    $width,
                );
                $output[] = '';
            }

            // Stack trace
            $output[] = $this->renderStackTrace($exception, $width);
            $output[] = '';

            // Previous exceptions
            if ($this->showPreviousExceptions && $exception->getPrevious()) {
                $output[] = $this->renderPreviousExceptions($exception, $width);
                $output[] = '';
            }

            // Context info
            if ($context instanceof CliErrorContextInterface) {
                $output[] = $this->renderContext($context, $width);
                $output[] = '';
            }
        }

        return implode("\n", $output);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Render components
    // ═══════════════════════════════════════════════════════════════════════════

    private function renderHeader(Throwable $exception, int $width): string
    {
        $type = $this->getExceptionType($exception);
        $icon = self::ICON_ERROR;

        $header = " {$icon}  {$type} ";
        $line = str_repeat(self::BOX_H, $width);

        return $this->color(
            $line . "\n" . $this->center($header, $width) . "\n" . $line,
            self::FG_BRIGHT_RED,
            self::BOLD,
        );
    }

    private function renderExceptionBox(Throwable $exception, int $width): string
    {
        $innerWidth = $width - 4;
        $lines = [];

        // Exception class
        $class = $exception::class;
        $lines[] = $this->color($class, self::FG_BRIGHT_YELLOW, self::BOLD);
        $lines[] = '';

        // Message (wrapped)
        $message = $exception->getMessage() ?: '(no message)';
        $wrappedMessage = $this->wordWrap($message, $innerWidth);
        foreach (explode("\n", $wrappedMessage) as $msgLine) {
            $lines[] = $this->color($msgLine, self::FG_WHITE);
        }
        $lines[] = '';

        // Location
        if ($exception->getFile()) {
            $file = $this->shortenPath($exception->getFile());
            $line = $exception->getLine();
            $location = sprintf(
                '%s %s %s line %s',
                self::ICON_FILE,
                $this->color($file, self::FG_CYAN),
                $this->color(self::ICON_ARROW, self::FG_GRAY),
                $this->color((string) $line, self::FG_BRIGHT_YELLOW, self::BOLD),
            );
            $lines[] = $location;
        }

        // Code
        if ($exception->getCode() !== 0) {
            $code = sprintf(
                '%s Code: %s',
                self::ICON_INFO,
                $this->color((string) $exception->getCode(), self::FG_BRIGHT_MAGENTA),
            );
            $lines[] = $code;
        }

        return $this->box($lines, $width, self::FG_RED);
    }

    private function renderCodePreview(string $file, int $errorLine, int $width): string
    {
        if (!is_readable($file)) {
            return '';
        }

        $lines = file($file);
        if ($lines === false) {
            return '';
        }

        $start = max(1, $errorLine - $this->contextLines);
        $end = min(count($lines), $errorLine + $this->contextLines);

        $output = [];
        $maxLineNum = strlen((string) $end);

        // Header
        $shortFile = $this->shortenPath($file);
        $header = sprintf(
            ' %s %s ',
            self::ICON_CODE,
            $this->color($shortFile, self::FG_CYAN, self::BOLD),
        );
        $output[] = $this->color(self::BOX_TL . str_repeat(self::BOX_H, 2), self::FG_GRAY) .
            $header .
            $this->color(str_repeat(self::BOX_H, max(0, $width - $this->visibleLength($header) - 4)) . self::BOX_TR, self::FG_GRAY);

        // Code lines
        for ($i = $start; $i <= $end; $i++) {
            $lineContent = rtrim($lines[$i - 1] ?? '', "\r\n");
            $lineNum = str_pad((string) $i, $maxLineNum, ' ', STR_PAD_LEFT);
            $isErrorLine = ($i === $errorLine);

            // Line number
            $numColor = $isErrorLine ? self::FG_BRIGHT_RED : self::FG_GRAY;
            $lineNumStr = $this->color($lineNum, $numColor);

            // Separator
            $separator = $isErrorLine
                ? $this->color(' ' . self::ICON_ARROW . ' ', self::FG_BRIGHT_RED, self::BOLD)
                : $this->color(' ' . self::BOX_V . ' ', self::FG_GRAY);

            // Code content with highlighting
            $code = $this->highlightPhp($lineContent);

            if ($isErrorLine) {
                $availableWidth = $width - $maxLineNum - 6;
                $paddedCode = str_pad($lineContent, $availableWidth);
                $code = $this->color($paddedCode, self::FG_BRIGHT_WHITE, self::BG_BRIGHT_RED);
            }

            $prefix = $this->color(self::BOX_V . ' ', self::FG_GRAY);

            $output[] = $prefix . $lineNumStr . $separator . $code;
        }

        // Footer
        $output[] = $this->color(
            self::BOX_BL . str_repeat(self::BOX_H, $width - 2) . self::BOX_BR,
            self::FG_GRAY,
        );

        return implode("\n", $output);
    }

    private function renderStackTrace(Throwable $exception, int $width): string
    {
        $trace = $exception->getTrace();
        if (empty($trace)) {
            return '';
        }

        $output = [];

        // Header
        $header = sprintf(
            ' %s Stack Trace (%d frames) ',
            self::ICON_DOT,
            count($trace),
        );
        $output[] = $this->color(
            str_repeat(self::BOX_H, 2) . $header . str_repeat(self::BOX_H, max(0, $width - $this->visibleLength($header) - 2)),
            self::FG_BRIGHT_BLUE,
            self::BOLD,
        );
        $output[] = '';

        // Frames
        $maxFrames = min(15, count($trace));
        foreach (array_slice($trace, 0, $maxFrames) as $index => $frame) {
            $output[] = $this->renderFrame($index, $frame, $width);
        }

        if (count($trace) > $maxFrames) {
            $remaining = count($trace) - $maxFrames;
            $output[] = '';
            $output[] = $this->color(
                sprintf('   ... and %d more frame(s)', $remaining),
                self::FG_GRAY,
                self::ITALIC,
            );
        }

        return implode("\n", $output);
    }

    private function renderFrame(int $index, array $frame, int $width): string
    {
        $lines = [];

        // Frame number
        $num = $this->color(
            str_pad('#' . $index, 4, ' ', STR_PAD_LEFT),
            self::FG_GRAY,
        );

        // Function/method call
        $call = '';
        if (isset($frame['class'])) {
            $class = $this->color($frame['class'], self::FG_BRIGHT_CYAN);
            $type = $this->color($frame['type'] ?? '::', self::FG_GRAY);
            $function = $this->color($frame['function'] ?? '', self::FG_BRIGHT_YELLOW);
            $call = $class . $type . $function;
        } elseif (isset($frame['function'])) {
            $call = $this->color($frame['function'], self::FG_BRIGHT_YELLOW);
        }

        // Arguments summary
        $args = '';
        if (!empty($frame['args'])) {
            $argTypes = array_map(fn($arg) => $this->getArgType($arg), $frame['args']);
            $args = $this->color('(' . implode(', ', $argTypes) . ')', self::FG_GRAY);
        } else {
            $args = $this->color('()', self::FG_GRAY);
        }

        $lines[] = sprintf('%s %s%s', $num, $call, $args);

        // File location
        if (isset($frame['file'])) {
            $file = $this->shortenPath($frame['file']);
            $line = $frame['line'] ?? '?';
            $location = sprintf(
                '     %s %s:%s',
                $this->color(self::ICON_ARROW, self::FG_GRAY),
                $this->color($file, self::FG_CYAN),
                $this->color((string) $line, self::FG_BRIGHT_YELLOW),
            );
            $lines[] = $location;
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    private function renderPreviousExceptions(Throwable $exception, int $width): string
    {
        $output = [];

        // Header
        $header = sprintf(' %s Previous Exceptions ', self::ICON_CHAIN);
        $output[] = $this->color(
            str_repeat(self::BOX_H, 2) . $header . str_repeat(self::BOX_H, max(0, $width - $this->visibleLength($header) - 2)),
            self::FG_BRIGHT_MAGENTA,
            self::BOLD,
        );
        $output[] = '';

        $prev = $exception->getPrevious();
        $depth = 1;
        $maxDepth = 5;

        while ($prev !== null && $depth <= $maxDepth) {
            $indent = str_repeat('  ', $depth);
            $type = $this->color($prev::class, self::FG_YELLOW);
            $message = $this->truncate($prev->getMessage() ?: '(no message)', 60);

            $output[] = sprintf(
                '%s%s %s: %s',
                $indent,
                $this->color(self::ICON_CHAIN, self::FG_MAGENTA),
                $type,
                $this->color($message, self::FG_WHITE),
            );

            if ($prev->getFile()) {
                $file = $this->shortenPath($prev->getFile());
                $output[] = sprintf(
                    '%s  %s %s:%s',
                    $indent,
                    $this->color(self::ICON_ARROW, self::FG_GRAY),
                    $this->color($file, self::FG_CYAN),
                    $this->color((string) $prev->getLine(), self::FG_BRIGHT_YELLOW),
                );
            }

            $output[] = '';
            $prev = $prev->getPrevious();
            $depth++;
        }

        if ($prev !== null) {
            $output[] = $this->color('  ... more previous exceptions', self::FG_GRAY);
        }

        return implode("\n", $output);
    }

    private function renderContext(CliErrorContextInterface $context, int $width): string
    {
        $output = [];

        // Header
        $header = sprintf(' %s Context ', self::ICON_INFO);
        $output[] = $this->color(
            str_repeat(self::BOX_H, 2) . $header . str_repeat(self::BOX_H, max(0, $width - $this->visibleLength($header) - 2)),
            self::FG_BRIGHT_GREEN,
            self::BOLD,
        );
        $output[] = '';

        // Input arguments - PHP 8.4 property hooks compatible
        $input = $context->input;
        if ($input !== null) {
            $output[] = $this->color('  Input:', self::FG_GREEN, self::BOLD);

            // Command
            if (method_exists($input, 'getFirstArgument')) {
                $firstArg = $input->getFirstArgument();
                if ($firstArg) {
                    $output[] = sprintf(
                        '    %s Command: %s',
                        self::ICON_DOT,
                        $this->color($firstArg, self::FG_CYAN),
                    );
                }
            }

            // Arguments
            if (method_exists($input, 'getArguments')) {
                $args = $input->getArguments();
                if (!empty($args)) {
                    $output[] = sprintf(
                        '    %s Arguments: %s',
                        self::ICON_DOT,
                        $this->color(json_encode($args, JSON_UNESCAPED_SLASHES), self::FG_WHITE),
                    );
                }
            }

            // Options
            if (method_exists($input, 'getOptions')) {
                $options = $input->getOptions();
                if (!empty($options)) {
                    $filteredOptions = array_filter($options, fn($v) => $v !== false && $v !== null && $v !== []);
                    if (!empty($filteredOptions)) {
                        $output[] = sprintf(
                            '    %s Options: %s',
                            self::ICON_DOT,
                            $this->color(json_encode($filteredOptions, JSON_UNESCAPED_SLASHES), self::FG_WHITE),
                        );
                    }
                }
            }
        }

        // Custom attributes
        $attributes = $context->attributes;
        if (!empty($attributes)) {
            $output[] = '';
            $output[] = $this->color('  Attributes:', self::FG_GREEN, self::BOLD);
            foreach ($attributes as $key => $value) {
                $output[] = sprintf(
                    '    %s %s: %s',
                    self::ICON_DOT,
                    $this->color((string) $key, self::FG_CYAN),
                    $this->color($this->formatValue($value), self::FG_WHITE),
                );
            }
        }

        $output[] = '';

        return implode("\n", $output);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Helper methods
    // ═══════════════════════════════════════════════════════════════════════════

    private function box(array $lines, int $width, string $borderColor = self::FG_GRAY): string
    {
        $innerWidth = $width - 4;
        $output = [];

        // Top border
        $output[] = $this->color(
            self::BOX_TL . str_repeat(self::BOX_H, $width - 2) . self::BOX_TR,
            $borderColor,
        );

        // Content lines
        foreach ($lines as $line) {
            $visibleLen = $this->visibleLength($line);
            $padding = max(0, $innerWidth - $visibleLen);
            $output[] = $this->color(self::BOX_V . ' ', $borderColor) .
                $line . str_repeat(' ', $padding) .
                $this->color(' ' . self::BOX_V, $borderColor);
        }

        // Bottom border
        $output[] = $this->color(
            self::BOX_BL . str_repeat(self::BOX_H, $width - 2) . self::BOX_BR,
            $borderColor,
        );

        return implode("\n", $output);
    }

    private function color(string $text, string $fg = '', string ...$styles): string
    {
        if (!$this->colors) {
            return $text;
        }

        $codes = array_filter([$fg, ...$styles]);
        if (empty($codes)) {
            return $text;
        }

        return implode('', $codes) . $text . self::RESET;
    }

    private function visibleLength(string $text): int
    {
        return mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $text) ?? $text);
    }

    private function center(string $text, int $width): string
    {
        $visibleLen = $this->visibleLength($text);
        $padding = max(0, ($width - $visibleLen) / 2);

        return str_repeat(' ', (int) floor($padding)) . $text . str_repeat(' ', (int) ceil($padding));
    }

    private function wordWrap(string $text, int $width): string
    {
        return wordwrap($text, $width, "\n", true);
    }

    private function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 3) . '...';
    }

    private function shortenPath(string $path): string
    {
        // Try to make path relative to common roots
        $roots = [
            getcwd(),
            dirname(__DIR__, 4), // vendor parent
        ];

        foreach ($roots as $root) {
            if ($root && str_starts_with($path, $root . '/')) {
                return substr($path, strlen($root) + 1);
            }
        }

        return $path;
    }

    private function getExceptionType(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof \Error => 'PHP Error',
            $exception instanceof \ErrorException => 'Error Exception',
            $exception instanceof \TypeError => 'Type Error',
            $exception instanceof \ArgumentCountError => 'Argument Count Error',
            $exception instanceof \ValueError => 'Value Error',
            $exception instanceof \RuntimeException => 'Runtime Exception',
            $exception instanceof \LogicException => 'Logic Exception',
            $exception instanceof \InvalidArgumentException => 'Invalid Argument',
            $exception instanceof \OutOfBoundsException => 'Out of Bounds',
            $exception instanceof \DomainException => 'Entity Exception',
            default => 'Exception',
        };
    }

    private function getArgType(mixed $arg): string
    {
        return match (true) {
            is_null($arg) => $this->color('null', self::FG_MAGENTA),
            is_bool($arg) => $this->color($arg ? 'true' : 'false', self::FG_MAGENTA),
            is_int($arg) => $this->color('int', self::FG_BRIGHT_BLUE),
            is_float($arg) => $this->color('float', self::FG_BRIGHT_BLUE),
            is_string($arg) => $this->color('string', self::FG_BRIGHT_GREEN) .
                $this->color('[' . min(strlen($arg), 20) . ']', self::FG_GRAY),
            is_array($arg) => $this->color('array', self::FG_YELLOW) .
                $this->color('[' . count($arg) . ']', self::FG_GRAY),
            is_object($arg) => $this->color($this->getShortClass($arg::class), self::FG_CYAN),
            is_resource($arg) => $this->color('resource', self::FG_MAGENTA),
            default => $this->color('mixed', self::FG_GRAY),
        };
    }

    private function getShortClass(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts);
    }

    private function formatValue(mixed $value): string
    {
        return match (true) {
            is_null($value) => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            is_array($value) => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'array',
            is_object($value) => $value::class,
            default => gettype($value),
        };
    }

    /**
     * Simple PHP syntax highlighting
     */
    private function highlightPhp(string $code): string
    {
        if (!$this->colors) {
            return $code;
        }

        // Keywords
        $keywords = [
            'function', 'class', 'interface', 'trait', 'enum', 'extends', 'implements',
            'public', 'private', 'protected', 'static', 'final', 'abstract', 'readonly',
            'return', 'if', 'else', 'elseif', 'while', 'for', 'foreach', 'switch', 'case',
            'break', 'continue', 'throw', 'try', 'catch', 'finally', 'new', 'use', 'namespace',
            'const', 'true', 'false', 'null', 'match', 'fn', 'yield', 'from',
        ];

        // Highlight strings
        $code = preg_replace_callback(
            '/(["\'])(?:(?!\1|\\\\).|\\\\.)*\1/',
            fn($m) => $this->color($m[0], self::FG_BRIGHT_GREEN),
            $code,
        ) ?? $code;

        // Highlight numbers
        $code = preg_replace_callback(
            '/\b(\d+(?:\.\d+)?)\b/',
            fn($m) => $this->color($m[0], self::FG_BRIGHT_BLUE),
            $code,
        ) ?? $code;

        // Highlight variables
        $code = preg_replace_callback(
            '/(\$[a-zA-Z_][a-zA-Z0-9_]*)/',
            fn($m) => $this->color($m[0], self::FG_BRIGHT_CYAN),
            $code,
        ) ?? $code;

        // Highlight keywords
        foreach ($keywords as $kw) {
            $code = preg_replace_callback(
                '/\b(' . preg_quote($kw, '/') . ')\b/',
                fn($m) => $this->color($m[0], self::FG_MAGENTA, self::BOLD),
                $code,
            ) ?? $code;
        }

        // Highlight comments
        $code = preg_replace_callback(
            '/(\/\/.*$|\/\*.*?\*\/|#.*$)/m',
            fn($m) => $this->color($m[0], self::FG_GRAY, self::ITALIC),
            $code,
        ) ?? $code;

        return $code;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Terminal detection
    // ═══════════════════════════════════════════════════════════════════════════

    private function getWidth(): int
    {
        if ($this->terminalWidth > 0) {
            return $this->terminalWidth;
        }

        return self::detectTerminalWidth();
    }

    private static function detectTerminalWidth(): int
    {
        // Try tput
        if (function_exists('exec')) {
            $width = trim((string) @exec('tput cols 2>/dev/null'));
            if (is_numeric($width) && (int) $width > 0) {
                return min((int) $width, 200);
            }
        }

        // Try stty
        if (function_exists('exec')) {
            $output = @exec('stty size 2>/dev/null');
            if ($output && preg_match('/\d+\s+(\d+)/', $output, $matches)) {
                return min((int) $matches[1], 200);
            }
        }

        // Environment variable
        $columns = getenv('COLUMNS');
        if ($columns !== false && is_numeric($columns)) {
            return min((int) $columns, 200);
        }

        return self::DEFAULT_WIDTH;
    }

    private static function supportsColors(): bool
    {
        // NO_COLOR convention
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        // Windows
        if (DIRECTORY_SEPARATOR === '\\') {
            return getenv('ANSICON') !== false ||
                getenv('ConEmuANSI') === 'ON' ||
                getenv('TERM') === 'xterm' ||
                (function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT));
        }

        // TERM check
        $term = getenv('TERM');
        if ($term === false || $term === 'dumb') {
            return false;
        }

        // TTY check
        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }

    public function supports(Throwable $exception, ErrorContextInterface $context): bool
    {
        return $context instanceof CliErrorContextInterface;
    }
}
