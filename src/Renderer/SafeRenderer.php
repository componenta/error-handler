<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Throwable;
use Componenta\Error\Context\ErrorContextAttribute;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Context\HttpErrorContextInterface;

/**
 * Safe error renderer for production environments
 *
 * Renders generic error messages without exposing sensitive information
 * like stack traces, file paths, or internal error messages.
 * Automatically selects JSON or HTML format based on request headers.
 */
readonly class SafeRenderer implements HttpErrorRendererInterface
{
    /**
     * Create safe renderer
     *
     * @param string $defaultMessage Generic error message
     * @param string|null $supportEmail Optional support email to display
     * @param string $errorIdPrefix Prefix for generated error IDs
     * @param string|null $templatePath Optional safe HTML template path
     */
    public function __construct(
        private string $defaultMessage = 'An unexpected error occurred',
        private ?string $supportEmail = null,
        private string $errorIdPrefix = 'ERR',
        private ?string $templatePath = null,
    ) {
    }

    /**
     * Render exception as safe output
     *
     * @param Throwable $exception Exception to render
     * @param ErrorContextInterface $context Context information
     * @return string Safe HTML or JSON output
     */
    public function render(Throwable $exception, ErrorContextInterface $context): string
    {
        if ($context instanceof HttpErrorContextInterface) {
            return $this->renderHttp($exception, $context)->body;
        }

        $errorId = $context->getAttribute(ErrorContextAttribute::ERROR_ID, null);
        $errorId = is_string($errorId) ? $errorId : $this->generateErrorId();
        $statusCode = $this->getStatusCode($context);

        return $this->renderHtml($errorId, $statusCode);
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

    /**
     * Render JSON response
     */
    private function renderJson(string $errorId, int $statusCode): string
    {
        $data = [
            'error' => [
                'id' => $errorId,
                'status' => $statusCode,
                'message' => $this->defaultMessage,
            ],
        ];

        if ($this->supportEmail !== null) {
            $data['error']['support'] = $this->supportEmail;
        }

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Render HTML response
     */
    private function renderHtml(string $errorId, int $statusCode): string
    {
        $html = $this->renderTemplate(
            $this->templatePath ?? __DIR__ . '/templates/safe-page.phtml',
            [
                'statusCode' => $statusCode,
                'message' => $this->defaultMessage,
                'errorId' => $errorId,
                'supportEmail' => $this->supportEmail,
            ],
        );

        return $html ?? $this->fallbackHtml($statusCode, $errorId);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderTemplate(string $templatePath, array $data): ?string
    {
        if (!is_file($templatePath)) {
            return null;
        }

        $render = static function (string $templatePath, array $data): ?string {
            $level = ob_get_level();
            $e = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            extract($data, EXTR_SKIP);
            ob_start();

            try {
                require $templatePath;

                $output = ob_get_clean();

                return $output === false ? '' : $output;
            } catch (Throwable) {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }

                return null;
            }
        };

        return $render($templatePath, $data);
    }

    private function fallbackHtml(int $statusCode, string $errorId): string
    {
        $status = htmlspecialchars((string) $statusCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = htmlspecialchars($this->defaultMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $id = htmlspecialchars($errorId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Error '
            . $status
            . '</title></head><body><h1>'
            . $status
            . '</h1><p>'
            . $message
            . '</p><p>Error ID: '
            . $id
            . '</p></body></html>';
    }

    /**
     * Generate unique error ID
     */
    private function generateErrorId(): string
    {
        return sprintf(
            '%s-%s-%d',
            $this->errorIdPrefix,
            strtoupper(bin2hex(random_bytes(16))),
            time(),
        );
    }

    /**
     * Get resolved HTTP status code from context.
     */
    private function getStatusCode(ErrorContextInterface $context): int
    {
        $statusCode = $context->getAttribute(ErrorContextAttribute::HTTP_STATUS_CODE, 500);

        return is_int($statusCode) && $statusCode >= 400 && $statusCode < 600 ? $statusCode : 500;
    }

    public function renderHttp(Throwable $exception, HttpErrorContextInterface $context): RenderedError
    {
        $errorId = $context->getAttribute(ErrorContextAttribute::ERROR_ID, null);
        $errorId = is_string($errorId) ? $errorId : $this->generateErrorId();
        $statusCode = $this->getStatusCode($context);

        if (\Componenta\Error\Http\ErrorContentNegotiator::prefersJson($context->request->getHeaderLine('Accept'))) {
            return new RenderedError($this->renderJson($errorId, $statusCode), 'application/json; charset=utf-8');
        }

        return new RenderedError($this->renderHtml($errorId, $statusCode), 'text/html; charset=utf-8');
    }
}
