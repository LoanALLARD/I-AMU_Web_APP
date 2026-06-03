<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use DomainException;

/**
 * Base class for all Session-related domain exceptions.
 * Catching this catches every business rule violation around sessions.
 */
abstract class SessionException extends DomainException
{
}
