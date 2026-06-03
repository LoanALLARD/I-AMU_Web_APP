<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

/**
 * Thrown when an edit (rename, reschedule, model change…) is attempted on
 * a session whose status or schedule no longer permits modifications.
 *
 * A session is editable only while:
 *   - status is DRAFT or SCHEDULED, AND
 *   - the scheduled start is either not set or still in the future.
 */
final class SessionNotEditableException extends SessionException
{
}
