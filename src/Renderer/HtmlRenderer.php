<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Throwable;

/**
 * HTML error renderer with styled output
 *
 * Renders exceptions as styled HTML pages with optional debug information.
 */
readonly class HtmlRenderer implements ErrorRendererInterface
{
    /**
     * Create HTML renderer
     *
     * @param bool $debug Include debug information (trace, file, line)
     * @param string $charset HTML charset
     */
    public function __construct(
        private bool $debug = false,
        private string $charset = 'UTF-8',
    ) {
    }

    /**
     * Render exception as HTML
     *
     * @param Throwable $exception Exception to render
     * @param ErrorContextInterface $context Context information
     * @return string HTML output
     */
    public function render(Throwable $exception, ErrorContextInterface $context): string
    {
        $type = htmlspecialchars($exception::class, ENT_QUOTES, $this->charset);
        $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, $this->charset);
        $code = $exception->getCode();

        $debug = '';
        if ($this->debug) {
            $file = htmlspecialchars($exception->getFile(), ENT_QUOTES, $this->charset);
            $line = $exception->getLine();
            $trace = htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, $this->charset);

            $debug = <<<HTML
            <div class="debug">
                <div class="location">$file:$line</div>
                <pre class="trace">$trace</pre>
            </div>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="{$this->charset}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$type</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 2rem;
            color: #e4e4e7;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        .header {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .type { color: #ef4444; font-size: 1.25rem; font-weight: 600; }
        .code { color: #71717a; font-size: 0.875rem; margin-top: 0.25rem; }
        .message { margin-top: 1rem; font-size: 1.125rem; line-height: 1.6; }
        .debug {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 1.5rem;
        }
        .location {
            color: #60a5fa;
            font-family: monospace;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        .trace {
            background: rgba(0, 0, 0, 0.3);
            padding: 1rem;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 0.8125rem;
            line-height: 1.6;
            color: #a1a1aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="type">$type</div>
            <div class="code">Code: $code</div>
            <div class="message">$message</div>
        </div>
        $debug
    </div>
</body>
</html>
HTML;
    }

    /**
     * Check if renderer supports the exception
     *
     * @param Throwable $exception Exception to check
     * @param ErrorContextInterface $context Context information
     * @return true Always returns true
     */
    public function supports(Throwable $exception, ErrorContextInterface $context): true
    {
        return true;
    }
}
