<?php

declare(strict_types=1);

namespace Core;

use RuntimeException;
use Throwable;

/**
 * An exception that maps directly to an HTTP status code.
 *
 * Thrown by the router (404) or anywhere a request must abort with a specific
 * status and a user-facing message. The global handler in public/index.php
 * catches it and renders the error page via ErrorController.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
