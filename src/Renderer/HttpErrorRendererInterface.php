<?php

declare(strict_types=1);

namespace Componenta\Error\Renderer;

use Componenta\Error\Context\HttpErrorContextInterface;
use Throwable;

/** A renderer that can describe the HTTP representation it actually produces. */
interface HttpErrorRendererInterface extends ErrorRendererInterface
{
    public function renderHttp(Throwable $exception, HttpErrorContextInterface $context): RenderedError;
}
