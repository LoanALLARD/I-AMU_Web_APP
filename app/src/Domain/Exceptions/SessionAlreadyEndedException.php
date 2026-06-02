<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

/**
 * Thrown when a transition that requires a non-terminal state is attempted
 * on a session whose status is ENDED.
 */
final class SessionAlreadyEndedException extends SessionException
{
}
