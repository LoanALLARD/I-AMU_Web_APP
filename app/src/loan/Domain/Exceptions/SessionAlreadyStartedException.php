<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

/**
 * Thrown when start() is called on a session that is already ACTIVE or
 * past its endpoint (so it cannot be started again).
 */
final class SessionAlreadyStartedException extends SessionException
{
}
