<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Context\HttpErrorContextInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Pretty HTML error renderer with stack trace visualization
 *
 * Extracts error data and renders it using a template file.
 * Self-contained diagnostic page without remote assets.
 */
final class PrettyPageRenderer implements HttpErrorRendererInterface
{
    private const int CONTEXT_LINES = 12;
    private const int DEFAULT_MAX_STRING_LENGTH = 200;
    private const int DEFAULT_MAX_ARRAY_DEPTH = 3;
    private const int DEFAULT_MAX_BODY_LENGTH = 4096;
    private const int DEFAULT_MAX_FRAMES = 100;
    private const int DEFAULT_MAX_EXCEPTIONS = 10;

    /**
     * Available themes
     */
    public const string THEME_DARK = 'dark';
    public const string THEME_LIGHT = 'light';

    /** @var array<string, string> Cache for shortened paths */
    private array $shortPathCache = [];

    /** @var array<string, string> Cache for base paths */
    private array $basePaths;

    /**
     * Built-in search providers
     */
    public const array SEARCH_GOOGLE = [
        'name' => 'Google',
        'url' => 'https://www.google.com/search?q=%s',
        'icon' => '<svg viewBox="0 0 24 24"><path fill="#4285f4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34a853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#fbbc05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#ea4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>',
        'class' => 'google',
    ];

    public const array SEARCH_STACKOVERFLOW = [
        'name' => 'Stack Overflow',
        'url' => 'https://stackoverflow.com/search?q=%s',
        'icon' => '<svg viewBox="0 0 120 120"><path fill="#bcbbbb" d="M84.4 93.8V70.6h7.7v30.9H22.6V70.6h7.7v23.2z"/><path fill="#f48024" d="M38.8 68.4l37.8 7.9 1.6-7.6-37.8-7.9-1.6 7.6zm5-18l35 16.3 3.2-7-35-16.4-3.2 7.1zm9.7-17.2l29.7 24.7 4.9-5.9-29.7-24.7-4.9 5.9zm19.2-18.3l-6.2 4.6 23 31 6.2-4.6-23-31zM38 86h38.6v-7.7H38V86z"/></svg>',
        'class' => 'stackoverflow',
    ];

    public const array SEARCH_HABR = [
        'name' => 'Habr Q&A',
        'url' => 'https://qna.habr.com/search?q=%s',
        'icon' => '<svg viewBox="0 0 24 24"><path fill="#77a2b6" d="M21.77 9.91l-2.12-2.12 1.41-1.42a1 1 0 0 0 0-1.41L18.93 2.83a1 1 0 0 0-1.42 0l-1.41 1.41L14 2.12a1 1 0 0 0-1.42 0L2.12 12.59a1 1 0 0 0 0 1.41l2.12 2.12-1.41 1.42a1 1 0 0 0 0 1.41l2.13 2.12a1 1 0 0 0 1.41 0l1.42-1.41 2.12 2.12a1 1 0 0 0 1.41 0l10.45-10.46a1 1 0 0 0 0-1.41zM12 18.54L5.46 12 12 5.46 18.54 12z"/></svg>',
        'class' => 'habr',
    ];

    /**
     * Built-in editor configurations
     */
    public const array EDITOR_PHPSTORM = [
        'name' => 'PhpStorm',
        'url' => 'jetbrains://php-storm/navigate/reference?project=&path=%file%:%line%',
    ];

    public const array EDITOR_VSCODE = [
        'name' => 'VS Code',
        'url' => 'vscode://file/%file%:%line%',
    ];

    public const array EDITOR_SUBLIME = [
        'name' => 'Sublime Text',
        'url' => 'subl://open?url=file://%file%&line=%line%',
    ];

    public const array EDITOR_ATOM = [
        'name' => 'Atom',
        'url' => 'atom://open?file=%file%&line=%line%',
    ];

    /**
     * @param bool $debug Show detailed information (stack trace, code, etc.)
     * @param string $applicationName Application name for branding
     * @param string $accentColor Primary accent color (hex)
     * @param string $errorColor Error highlight color (hex)
     * @param string $theme Theme name ('dark' or 'light')
     * @param ?string $templatePath Custom template path (null for default)
     * @param list<array{name: string, url: string, icon: string, class?: string}> $searchProviders Search button configs
     * @param ?array{name: string, url: string} $editor Editor config for "Open in IDE"
     * @param ?int $maxFrames Maximum stack frames to show (null = unlimited)
     * @param ?int $maxExceptions Maximum exception chain depth (null = unlimited)
     * @param int $maxStringLength Maximum string length before truncation
     * @param int $maxArrayDepth Maximum array/object nesting depth
     * @param int $maxBodyLength Maximum request body length to display
     */
    public function __construct(
        private readonly bool $debug = true,
        private readonly string $applicationName = 'componenta/error-handler',
        private readonly string $accentColor = '#6A8FD9',
        private readonly string $errorColor = '#ef4444',
        private readonly string $theme = 'dark',
        private readonly ?string $templatePath = null,
        private readonly array $searchProviders = [],
        private readonly ?array $editor = null,
        private readonly ?int $maxFrames = self::DEFAULT_MAX_FRAMES,
        private readonly ?int $maxExceptions = self::DEFAULT_MAX_EXCEPTIONS,
        private readonly int $maxStringLength = self::DEFAULT_MAX_STRING_LENGTH,
        private readonly int $maxArrayDepth = self::DEFAULT_MAX_ARRAY_DEPTH,
        private readonly int $maxBodyLength = self::DEFAULT_MAX_BODY_LENGTH,
        private readonly bool $captureJsonBody = false,
        private readonly bool $includeSource = true,
    ) {
        if (($maxFrames !== null && $maxFrames < 1) || ($maxExceptions !== null && $maxExceptions < 1)
            || $maxStringLength < 1 || $maxArrayDepth < 0 || $maxBodyLength < 1 || $maxBodyLength > 1_048_576) {
            throw new \InvalidArgumentException('Invalid diagnostic limits.');
        }
        $this->basePaths = $this->initBasePaths();
    }

    /**
     * Initialize base paths for path shortening
     *
     * @return array<string, string>
     */
    private function initBasePaths(): array
    {
        $paths = [];

        $cwd = getcwd();
        if ($cwd !== false) {
            $realCwd = realpath($cwd);
            if ($realCwd !== false) {
                $paths['cwd'] = $realCwd;
            }
        }

        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        if (is_string($docRoot) && $docRoot !== '') {
            $realDocRoot = realpath($docRoot);
            if ($realDocRoot !== false) {
                $paths['docRoot'] = $realDocRoot;
            }
        }

        return $paths;
    }

    /**
     * Check if this renderer supports the given exception and context
     */
    public function supports(Throwable $exception, ErrorContextInterface $context): bool
    {
        return $context instanceof HttpErrorContextInterface;
    }

    /**
     * Render exception as HTML string
     */
    public function render(Throwable $exception, ErrorContextInterface $context): string
    {
        if (!$context instanceof HttpErrorContextInterface) {
            throw new \InvalidArgumentException('PrettyPageRenderer requires HTTP error context.');
        }

        if (!$this->debug) {
            return (new SafeRenderer())->render($exception, $context);
        }

        // Prepare template payload
        $payload = $this->buildPayload($exception, $context);

        // Render template
        return $this->renderTemplate($payload);
    }

    /**
     * Prepare all data for the template
     *
     * @return array<string, mixed>
     */
    private function buildPayload(Throwable $exception, HttpErrorContextInterface $context): array
    {
        $request = $context->request;
        $serverParams = $request->getServerParams();
        $requestTime = $serverParams['REQUEST_TIME'] ?? null;
        $remoteAddress = $serverParams['REMOTE_ADDR'] ?? null;

        // Build exception chain info (for header display)
        $exceptionChain = $this->buildExceptionChain($exception);

        // Build unified stack trace including all exceptions
        $frames = $this->buildUnifiedFrames($exception);

        return [
            // Config
            'debug' => $this->debug,
            'errorId' => \Componenta\Error\Internal\ErrorCorrelation::read($context),
            'applicationName' => $this->applicationName,
            'accentColor' => $this->accentColor,
            'errorColor' => $this->errorColor,

            // Exception chain (for header info)
            'exceptionChain' => $exceptionChain,

            // Current exception (first in chain)
            'exception' => $exceptionChain[0],

            // Unified stack trace frames
            'frames' => $frames,

            // Request data
            'request' => [
                'method' => $request->getMethod(),
                'uri' => (string) $request->getUri()->withUserInfo('')->withQuery('')->withFragment(''),
                'path' => $request->getUri()->getPath(),
                'query' => '',
                'ip' => is_string($remoteAddress) ? $remoteAddress : 'unknown',
                'time' => date('Y-m-d H:i:s', is_int($requestTime) ? $requestTime : time()),
            ],

            // GET parameters
            'getData' => \Componenta\Error\Diagnostic\DiagnosticData::sanitize($request->getQueryParams()),

            // POST parameters
            'postData' => \Componenta\Error\Diagnostic\DiagnosticData::sanitize($request->getParsedBody()),

            // Cookies
            'cookies' => array_fill_keys(array_keys(array_slice($request->getCookieParams(), 0, 100, true)), \Componenta\Error\Diagnostic\DiagnosticData::REDACTED),

            // Uploaded files
            'files' => $this->formatUploadedFiles($request->getUploadedFiles()),

            // Raw body
            'rawBody' => $this->captureJsonBody ? $this->getRawBody($request) : null,

            // Headers
            'headers' => $this->flattenHeaders($request->getHeaders()),

            // Server info
            'server' => [
                'phpVersion' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'serverSoftware' => $serverParams['SERVER_SOFTWARE'] ?? 'unknown',
                'documentRoot' => $serverParams['DOCUMENT_ROOT'] ?? getcwd() ?: 'unknown',
                'memoryUsage' => $this->formatBytes(memory_get_usage(true)),
                'peakMemory' => $this->formatBytes(memory_get_peak_usage(true)),
            ],

            // Environment variables (safe subset)
            'environment' => $this->getSafeEnvironment(),

            // Search providers
            'searchProviders' => $this->searchProviders,

            // Editor config
            'editor' => $this->editor,

            // Theme colors
            'theme' => $this->getThemeColors(),
        ];
    }

    /**
     * Build exception chain info (without frames, just for header display)
     *
     * @return list<array{
     *     index: int,
     *     class: string,
     *     shortClass: string,
     *     message: string,
     *     code: int|string,
     *     file: string,
     *     line: int,
     *     shortFile: string,
     *     arguments: array<string, array{type: string, value: string}>
     * }>
     */
    private function buildExceptionChain(Throwable $exception): array
    {
        $chain = [];
        $current = $exception;
        $index = 0;
        $visited = new \SplObjectStorage();

        while ($current !== null) {
            // Check limit (null = unlimited)
            if ($this->maxExceptions !== null && $index >= $this->maxExceptions) {
                break;
            }

            // Protect against circular references
            if ($visited->offsetExists($current)) {
                break;
            }
            $visited->offsetSet($current);

            $chain[] = [
                'index' => $index,
                'class' => $current::class,
                'shortClass' => $this->getShortClassName($current::class),
                'message' => $current->getMessage(),
                'code' => $current->getCode(),
                'file' => $current->getFile(),
                'line' => $current->getLine(),
                'shortFile' => $this->getShortPath($current->getFile()),
                'arguments' => $this->getExceptionArguments($current),
            ];
            $current = $current->getPrevious();
            $index++;
        }

        return $chain;
    }

    /**
     * Build unified stack trace from exception and all previous exceptions
     *
     * Creates a single list of frames matching Whoops behavior:
     * 1. All exception markers first (outer to inner)
     * 2. Frames from inner exceptions (excluding common tail with outer)
     * 3. Full trace of outer exception
     *
     * @return list<array<string, mixed>>
     */
    private function buildUnifiedFrames(Throwable $exception): array
    {
        // Step 1: Collect all exceptions in chain (outer to inner)
        $exceptions = [];
        $current = $exception;
        $visited = new \SplObjectStorage();

        while ($current !== null) {
            if ($this->maxExceptions !== null && count($exceptions) >= $this->maxExceptions) {
                break;
            }
            if ($visited->offsetExists($current)) {
                break;
            }
            $visited->offsetSet($current);
            $exceptions[] = $current;
            $current = $current->getPrevious();
        }

        // Step 2: Build unified frames
        $frames = [];
        $frameIndex = 0;

        // Add all exception markers first (outer to inner)
        foreach ($exceptions as $excIndex => $exc) {
            $frames[] = [
                'index' => $frameIndex++,
                'isExceptionMarker' => true,
                'exceptionIndex' => $excIndex,
                'exceptionClass' => $exc::class,
                'exceptionShortClass' => $this->getShortClassName($exc::class),
                'exceptionMessage' => $exc->getMessage(),
                'exceptionCode' => $exc->getCode(),
                'class' => null,
                'shortClass' => null,
                'function' => '<throw>',
                'file' => $exc->getFile(),
                'shortFile' => $this->getShortPath($exc->getFile()),
                'line' => $exc->getLine(),
                'args' => [],
                'code' => $this->getCodeSnippet($exc->getFile(), $exc->getLine()),
            ];
        }

        $outerException = $exceptions[0];
        $outerTrace = $outerException->getTrace();

        // Add frames from inner exceptions, excluding common tail with outer
        for ($i = count($exceptions) - 1; $i > 0; $i--) {
            $innerException = $exceptions[$i];
            $innerTrace = $innerException->getTrace();
            $excIndex = $i;

            // Find where inner trace merges with outer trace (common tail)
            // Compare from the END of both traces to find common suffix
            $commonTailLength = $this->findCommonTailLength($innerTrace, $outerTrace);

            // Add frames from inner trace, excluding the common tail
            $uniqueFrameCount = count($innerTrace) - $commonTailLength;
            for ($j = 0; $j < $uniqueFrameCount; $j++) {
                if ($this->maxFrames !== null && $frameIndex >= $this->maxFrames) {
                    break 2;
                }

                $frames[] = $this->buildFrameData($innerTrace[$j], $frameIndex++, $excIndex);
            }
        }

        // Add full trace from outer exception
        foreach ($outerTrace as $traceFrame) {
            if ($this->maxFrames !== null && $frameIndex >= $this->maxFrames) {
                break;
            }

            $frames[] = $this->buildFrameData($traceFrame, $frameIndex++, 0);
        }

        return $frames;
    }

    /**
     * Find the length of common tail (suffix) between two traces
     *
     * Compares traces from the end to find how many frames are identical
     * @param list<array{function?: string, line?: int, file?: string, class?: class-string, type?: '->'|'::', args?: list<mixed>, object?: object}> $innerTrace
     * @param list<array{function?: string, line?: int, file?: string, class?: class-string, type?: '->'|'::', args?: list<mixed>, object?: object}> $outerTrace
     */
    private function findCommonTailLength(array $innerTrace, array $outerTrace): int
    {
        $innerLen = count($innerTrace);
        $outerLen = count($outerTrace);
        $commonLength = 0;

        // Compare from end of both arrays
        for ($i = 1; $i <= min($innerLen, $outerLen); $i++) {
            $innerFrame = $innerTrace[$innerLen - $i];
            $outerFrame = $outerTrace[$outerLen - $i];

            if ($this->buildFrameKey($innerFrame) === $this->buildFrameKey($outerFrame)) {
                $commonLength++;
            } else {
                break;
            }
        }

        return $commonLength;
    }

    /**
     * Build a unique key for frame comparison
     * @param array{function?: string, line?: int, file?: string, class?: class-string, type?: '->'|'::', args?: list<mixed>, object?: object} $frame
     */
    private function buildFrameKey(array $frame): string
    {
        return sprintf(
            '%s:%d:%s:%s',
            $frame['file'] ?? '',
            $frame['line'] ?? 0,
            $frame['class'] ?? '',
            $frame['function'] ?? ''
        );
    }

    /**
     * Build frame data array from trace frame
     * @param array{function?: string, line?: int, file?: string, class?: class-string, type?: '->'|'::', args?: list<mixed>, object?: object} $traceFrame
     * @return array<string, mixed>
     */
    private function buildFrameData(array $traceFrame, int $index, int $excIndex): array
    {
        $file = $traceFrame['file'] ?? null;
        $line = $traceFrame['line'] ?? null;
        $class = $traceFrame['class'] ?? null;

        return [
            'index' => $index,
            'isExceptionMarker' => false,
            'exceptionIndex' => $excIndex,
            'exceptionClass' => null,
            'exceptionShortClass' => null,
            'exceptionMessage' => null,
            'exceptionCode' => null,
            'class' => $class,
            'shortClass' => $class !== null ? $this->getShortClassName($class) : null,
            'function' => $traceFrame['function'] ?? null,
            'file' => $file,
            'shortFile' => $file !== null ? $this->getShortPath($file) : null,
            'line' => $line,
            'args' => $this->formatArgs($traceFrame['args'] ?? []),
            'code' => $file !== null && $line !== null ? $this->getCodeSnippet($file, $line) : null,
        ];
    }

    /**
     * Get custom arguments/properties of an exception (excluding standard Exception properties)
     *
     * @return array<string, array{type: string, value: string}>
     */
    private function getExceptionArguments(Throwable $exception): array
    {
        $arguments = [];

        // Standard Exception properties to exclude
        $standardProps = ['message', 'code', 'file', 'line', 'trace', 'previous', 'string'];

        try {
            $reflection = new \ReflectionObject($exception);

            foreach ($reflection->getProperties() as $property) {
                if (count($arguments) >= 64) {
                    break;
                }
                $name = $property->getName();

                // Skip standard properties
                if (in_array($name, $standardProps, true) || $property->isStatic() || $property->isVirtual()) {
                    continue;
                }

                try {
                    if (\Componenta\Error\Diagnostic\DiagnosticData::isSensitiveKey($name)) {
                        $arguments[$name] = ['type' => 'redacted', 'value' => \Componenta\Error\Diagnostic\DiagnosticData::REDACTED];
                        continue;
                    }
                    $value = $property->getRawValue($exception);

                    $arguments[$name] = [
                        'type' => $this->getValueType($value),
                        'value' => $this->formatValue(\Componenta\Error\Diagnostic\DiagnosticData::sanitize($value)),
                    ];
                } catch (\Throwable) {
                    $arguments[$name] = [
                        'type' => 'unknown',
                        'value' => '<unable to read>',
                    ];
                }
            }
        } catch (\Throwable) {
            // Reflection failed
        }

        return $arguments;
    }

    /**
     * Format function arguments for display
     *
     * @param list<mixed> $args
     * @return list<array{type: string, value: string}>
     */
    private function formatArgs(array $args): array
    {
        $formatted = [];

        foreach (array_slice($args, 0, 64) as $arg) {
            $formatted[] = [
                'type' => $this->getValueType($arg),
                'value' => \Componenta\Error\Diagnostic\DiagnosticData::REDACTED,
            ];
        }

        return $formatted;
    }

    /**
     * Get human-readable type of a value
     */
    private function getValueType(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_string($value) => 'string',
            is_array($value) => 'array',
            is_object($value) => $this->getShortClassName($value::class),
            is_resource($value) => 'resource',
            default => 'unknown',
        };
    }

    /**
     * Format a value for display
     */
    private function formatValue(mixed $value, int $depth = 0): string
    {
        if ($depth > $this->maxArrayDepth) {
            return '...';
        }

        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => $this->formatString($value),
            is_array($value) => $this->formatArray($value, $depth),
            is_object($value) => $this->formatObject($value, $depth),
            is_resource($value) => 'resource(' . get_resource_type($value) . ')',
            default => '?',
        };
    }

    /**
     * Format string with truncation and binary detection
     */
    private function formatString(string $value): string
    {
        $length = strlen($value);

        // Check for binary data (null bytes or invalid UTF-8)
        if (str_contains($value, "\0") || !mb_check_encoding($value, 'UTF-8')) {
            return 'binary(' . $length . ' bytes)';
        }

        if ($length > $this->maxStringLength) {
            return '"' . mb_strcut($value, 0, $this->maxStringLength, 'UTF-8') . '..." (' . $length . ' chars)';
        }

        return '"' . $value . '"';
    }

    /**
     * Format array for display
     * @param array<array-key, mixed> $value
     */
    private function formatArray(array $value, int $depth): string
    {
        if (empty($value)) {
            return '[]';
        }

        $count = count($value);
        $isAssoc = !array_is_list($value);
        $items = [];
        $shown = 0;
        $maxItems = 10;

        foreach ($value as $k => $v) {
            if ($shown >= $maxItems) {
                $items[] = '... +' . ($count - $shown) . ' more';
                break;
            }

            $formattedValue = \Componenta\Error\Diagnostic\DiagnosticData::isSensitiveKey($k)
                ? \Componenta\Error\Diagnostic\DiagnosticData::REDACTED : $this->formatValue($v, $depth + 1);

            if ($isAssoc) {
                $items[] = (is_string($k) ? '"' . $k . '"' : $k) . ' => ' . $formattedValue;
            } else {
                $items[] = $formattedValue;
            }

            $shown++;
        }

        return '[' . implode(', ', $items) . ']';
    }

    /**
     * Format object for display
     */
    private function formatObject(object $value, int $depth): string
    {
        return $value instanceof \SensitiveParameterValue
            ? \Componenta\Error\Diagnostic\DiagnosticData::REDACTED
            : $this->getShortClassName($value::class) . '{...}';
    }

    /**
     * Format uploaded files
     *
     * @param array<array-key, mixed> $files
     * @return list<array{name: string, clientName: string, size: string, type: string, error: string}>
     */
    private function formatUploadedFiles(array $files, int $depth = 0, int &$remaining = 1024): array
    {
        if ($depth >= 5) {
            return [];
        }
        $formatted = [];

        foreach (array_slice($files, 0, 100, true) as $key => $file) {
            if (count($formatted) >= 100 || $remaining <= 0) {
                break;
            }
            --$remaining;
            if (is_array($file)) {
                // Nested files
                foreach ($this->formatUploadedFiles($file, $depth + 1, $remaining) as $nested) {
                    $nested['name'] = $key . '[' . $nested['name'] . ']';
                    $formatted[] = $nested;
                }
            } elseif ($file instanceof \Psr\Http\Message\UploadedFileInterface) {
                $formatted[] = [
                    'name' => (string) $key,
                    'clientName' => $file->getClientFilename() ?? 'unknown',
                    'size' => $this->formatBytes($file->getSize() ?? 0),
                    'type' => $file->getClientMediaType() ?? 'unknown',
                    'error' => $this->getUploadErrorMessage($file->getError()),
                ];
            }
        }

        return array_slice($formatted, 0, 100);
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_OK => 'OK',
            UPLOAD_ERR_INI_SIZE => 'Exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'Exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'Partial upload',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write',
            UPLOAD_ERR_EXTENSION => 'Blocked by extension',
            default => 'Unknown error',
        };
    }

    /**
     * Get raw request body
     *
     * @return array{content: string, truncated: bool, size: int}|null
     */
    private function getRawBody(ServerRequestInterface $request): ?array
    {
        $body = $request->getBody();
        $position = null;
        try {
            $type = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'), 2)[0]));
            if ($type !== 'application/json' || !$body->isReadable() || !$body->isSeekable()) {
                return null;
            }
            $position = $body->tell();
            $body->rewind();
            $content = '';
            $limit = $this->maxBodyLength + 1;
            while (!$body->eof() && strlen($content) < $limit) {
                $chunk = $body->read($limit - strlen($content));
                if ($chunk === '') {
                    return null;
                }
                $content .= $chunk;
            }
            if (strlen($content) > $this->maxBodyLength || !$body->eof()) {
                return null;
            }
            $data = json_decode($content, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                return null;
            }
            return [
                'content' => json_encode(\Componenta\Error\Diagnostic\DiagnosticData::sanitize($data), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
                'truncated' => false,
                'size' => strlen($content),
            ];
        } catch (\Throwable) {
            return null;
        } finally {
            if ($position !== null) {
                try {
                    $body->seek($position);
                } catch (\Throwable $failure) {
                    \Componenta\Error\Internal\FailureLog::write('diagnostic stream restoration', $failure);
                }
            }
        }
    }

    /**
     * Get code snippet around the specified line
     *
     * @return array{startLine: int, errorLine: int, lines: list<string>}|null
     */
    private function getCodeSnippet(string $file, int $errorLine): ?array
    {
        return $this->includeSource ? \Componenta\Error\Diagnostic\SourceExcerpt::read($file, $errorLine, self::CONTEXT_LINES) : null;
    }

    /**
     * Flatten headers array to key-value pairs
     *
     * @param array<array-key, array<string>> $headers
     * @return array<string, string>
     */
    private function flattenHeaders(array $headers): array
    {
        $result = [];
        foreach (array_slice($headers, 0, 100, true) as $name => $values) {
            // Only recognized non-credential headers expose their values.
            $result[(string) $name] = in_array(strtolower((string) $name), ['accept', 'accept-language', 'content-type', 'content-length', 'host', 'user-agent'], true)
                ? mb_strcut(implode(', ', $values), 0, 4096, 'UTF-8')
                : \Componenta\Error\Diagnostic\DiagnosticData::REDACTED;
        }
        return $result;
    }

    /**
     * Get safe environment variables
     *
     * @return array<string, string>
     */
    private function getSafeEnvironment(): array
    {
        $safe = [];
        $keys = ['APP_ENV', 'APP_DEBUG', 'APP_NAME', 'DB_CONNECTION', 'CACHE_DRIVER', 'SESSION_DRIVER'];

        foreach ($keys as $key) {
            $value = $_ENV[$key] ?? getenv($key);
            if (is_string($value) && $value !== '') {
                $safe[$key] = mb_strcut($value, 0, 4096, 'UTF-8');
            }
        }

        return $safe;
    }

    /**
     * Render the template with data
     *
     * @param array<string, mixed> $data
     */
    private function renderTemplate(array $data): string
    {
        $templatePath = $this->templatePath ?? __DIR__ . '/templates/pretty-page.phtml';

        if (!is_file($templatePath)) {
            throw new \RuntimeException("Template not found: {$templatePath}");
        }

        $data['dump'] = fn(mixed $value): string => $this->formatValue($value);

        return $this->includeTemplate($templatePath, $data);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function includeTemplate(string $templatePath, array $payload): string
    {
        return \Componenta\Error\Internal\TemplateOutput::render($templatePath, $payload);
    }

    /**
     * Get theme color scheme
     *
     * @return array{
     *     bg: string,
     *     bg2: string,
     *     bg3: string,
     *     text: string,
     *     text2: string,
     *     muted: string,
     *     border: string,
     *     codeBg: string,
     *     highlightCss: string,
     *     gradientOpacity: string,
     *     hoverBg: string,
     *     highlightBg: string,
     *     errorLineBg: string,
     *     sidebarErrorBg: string,
     *     badgeOpacity: string,
     *     activeBg: string
     * }
     */
    private function getThemeColors(): array
    {
        if ($this->theme === self::THEME_LIGHT) {
            return [
                'bg' => '#f7f7f8',
                'bg2' => '#ffffff',
                'bg3' => '#efefef',
                'text' => '#1a1a1a',
                'text2' => '#666666',
                'muted' => '#999999',
                'border' => '#e5e5e5',
                'codeBg' => '#fafafa',
                'highlightCss' => 'github.min.css',
                'gradientOpacity' => '0.03',
                'hoverBg' => 'rgba(0, 0, 0, 0.02)',
                'highlightBg' => 'rgba(106, 143, 217, 0.08)',
                'errorLineBg' => 'rgba(239, 68, 68, 0.08)',
                'sidebarErrorBg' => 'rgba(239, 68, 68, 0.12)',
                'badgeOpacity' => '0.08',
                'activeBg' => 'rgba(106, 143, 217, 0.08)',
            ];
        }

        // Dark theme (default)
        return [
            'bg' => '#0c0c0e',
            'bg2' => '#141416',
            'bg3' => '#1a1a1d',
            'text' => '#fafafa',
            'text2' => '#a1a1aa',
            'muted' => '#52525b',
            'border' => '#232326',
            'codeBg' => '#09090b',
            'highlightCss' => 'github-dark.min.css',
            'gradientOpacity' => '0.06',
            'hoverBg' => 'rgba(255, 255, 255, 0.02)',
            'highlightBg' => 'rgba(106, 143, 217, 0.12)',
            'errorLineBg' => 'rgba(239, 68, 68, 0.1)',
            'sidebarErrorBg' => 'rgba(239, 68, 68, 0.15)',
            'badgeOpacity' => '0.1',
            'activeBg' => 'rgba(106, 143, 217, 0.1)',
        ];
    }

    /**
     * Get short class name without namespace
     */
    private function getShortClassName(string $class): string
    {
        $pos = strrpos($class, '\\');
        return $pos === false ? $class : substr($class, $pos + 1);
    }

    /**
     * Get shortened file path relative to project root (cached)
     */
    private function getShortPath(string $path): string
    {
        if (isset($this->shortPathCache[$path])) {
            return $this->shortPathCache[$path];
        }

        if (count($this->shortPathCache) >= 256) {
            $this->shortPathCache = [];
        }
        $realPath = realpath($path) ?: $path;

        foreach ($this->basePaths as $base) {
            if (str_starts_with($realPath, $base . DIRECTORY_SEPARATOR)) {
                $short = './' . ltrim(substr($realPath, strlen($base)), '/\\');
                // Normalize to forward slashes
                $short = str_replace('\\', '/', $short);
                $this->shortPathCache[$path] = $short;
                return $short;
            }
        }

        // Normalize to forward slashes even for non-matched paths
        $normalized = str_replace('\\', '/', $path);
        $this->shortPathCache[$path] = $normalized;
        return $normalized;
    }

    /**
     * Format bytes to human-readable string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }

    public function renderHttp(Throwable $exception, \Componenta\Error\Context\HttpErrorContextInterface $context): RenderedError
    {
        return !$this->debug
            ? (new SafeRenderer())->renderHttp($exception, $context)
            : new RenderedError($this->render($exception, $context), 'text/html; charset=utf-8');
    }
}
